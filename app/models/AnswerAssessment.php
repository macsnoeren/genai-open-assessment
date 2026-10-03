<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/StudentAnswer.php';

/**
 * Agentic beoordelingen van studentantwoorden: de assessment-agents (Evidence,
 * Assessment, Validation) beoordelen een antwoord met de rubric van de vraag,
 * de orchestrator in de worker beslist of menselijke beoordeling nodig is, en
 * de docent past aan en keurt goed.
 *
 * Elke start is een nieuwe rij; een eerdere open of afgeronde run van hetzelfde
 * antwoord wordt superseded. De tabel is ook de wachtrij voor de
 * assessment-worker. Statusovergangen zijn atomair (WHERE id = ? AND status = ?),
 * zodat een verouderd resultaat van de worker nooit een nieuwere run raakt.
 */
class AnswerAssessment {

  // Statussen (geen CHECK in het schema; dit is de bron van waarheid)
  const STATUS_PENDING = 'pending';
  const STATUS_REVIEW = 'review';
  const STATUS_APPROVED = 'approved';
  const STATUS_FAILED = 'failed';
  const STATUS_SUPERSEDED = 'superseded';

  // Contractlimieten (contract 8): gelijk houden met bin/assessment_agents.py
  const MAX_TEXT = 800;
  const MAX_QUOTE = 300;
  const MAX_MODEL_ANSWER = 4000;
  const MAX_CRITERIA = 10;
  const MAX_QUOTES = 3;
  const MAX_ALTERNATIVES = 10;
  const MAX_ISSUES = 10;
  const MAX_CORRECTIONS = 10;
  const MAX_ROUNDS = 3;
  const MAX_REASONS = 10;
  const MAX_SHORT = 100;   // modelnamen en tijdstempels in de run_log
  const WEIGHTS = ['essentieel', 'aanvullend'];
  const LEVELS = ['10', '5', '1', '0'];
  const EVIDENCE_FOUND = ['ja', 'gedeeltelijk', 'nee'];
  const STATUSES = ['voldaan', 'deels', 'niet'];
  const CONFIDENCES = ['hoog', 'middel', 'laag'];
  const AGREEMENTS = ['eens', 'klein_verschil', 'conflict'];
  const SCORES = [0, 1, 5, 10];
  const CHECKS = ['evidence_present', 'interpretation', 'rubric_applied', 'consistent',
                  'alternative_reading', 'missing_or_conflicting', 'confidence'];

  /** JSON-kolommen die decode() omzet naar arrays. */
  const JSON_COLUMNS = ['rubric', 'evidence', 'rounds', 'decision', 'run_log', 'teacher_criteria'];

  /** Nederlands label voor een status. */
  public static function statusLabel(string $status): string {
    switch ($status) {
      case self::STATUS_PENDING: return 'Wordt beoordeeld';
      case self::STATUS_REVIEW: return 'Klaar voor controle';
      case self::STATUS_APPROVED: return 'Goedgekeurd';
      case self::STATUS_FAILED: return 'Mislukt';
      case self::STATUS_SUPERSEDED: return 'Vervangen';
      default: return 'Onbekend';
    }
  }

  /** Bootstrap-klasse voor de statusbadge. */
  public static function statusClass(string $status): string {
    switch ($status) {
      case self::STATUS_REVIEW: return 'bg-primary';
      case self::STATUS_APPROVED: return 'bg-success';
      case self::STATUS_FAILED: return 'bg-danger';
      case self::STATUS_SUPERSEDED: return 'bg-light text-muted border';
      default: return 'bg-secondary';
    }
  }

  /** Nederlands label voor een controle van de Validation Agent. */
  public static function checkLabel(string $check): string {
    $labels = [
      'evidence_present' => 'Het gebruikte bewijs staat echt in het antwoord',
      'interpretation' => 'De interpretatie van het bewijs is redelijk',
      'rubric_applied' => 'De rubric is juist toegepast (geen nieuwe criteria)',
      'consistent' => 'Oordelen per criterium en score zijn consistent',
      'alternative_reading' => 'Een andere redelijke lezing van het antwoord is overwogen',
      'missing_or_conflicting' => 'Ontbrekend of tegenstrijdig bewijs is meegewogen',
      'confidence' => 'De confidence is eerlijk ingeschat',
    ];
    return $labels[$check] ?? $check;
  }

  /** Symbool en tekst bij een status per criterium (✓ voldaan, ~ deels, ✗ niet). */
  public static function statusSymbol(string $status): string {
    return ['voldaan' => '✓', 'deels' => '~', 'niet' => '✗'][$status] ?? '?';
  }

  public static function criterionStatusLabel(string $status): string {
    return ['voldaan' => 'volledig', 'deels' => 'gedeeltelijk', 'niet' => 'onvoldoende'][$status] ?? $status;
  }

  public static function evidenceLabel(string $found): string {
    return ['ja' => 'Bewijs gevonden', 'gedeeltelijk' => 'Gedeeltelijk bewijs', 'nee' => 'Geen voldoende bewijs'][$found] ?? $found;
  }

  public static function agreementLabel(string $agreement): string {
    return ['eens' => 'eens', 'klein_verschil' => 'klein verschil', 'conflict' => 'conflict'][$agreement] ?? $agreement;
  }

  // ---------------------------------------------------------------------
  // Aanmaken en lezen
  // ---------------------------------------------------------------------

  /**
   * Nieuwe run (status pending) met snapshots van vraag, criteria en antwoord.
   * In dezelfde transactie worden de open en afgeronde runs van dit antwoord
   * (pending, review, failed) superseded; een goedgekeurde run blijft staan.
   */
  public static function create(int $studentAnswerId, int $userId, string $question, string $criteria, string $answer): int {
    $pdo = Database::connect();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("
        UPDATE answer_assessments
        SET status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE student_answer_id = ? AND status IN (?, ?, ?)
      ");
      $stmt->execute([self::STATUS_SUPERSEDED, $studentAnswerId,
                      self::STATUS_PENDING, self::STATUS_REVIEW, self::STATUS_FAILED]);

      $stmt = $pdo->prepare("
        INSERT INTO answer_assessments
          (student_answer_id, requested_by, status, question_snapshot, criteria_snapshot, answer_snapshot)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$studentAnswerId, $userId, self::STATUS_PENDING, $question, $criteria, $answer]);
      $id = (int)$pdo->lastInsertId();
      $pdo->commit();
      return $id;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  /** Eén run, met de namen van de aanvrager en de goedkeurder. */
  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT aa.*, ur.name AS requested_by_name, ua.name AS approved_by_name
      FROM answer_assessments aa
      LEFT JOIN users ur ON aa.requested_by = ur.id
      LEFT JOIN users ua ON aa.approved_by = ua.id
      WHERE aa.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  /** Nieuwste run van een antwoord die niet superseded is, of null. */
  public static function latestByAnswer($studentAnswerId): ?array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT * FROM answer_assessments
      WHERE student_answer_id = ? AND status != ?
      ORDER BY id DESC
      LIMIT 1
    ");
    $stmt->execute([$studentAnswerId, self::STATUS_SUPERSEDED]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  /**
   * Nieuwste niet-superseded run per antwoord van een toetspoging,
   * als array student_answer_id => run (voor de antwoordenpagina).
   */
  public static function latestByStudentExam($studentExamId): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT aa.*
      FROM answer_assessments aa
      JOIN student_answers sa ON aa.student_answer_id = sa.id
      WHERE sa.student_exam_id = ? AND aa.status != ?
      ORDER BY aa.id ASC
    ");
    $stmt->execute([$studentExamId, self::STATUS_SUPERSEDED]);
    $runs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      // Oplopend op id: een nieuwere run overschrijft een oudere.
      $runs[(int)$row['student_answer_id']] = $row;
    }
    return $runs;
  }

  /** Alle runs van een antwoord, nieuwste eerst. */
  public static function historyByAnswer($studentAnswerId): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT id, status, final_score, human_review_needed, teacher_score, created_at, updated_at
      FROM answer_assessments
      WHERE student_answer_id = ?
      ORDER BY id DESC
    ");
    $stmt->execute([$studentAnswerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Zet de JSON-kolommen om naar arrays (null als ze leeg of ongeldig zijn).
   * Views werken alleen met gedecodeerde rijen.
   */
  public static function decode(array $row): array {
    foreach (self::JSON_COLUMNS as $column) {
      $value = $row[$column] ?? null;
      $decoded = (is_string($value) && $value !== '') ? json_decode($value, true) : null;
      $row[$column] = is_array($decoded) ? $decoded : null;
    }
    return $row;
  }

  private static function encode(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  }

  // ---------------------------------------------------------------------
  // Overgangen door de worker (alleen vanuit pending)
  // ---------------------------------------------------------------------

  /** pending → review, met het genormaliseerde resultaat (zie normalizeResult()). */
  public static function saveResult($id, array $result): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE answer_assessments
      SET status = ?, rubric = ?, evidence = ?, rounds = ?, decision = ?, run_log = ?,
          final_score = ?, human_review_needed = ?, error_message = NULL, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ?
    ");
    $stmt->execute([
      self::STATUS_REVIEW,
      self::encode($result['rubric']),
      self::encode($result['evidence']),
      self::encode($result['rounds']),
      self::encode($result['decision']),
      self::encode($result['run_log']),
      (int)$result['decision']['score'],
      $result['decision']['human_review_needed'] ? 1 : 0,
      $id,
      self::STATUS_PENDING,
    ]);
    return $stmt->rowCount() > 0;
  }

  /** pending → failed. */
  public static function markFailed($id, string $message): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE answer_assessments
      SET status = ?, error_message = ?, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ?
    ");
    $stmt->execute([self::STATUS_FAILED, $message, $id, self::STATUS_PENDING]);
    return $stmt->rowCount() > 0;
  }

  // ---------------------------------------------------------------------
  // Wachtrij voor de assessment-worker
  // ---------------------------------------------------------------------

  /** Runs waar de worker aan moet werken, oudste eerst. */
  public static function getPendingJobs(int $limit): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT id, question_snapshot, criteria_snapshot, answer_snapshot
      FROM answer_assessments
      WHERE status = ?
      ORDER BY id ASC
      LIMIT " . (int)$limit
    );
    $stmt->execute([self::STATUS_PENDING]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
