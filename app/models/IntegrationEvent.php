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
require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/Integration.php';

/**
 * Outbox voor de webhooks van externe koppelingen (zie ARCHITECTURE.md §6.9).
 * Eén rij per (poging, event); afgeleverd tijdens de polls van de workers.
 */
class IntegrationEvent {

  const EVENTS = ['attempt.submitted', 'attempt.graded', 'attempt.reviewed'];

  /**
   * Zet een event in de outbox. Elk event komt per poging maar één keer
   * (INSERT OR IGNORE op UNIQUE (student_exam_id, event)).
   * @param array $payload zonder event_id (wordt bij het versturen toegevoegd)
   * @return int|null het nieuwe id, of null als het event al bestond
   */
  public static function enqueue($integrationId, $studentExamId, string $event, array $payload): ?int {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      INSERT OR IGNORE INTO integration_events (integration_id, student_exam_id, event, payload)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$integrationId, $studentExamId, $event, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    return $stmt->rowCount() === 1 ? (int)$pdo->lastInsertId() : null;
  }

  /**
   * Events die nu aan de beurt zijn (niet afgeleverd, niet opgegeven), oudste
   * eerst. Events van een uitgeschakelde koppeling wachten tot die weer aan staat.
   */
  public static function due(int $limit): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT ev.* FROM integration_events ev
      JOIN integrations i ON ev.integration_id = i.id
      JOIN api_keys k ON i.api_key_id = k.id
      WHERE ev.delivered_at IS NULL AND ev.next_attempt_at IS NOT NULL AND ev.next_attempt_at <= CURRENT_TIMESTAMP
        AND k.active = 1
      ORDER BY ev.next_attempt_at ASC, ev.id ASC
      LIMIT " . (int)$limit
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** Aantallen per toestand voor de detailpagina: afgeleverd, open (wacht op een poging) en opgegeven. */
  public static function counts($id): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT SUM(delivered_at IS NOT NULL) AS delivered,
             SUM(delivered_at IS NULL AND next_attempt_at IS NOT NULL) AS open,
             SUM(delivered_at IS NULL AND next_attempt_at IS NULL) AS given_up
      FROM integration_events
      WHERE integration_id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
      'delivered' => (int)($row['delivered'] ?? 0),
      'open' => (int)($row['open'] ?? 0),
      'given_up' => (int)($row['given_up'] ?? 0),
    ];
  }

  public static function recentByIntegration($id, int $limit): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT ev.id, ev.student_exam_id, ev.event, ev.attempts, ev.next_attempt_at, ev.delivered_at,
             ev.last_status, ev.last_error, ev.created_at, ia.external_ref
      FROM integration_events ev
      LEFT JOIN integration_attempts ia ON ia.student_exam_id = ev.student_exam_id
      WHERE ev.integration_id = ?
      ORDER BY ev.id DESC
      LIMIT " . (int)$limit
    );
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  // ---------------------------------------------------------------------
  // Afleveren (tijdens de polls van de workers, zie ApiController)
  // ---------------------------------------------------------------------

  /** Handtekening van een webhook: HMAC-SHA256 over "timestamp.body" met het geheim van de koppeling. */
  public static function signature(string $secret, int $ts, string $body): string {
    return 'sha256=' . hash_hmac('sha256', $ts . '.' . $body, $secret);
  }

  /** De body die wordt verstuurd: de opgeslagen payload met event_id vooraan, één keer door json_encode. */
  public static function body(array $event): string {
    $payload = json_decode((string)$event['payload'], true);
    return json_encode(['event_id' => (int)$event['id']] + (is_array($payload) ? $payload : []),
      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  /**
   * Claimt een event voor deze aflevering: schuift next_attempt_at 120 seconden
   * op, alleen als niemand anders dat net deed. Zo levert een gelijktijdige
   * poll van een andere worker niet dubbel af.
   */
  public static function claim(array $event): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE integration_events SET next_attempt_at = datetime('now', '+120 seconds')
      WHERE id = ? AND next_attempt_at = ? AND delivered_at IS NULL
    ");
    $stmt->execute([$event['id'], $event['next_attempt_at']]);
    return $stmt->rowCount() === 1;
  }

  /**
   * Verstuurt één event met curl (geen redirects, korte timeout, alleen
   * https of de dev-uitzondering). Het antwoord van de ontvanger wordt niet
   * opgeslagen. Een 2xx is afgeleverd; anders volgt backoff (30 s · 2^(n-1),
   * maximaal een uur) en na INTEGRATION_WEBHOOK_MAX_ATTEMPTS mislukte
   * pogingen geeft de aflevering het op (next_attempt_at = NULL).
   */
  public static function deliver(array $event, array $integration): void {
    $url = (string)($integration['webhook_url'] ?? '');
    $status = 0;
    $error = null;
    if ($url === '' || !Integration::validWebhookUrl($url)) {
      $error = 'Geen geldige webhook-URL ingesteld';
    } else {
      $body = self::body($event);
      $ts = time();
      $protocols = CURLPROTO_HTTPS | (INTEGRATION_ALLOW_HTTP ? CURLPROTO_HTTP : 0);
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
          'Content-Type: application/json',
          'User-Agent: genai-open-assessment-webhook',
          'X-Assessment-Event: ' . $event['event'],
          'X-Assessment-Timestamp: ' . $ts,
          'X-Assessment-Signature: ' . self::signature((string)$integration['webhook_secret'], $ts, $body),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => $protocols,
        CURLOPT_REDIR_PROTOCOLS => $protocols,
        CURLOPT_TIMEOUT => INTEGRATION_WEBHOOK_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => INTEGRATION_WEBHOOK_TIMEOUT,
        CURLOPT_MAXFILESIZE => 65536,
      ]);
      $result = curl_exec($ch);
      $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      if ($result === false) {
        $error = curl_error($ch) ?: 'Verzoek mislukt';
      } elseif ($status < 200 || $status > 299) {
        $error = 'HTTP ' . $status;
      }
      curl_close($ch);
    }

    $pdo = Database::connect();
    if ($error === null) {
      $pdo->prepare("UPDATE integration_events SET delivered_at = CURRENT_TIMESTAMP, last_status = ?, last_error = NULL WHERE id = ?")
          ->execute([$status, $event['id']]);
      return;
    }

    $attempts = (int)$event['attempts'] + 1;
    $giveUp = $attempts >= INTEGRATION_WEBHOOK_MAX_ATTEMPTS || $url === '';
    $delay = min(30 * (2 ** ($attempts - 1)), 3600);
    $pdo->prepare("
      UPDATE integration_events
      SET attempts = ?, last_status = ?, last_error = ?,
          next_attempt_at = CASE WHEN ? THEN NULL ELSE datetime('now', ?) END
      WHERE id = ?
    ")->execute([$attempts, $status, mb_substr($error, 0, 300), $giveUp ? 1 : 0, '+' . $delay . ' seconds', $event['id']]);

    if ($giveUp) {
      AuditLog::log('integration_webhook_gave_up', [
        'integration_id' => (int)$integration['id'],
        'event_id' => (int)$event['id'],
        'event' => $event['event'],
        'attempts' => $attempts,
        'last_status' => $status,
      ], 'System/API');
    }
  }

  /** Verstuurt hooguit $limit events die aan de beurt zijn. */
  public static function deliverDue(int $limit): void {
    foreach (self::due($limit) as $event) {
      if (!self::claim($event)) {
        continue;
      }
      $integration = Integration::find($event['integration_id']);
      if ($integration) {
        self::deliver($event, $integration);
      }
    }
  }
}
