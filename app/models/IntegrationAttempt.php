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
require_once __DIR__ . '/StudentExam.php';

/**
 * Een poging via een externe koppeling: een gewone gastpoging (student_exams,
 * student_id NULL) met een rij in integration_attempts. Het attempt_id in de
 * API is het student_exam_id. De status wordt niet opgeslagen maar berekend
 * (zie summary() en ARCHITECTURE.md §6.9).
 */
class IntegrationAttempt {

  const STATUS_NOT_STARTED = 'not_started';
  const STATUS_IN_PROGRESS = 'in_progress';
  const STATUS_GRADING = 'grading';
  const STATUS_GRADED = 'graded';
  const STATUS_REVIEWED = 'reviewed';

  private const SELECT = "
      SELECT ia.*, se.exam_id, se.guest_name, se.access_token, se.started_at, se.completed_at,
             e.title AS exam_title, i.name AS integration_name, i.min_confidence, i.webhook_url
      FROM integration_attempts ia
      JOIN student_exams se ON ia.student_exam_id = se.id
      JOIN exams e ON se.exam_id = e.id
      JOIN integrations i ON ia.integration_id = i.id";

  /** De poging, alleen als hij van deze koppeling is (anders null: de API geeft dan 404). */
  public static function findForIntegration($integrationId, $attemptId): ?array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(self::SELECT . " WHERE ia.student_exam_id = ? AND ia.integration_id = ?");
    $stmt->execute([$attemptId, $integrationId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public static function findByRef($integrationId, string $ref): ?array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(self::SELECT . " WHERE ia.integration_id = ? AND ia.external_ref = ?");
    $stmt->execute([$integrationId, $ref]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /** De koppelingsgegevens van een toetspoging, of null als het geen koppelingspoging is. */
  public static function findByStudentExam($studentExamId): ?array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(self::SELECT . " WHERE ia.student_exam_id = ?");
    $stmt->execute([$studentExamId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /** Nieuwste pogingen van een koppeling (voor de detailpagina). */
  public static function recentByIntegration($integrationId, int $limit): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(self::SELECT . " WHERE ia.integration_id = ? ORDER BY ia.student_exam_id DESC LIMIT " . (int)$limit);
    $stmt->execute([$integrationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  // ---------------------------------------------------------------------
  // Starten en de eenmalige startlink
  // ---------------------------------------------------------------------

  private static function hashToken(string $raw): string {
    return hash('sha256', $raw);
  }

  /** Zet een SQLite-tijdstempel (UTC) om naar ISO 8601 met Z, of null. */
  public static function isoTime(?string $sqliteTime): ?string {
    if ($sqliteTime === null || $sqliteTime === '') {
      return null;
    }
    $time = DateTime::createFromFormat('Y-m-d H:i:s', $sqliteTime, new DateTimeZone('UTC'));
    return $time ? $time->format('Y-m-d\TH:i:s\Z') : null;
  }

  /**
   * Start een poging: in één transactie een gastpoging (StudentExam::startGuest())
   * en de koppelingsrij met een nieuw launch-token. Het token wordt alleen als
   * SHA-256-hash opgeslagen.
   * @return array|null ['attempt_id', 'token', 'expires_at'], of null als
   *         external_ref intussen al bestaat (gelijktijdige start)
   */
  public static function create($integrationId, $examId, string $ref, string $displayName, string $returnUrl): ?array {
    $pdo = Database::connect();
    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    try {
      $guest = StudentExam::startGuest($examId, $displayName);
      $stmt = $pdo->prepare("
        INSERT INTO integration_attempts
          (student_exam_id, integration_id, external_ref, return_url, launch_token_hash, launch_expires_at)
        VALUES (?, ?, ?, ?, ?, datetime('now', ?))
      ");
      $stmt->execute([$guest['id'], $integrationId, $ref, $returnUrl, self::hashToken($token),
                      '+' . (int)INTEGRATION_LAUNCH_TTL . ' seconds']);
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      if ($e instanceof PDOException && str_contains($e->getMessage(), 'UNIQUE')
          && self::findByRef($integrationId, $ref)) {
        return null;
      }
      throw $e;
    }
    $attempt = self::findByStudentExam($guest['id']);
    return [
      'attempt_id' => (int)$guest['id'],
      'token' => $token,
      'expires_at' => self::isoTime($attempt['launch_expires_at']),
    ];
  }

  /**
   * Nieuwe startlink voor een bestaande poging; het vorige token vervalt.
   * @return array ['token', 'expires_at']
   */
  public static function newLaunchToken($studentExamId): array {
    $token = bin2hex(random_bytes(32));
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE integration_attempts
      SET launch_token_hash = ?, launch_expires_at = datetime('now', ?), updated_at = CURRENT_TIMESTAMP
      WHERE student_exam_id = ?
    ");
    $stmt->execute([self::hashToken($token), '+' . (int)INTEGRATION_LAUNCH_TTL . ' seconds', $studentExamId]);
    $attempt = self::findByStudentExam($studentExamId);
    return ['token' => $token, 'expires_at' => self::isoTime($attempt['launch_expires_at'] ?? null)];
  }

  private static function validTokenFormat(string $raw): bool {
    return preg_match('/^[a-f0-9]{64}$/', $raw) === 1;
  }

  /** Voorwaarden voor een bruikbare startlink: niet verlopen, niet ingeleverd, koppeling actief. */
  private const LAUNCHABLE = "
      ia.launch_token_hash = ?
      AND ia.launch_expires_at > CURRENT_TIMESTAMP
      AND se.completed_at IS NULL
      AND EXISTS (SELECT 1 FROM api_keys k WHERE k.id = i.api_key_id AND k.active = 1)";

  /** Alleen lezen (landingspagina): de poging bij een geldige, ongebruikte startlink, of null. */
  public static function findByLaunchToken(string $raw): ?array {
    if (!self::validTokenFormat($raw)) {
      return null;
    }
    $pdo = Database::connect();
    $stmt = $pdo->prepare(self::SELECT . " WHERE " . self::LAUNCHABLE);
    $stmt->execute([self::hashToken($raw)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /**
   * Verbruikt een startlink atomair. Het token wordt gewist (dus eenmalig, ook
   * bij gelijktijdig gebruik: alleen het UPDATE dat één rij raakt, wint).
   * launch_used_at blijft het moment van de eerste start.
   * @return array|null de poging, of null als het token onbekend, verlopen of al gebruikt is
   */
  public static function consumeLaunchToken(string $raw): ?array {
    $attempt = self::findByLaunchToken($raw);
    if ($attempt === null) {
      return null;
    }
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE integration_attempts
      SET launch_token_hash = NULL,
          launch_used_at = COALESCE(launch_used_at, CURRENT_TIMESTAMP),
          updated_at = CURRENT_TIMESTAMP
      WHERE student_exam_id = ? AND launch_token_hash = ? AND launch_expires_at > CURRENT_TIMESTAMP
    ");
    $stmt->execute([$attempt['student_exam_id'], self::hashToken($raw)]);
    if ($stmt->rowCount() !== 1) {
      return null;
    }
    return self::findByStudentExam($attempt['student_exam_id']);
  }

  /**
   * De terugkeer-URL met attempt_id, external_ref en status in de query.
   * Een bestaande query blijft staan; parameters met dezelfde naam worden vervangen.
   * Bevat bewust geen scores: de externe website haalt die op met de API.
   */
  public static function returnUrlFor(array $attempt, string $status): string {
    $url = (string)$attempt['return_url'];
    $query = '';
    $pos = strpos($url, '?');
    if ($pos !== false) {
      $query = substr($url, $pos + 1);
      $url = substr($url, 0, $pos);
    }
    $ours = ['attempt_id', 'external_ref', 'status'];
    $kept = array_filter($query === '' ? [] : explode('&', $query), function ($pair) use ($ours) {
      return $pair !== '' && !in_array(urldecode(explode('=', $pair, 2)[0]), $ours, true);
    });
    $kept[] = http_build_query([
      'attempt_id' => (int)$attempt['student_exam_id'],
      'external_ref' => (string)$attempt['external_ref'],
      'status' => $status,
    ]);
    return $url . '?' . implode('&', $kept);
  }
}
