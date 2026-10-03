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

/**
 * AI-vraagontwerpen: een vraag + gewenst antwoord die de ontwerp-agents
 * (Analysis, Assessment, Validation) uitwerken tot een rubric.
 *
 * De tabel is ook de wachtrij voor de ontwerp-worker. Statusovergangen zijn
 * atomair (WHERE id = ? AND status = ? AND revision = ?), zodat een verouderd
 * resultaat van de worker nooit nieuwere invoer van de docent overschrijft.
 */
class QuestionDesign {

  // Statussen (geen CHECK in het schema; dit is de bron van waarheid)
  const STATUS_ANALYSIS_PENDING = 'analysis_pending';
  const STATUS_AWAITING_ANSWERS = 'awaiting_answers';
  const STATUS_ASSESSMENT_PENDING = 'assessment_pending';
  const STATUS_REVIEW = 'review';
  const STATUS_APPROVED = 'approved';
  const STATUS_FAILED = 'failed';

  // Contractlimieten: gelijk houden met bin/design_agents.py
  const MAX_TEXT = 800;
  const MAX_ESSENTIAL_ELEMENTS = 8;
  const MAX_ISSUES = 8;
  const MAX_CLARIFYING_QUESTIONS = 5;
  const MAX_CRITERIA = 6;
  const MAX_ALTERNATIVE_ANSWERS = 5;
  const MAX_CHANGES = 8;
  const WEIGHTS = ['essentieel', 'aanvullend'];
  const CHECKS = ['coverage', 'clarity_independence', 'alternatives', 'not_too_literal', 'levels', 'consistency'];

  /** JSON-kolommen die decode() omzet naar arrays. */
  const JSON_COLUMNS = ['analysis', 'teacher_answers', 'assessment', 'validation'];

  /** Nederlands label voor een status. */
  public static function statusLabel(string $status): string {
    switch ($status) {
      case self::STATUS_ANALYSIS_PENDING: return 'Analyse loopt';
      case self::STATUS_AWAITING_ANSWERS: return 'Wacht op jouw antwoorden';
      case self::STATUS_ASSESSMENT_PENDING: return 'Voorstel wordt gemaakt';
      case self::STATUS_REVIEW: return 'Klaar voor beoordeling';
      case self::STATUS_APPROVED: return 'Goedgekeurd';
      case self::STATUS_FAILED: return 'Mislukt';
      default: return 'Onbekend';
    }
  }

  /** True als de worker nog aan dit ontwerp moet werken. */
  public static function isPending(string $status): bool {
    return $status === self::STATUS_ANALYSIS_PENDING || $status === self::STATUS_ASSESSMENT_PENDING;
  }

  public static function create($examId, $docentId, $questionText, $modelAnswer): int {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      INSERT INTO question_designs (exam_id, docent_id, question_text, model_answer, status, revision)
      VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$examId, $docentId, $questionText, $modelAnswer, self::STATUS_ANALYSIS_PENDING]);
    return (int)$pdo->lastInsertId();
  }

  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM question_designs WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  /** Alle ontwerpen van een toets, nieuwste eerst. */
  public static function allByExam($examId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM question_designs WHERE exam_id = ? ORDER BY created_at DESC, id DESC");
    $stmt->execute([$examId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function delete($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("DELETE FROM question_designs WHERE id = ?");
    $stmt->execute([$id]);
  }

  /**
   * Zet de JSON-kolommen om naar arrays (null als ze leeg of ongeldig zijn).
   * Views en de API werken alleen met gedecodeerde rijen.
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
    return json_encode($value, JSON_UNESCAPED_UNICODE);
  }

  // ---------------------------------------------------------------------
  // Overgangen door de worker (revision blijft gelijk)
  // ---------------------------------------------------------------------

  /** analysis_pending → awaiting_answers (met vragen) of assessment_pending (zonder vragen). */
  public static function saveAnalysis($id, $rev, array $analysis): bool {
    $next = empty($analysis['clarifying_questions']) ? self::STATUS_ASSESSMENT_PENDING : self::STATUS_AWAITING_ANSWERS;
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE question_designs
      SET analysis = ?, teacher_answers = NULL, status = ?, error_message = NULL, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ? AND revision = ?
    ");
    $stmt->execute([self::encode($analysis), $next, $id, self::STATUS_ANALYSIS_PENDING, $rev]);
    return $stmt->rowCount() > 0;
  }

  /** assessment_pending → review. */
  public static function saveAssessment($id, $rev, array $assessment, array $validation): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE question_designs
      SET assessment = ?, validation = ?, status = ?, error_message = NULL, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ? AND revision = ?
    ");
    $stmt->execute([self::encode($assessment), self::encode($validation), self::STATUS_REVIEW,
                    $id, self::STATUS_ASSESSMENT_PENDING, $rev]);
    return $stmt->rowCount() > 0;
  }

  /** analysis_pending of assessment_pending → failed. */
  public static function markFailed($id, $rev, $currentStatus, $message): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE question_designs
      SET status = ?, error_message = ?, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ? AND revision = ?
    ");
    $stmt->execute([self::STATUS_FAILED, $message, $id, $currentStatus, $rev]);
    return $stmt->rowCount() > 0;
  }

  // ---------------------------------------------------------------------
  // Overgangen door de docent (revision gaat omhoog)
  // ---------------------------------------------------------------------

  /** awaiting_answers → assessment_pending. */
  public static function saveTeacherAnswers($id, $rev, array $answers): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE question_designs
      SET teacher_answers = ?, status = ?, revision = revision + 1, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ? AND revision = ?
    ");
    $stmt->execute([self::encode($answers), self::STATUS_ASSESSMENT_PENDING, $id, self::STATUS_AWAITING_ANSWERS, $rev]);
    return $stmt->rowCount() > 0;
  }

  /**
   * review → assessment_pending. De validatie blijft staan: de worker geeft
   * de verbeterde rubric daaruit mee als previous_rubric.
   */
  public static function requestRevision($id, $rev, $feedback): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE question_designs
      SET teacher_feedback = ?, status = ?, revision = revision + 1, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ? AND revision = ?
    ");
    $stmt->execute([$feedback, self::STATUS_ASSESSMENT_PENDING, $id, self::STATUS_REVIEW, $rev]);
    return $stmt->rowCount() > 0;
  }

  /** failed → analysis_pending (nog geen analyse) of assessment_pending (analyse aanwezig). */
  public static function retry($id, $rev): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE question_designs
      SET status = CASE WHEN analysis IS NULL OR analysis = '' THEN ? ELSE ? END,
          error_message = NULL, revision = revision + 1, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ? AND revision = ?
    ");
    $stmt->execute([self::STATUS_ANALYSIS_PENDING, self::STATUS_ASSESSMENT_PENDING, $id, self::STATUS_FAILED, $rev]);
    return $stmt->rowCount() > 0;
  }

  // ---------------------------------------------------------------------
  // Wachtrij voor de ontwerp-worker
  // ---------------------------------------------------------------------

  /** Ontwerpen waar de worker aan moet werken, oudste eerst. */
  public static function getPendingJobs(int $limit) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT * FROM question_designs
      WHERE status IN (?, ?)
      ORDER BY updated_at ASC, id ASC
      LIMIT " . (int)$limit
    );
    $stmt->execute([self::STATUS_ANALYSIS_PENDING, self::STATUS_ASSESSMENT_PENDING]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
