<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/ApiKey.php';

/**
 * Externe koppeling: een andere website die haar deelnemers hier een toets laat
 * maken (zie ARCHITECTURE.md §6.9). Beheerd door de admin. De API-key van een
 * koppeling heeft scope integration; aan- en uitzetten gaat via api_keys.active.
 */
class Integration {

  /** Drempel voor review_needed: onder deze confidence moet een mens kijken. */
  const CONFIDENCES = ['hoog', 'middel', 'laag'];

  /** Alleen met INTEGRATION_ALLOW_HTTP (Docker-dev): http naar deze hosts. */
  const DEV_HTTP_HOSTS = ['localhost', '127.0.0.1', 'host.docker.internal'];

  const MAX_URL_LENGTH = 1000;

  private const SELECT = "
      SELECT i.*, k.active, k.name AS api_key_name
      FROM integrations i
      JOIN api_keys k ON i.api_key_id = k.id";

  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(self::SELECT . " WHERE i.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public static function findByApiKeyId($keyId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(self::SELECT . " WHERE i.api_key_id = ?");
    $stmt->execute([$keyId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /** Alle koppelingen met het aantal toetsen en het aantal nog niet afgeleverde events. */
  public static function all(): array {
    $pdo = Database::connect();
    return $pdo->query("
      SELECT i.*, k.active,
             (SELECT COUNT(*) FROM integration_exams ie WHERE ie.integration_id = i.id) AS exam_count,
             (SELECT COUNT(*) FROM integration_events ev
              WHERE ev.integration_id = i.id AND ev.delivered_at IS NULL) AS undelivered_events
      FROM integrations i
      JOIN api_keys k ON i.api_key_id = k.id
      ORDER BY i.name COLLATE NOCASE
    ")->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function nameExists(string $name, ?int $exceptId = null): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM integrations WHERE name = ? COLLATE NOCASE AND id != ?");
    $stmt->execute([$name, $exceptId ?? 0]);
    return (int)$stmt->fetchColumn() > 0;
  }

  // ---------------------------------------------------------------------
  // Aanmaken, wijzigen, verwijderen
  // ---------------------------------------------------------------------

  private static function newSecret(): string {
    return bin2hex(random_bytes(32));
  }

  /**
   * Maakt in één transactie de API-key (scope integration), het webhookgeheim
   * en de koppeling aan.
   * @param array $data name, return_origin, webhook_url (of null), min_confidence
   * @return array ['id', 'key', 'secret']; key en secret alleen nu tonen!
   */
  public static function create(array $data, int $userId): array {
    $pdo = Database::connect();
    $pdo->beginTransaction();
    try {
      $apiKey = ApiKey::createScoped($data['name'], ApiKey::SCOPE_INTEGRATION);
      $secret = self::newSecret();
      $stmt = $pdo->prepare("
        INSERT INTO integrations (name, api_key_id, return_origin, webhook_url, webhook_secret, min_confidence, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$data['name'], $apiKey['id'], $data['return_origin'], $data['webhook_url'],
                      $secret, $data['min_confidence'], $userId]);
      $id = (int)$pdo->lastInsertId();
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
    return ['id' => $id, 'key' => $apiKey['key'], 'secret' => $secret];
  }

  /** Wijzigt de gegevens; de naam van de API-key loopt mee (rate limit en audit log gebruiken die). */
  public static function update($id, array $data): void {
    $pdo = Database::connect();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("
        UPDATE integrations
        SET name = ?, return_origin = ?, webhook_url = ?, min_confidence = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
      ");
      $stmt->execute([$data['name'], $data['return_origin'], $data['webhook_url'], $data['min_confidence'], $id]);
      $pdo->prepare("UPDATE api_keys SET name = ? WHERE id = (SELECT api_key_id FROM integrations WHERE id = ?)")
          ->execute([$data['name'], $id]);
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  /** Nieuw webhookgeheim; geeft het (alleen nu te tonen) geheim terug. */
  public static function rotateSecret($id): string {
    $secret = self::newSecret();
    $pdo = Database::connect();
    $pdo->prepare("UPDATE integrations SET webhook_secret = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$secret, $id]);
    return $secret;
  }

  /**
   * Verwijdert de API-key; de CASCADE verwijdert de koppeling, de gekoppelde
   * toetsen, de koppelingsrijen van de pogingen en de events. De pogingen zelf
   * (student_exams) blijven als gewone gastpogingen bestaan.
   */
  public static function delete($id): void {
    $pdo = Database::connect();
    $pdo->prepare("DELETE FROM api_keys WHERE id = (SELECT api_key_id FROM integrations WHERE id = ?)")
        ->execute([$id]);
  }

  // ---------------------------------------------------------------------
  // Gekoppelde toetsen
  // ---------------------------------------------------------------------

  public static function examIds($id): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT exam_id FROM integration_exams WHERE integration_id = ? ORDER BY exam_id");
    $stmt->execute([$id]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
  }

  /** Gekoppelde toetsen met titel, AI-beoordeling en het aantal vragen (voor de beheerschermen). */
  public static function exams($id): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT e.id, e.title, e.ai_grading_enabled,
             (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS question_count
      FROM integration_exams ie
      JOIN exams e ON ie.exam_id = e.id
      WHERE ie.integration_id = ?
      ORDER BY e.title COLLATE NOCASE
    ");
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function setExams($id, array $examIds): void {
    $pdo = Database::connect();
    $pdo->beginTransaction();
    try {
      $pdo->prepare("DELETE FROM integration_exams WHERE integration_id = ?")->execute([$id]);
      $insert = $pdo->prepare("INSERT OR IGNORE INTO integration_exams (integration_id, exam_id) VALUES (?, ?)");
      foreach ($examIds as $examId) {
        $insert->execute([$id, (int)$examId]);
      }
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  /** De toets, maar alleen als hij aan de koppeling hangt en AI-beoordeling aan staat. */
  public static function allowedExam($id, $examId): ?array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT e.*
      FROM integration_exams ie
      JOIN exams e ON ie.exam_id = e.id
      WHERE ie.integration_id = ? AND ie.exam_id = ? AND e.ai_grading_enabled = 1
    ");
    $stmt->execute([$id, $examId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /** Gekoppelde toetsen die gestart kunnen worden (AI aan), voor GET integration_exams. */
  public static function startableExams($id): array {
    return array_values(array_filter(self::exams($id), fn($e) => (int)$e['ai_grading_enabled'] === 1));
  }

  // ---------------------------------------------------------------------
  // URL-regels (open redirect en SSRF)
  // ---------------------------------------------------------------------

  /**
   * Ontleedt een absolute URL streng: alleen https (of http naar een dev-host
   * met INTEGRATION_ALLOW_HTTP), geen userinfo, geen fragment, geen
   * witruimte, backslashes of stuurtekens, maximaal MAX_URL_LENGTH tekens.
   * @return array|null ['origin' => 'scheme://host[:port]', 'path', 'query'] of null
   */
  private static function parseUrl(string $url): ?array {
    if ($url === '' || strlen($url) > self::MAX_URL_LENGTH
        || preg_match('/[\s\\\\\x00-\x1f\x7f]/', $url)
        || !preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
      return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
        || str_contains($url, '#')) {
      return null;
    }
    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host)) {
      return null;
    }
    if ($scheme !== 'https' && !($scheme === 'http' && self::allowsHttpHost($host))) {
      return null;
    }
    $port = $parts['port'] ?? null;
    if ($port !== null && ($port < 1 || $port > 65535)) {
      return null;
    }
    $defaultPort = $scheme === 'https' ? 443 : 80;
    $origin = $scheme . '://' . $host . ($port !== null && $port !== $defaultPort ? ':' . $port : '');
    return ['origin' => $origin, 'path' => $parts['path'] ?? '', 'query' => $parts['query'] ?? null];
  }

  private static function allowsHttpHost(string $host): bool {
    return INTEGRATION_ALLOW_HTTP && in_array($host, self::DEV_HTTP_HOSTS, true);
  }

  /** Genormaliseerde origin (scheme://host[:port]) zonder pad, query of fragment; null als ongeldig. */
  public static function normalizeOrigin(string $url): ?string {
    $url = trim($url);
    $parsed = self::parseUrl($url);
    if ($parsed === null || !in_array($parsed['path'], ['', '/'], true) || $parsed['query'] !== null
        || str_contains($url, '?')) {
      return null;
    }
    return $parsed['origin'];
  }

  /** Webhook-URL: https (of dev-http), geen userinfo of fragment. Pad en query mogen. */
  public static function validWebhookUrl(string $url): bool {
    return self::parseUrl($url) !== null;
  }

  /** Terugkeer-URL: precies de geregistreerde origin, geen userinfo of fragment, maximaal 1000 tekens. */
  public static function allowsReturnUrl(array $integration, string $url): bool {
    $parsed = self::parseUrl($url);
    return $parsed !== null && hash_equals((string)$integration['return_origin'], $parsed['origin']);
  }
}
