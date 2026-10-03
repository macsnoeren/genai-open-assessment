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

  /** Events die nu aan de beurt zijn (niet afgeleverd, niet opgegeven), oudste eerst. */
  public static function due(int $limit): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT * FROM integration_events
      WHERE delivered_at IS NULL AND next_attempt_at IS NOT NULL AND next_attempt_at <= CURRENT_TIMESTAMP
      ORDER BY next_attempt_at ASC, id ASC
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
}
