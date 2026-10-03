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
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
  // Normalisatie van de agent-uitvoer (spiegel van validate_*() in de worker)
  // Alleen bekende velden worden overgenomen, lijsten afgekapt op hun maximum.
  // null betekent: de uitvoer voldoet niet aan het contract.
  // ---------------------------------------------------------------------

  /** Alleen strings, getrimd en afgekapt op $max tekens; null bij een ander type. */
  public static function cleanText($v, int $max = self::MAX_TEXT): ?string {
    if (!is_string($v)) {
      return null;
    }
    $v = trim($v);
    if (!mb_check_encoding($v, 'UTF-8')) {
      $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
    }
    return mb_strlen($v, 'UTF-8') > $max ? mb_substr($v, 0, $max, 'UTF-8') : $v;
  }

  /** Lijst (array met volgnummers) of een lege array. */
  private static function listOf($v): array {
    return (is_array($v) && array_is_list($v)) ? $v : [];
  }

  /**
   * Normaliseert een lijst van objecten met een verplicht tekstveld $key en een
   * optioneel tekstveld 'why'. Items zonder (geldig) $key vallen weg.
   */
  private static function normalizeItems($items, string $key, int $max): array {
    $out = [];
    foreach (self::listOf($items) as $item) {
      if (!is_array($item)) {
        continue;
      }
      $text = self::cleanText($item[$key] ?? null);
      if ($text === null || $text === '') {
        continue;
      }
      $out[] = [$key => $text, 'why' => self::cleanText($item['why'] ?? null) ?? ''];
      if (count($out) >= $max) {
        break;
      }
    }
    return $out;
  }

  /** Rubric: 1–6 criteria, vier niveaus (10/5/1/0) en 0–5 alternatieve antwoorden. */
  public static function normalizeRubric($r): ?array {
    if (!is_array($r)) {
      return null;
    }
    $criteria = [];
    foreach (self::listOf($r['criteria'] ?? null) as $c) {
      if (!is_array($c)) {
        continue;
      }
      $name = self::cleanText($c['name'] ?? null);
      $description = self::cleanText($c['description'] ?? null);
      $weight = $c['weight'] ?? null;
      if ($name === null || $name === '' || $description === null || $description === ''
          || !in_array($weight, self::WEIGHTS, true)) {
        continue;
      }
      $criteria[] = [
        'name' => $name,
        'description' => $description,
        'weight' => $weight,
        'why' => self::cleanText($c['why'] ?? null) ?? '',
      ];
      if (count($criteria) >= self::MAX_CRITERIA) {
        break;
      }
    }
    if (!$criteria) {
      return null;
    }

    $rubric = ['criteria' => $criteria];
    foreach (['level_10', 'level_5', 'level_1', 'level_0'] as $level) {
      $text = self::cleanText($r[$level] ?? null);
      if ($text === null || $text === '') {
        return null;
      }
      $rubric[$level] = $text;
    }

    $alternatives = [];
    foreach (self::listOf($r['alternative_answers'] ?? null) as $alt) {
      $text = self::cleanText($alt);
      if ($text !== null && $text !== '') {
        $alternatives[] = $text;
      }
      if (count($alternatives) >= self::MAX_ALTERNATIVE_ANSWERS) {
        break;
      }
    }
    $rubric['alternative_answers'] = $alternatives;
    return $rubric;
  }

  /** Uitvoer van de Analysis Agent. */
  public static function normalizeAnalysis($a): ?array {
    if (!is_array($a)) {
      return null;
    }
    $summary = self::cleanText($a['summary'] ?? null);
    $questionClear = $a['question_clear'] ?? null;
    $answerMatches = $a['answer_matches_question'] ?? null;
    if ($summary === null || $summary === '' || !is_bool($questionClear) || !is_bool($answerMatches)) {
      return null;
    }
    $elements = self::normalizeItems($a['essential_elements'] ?? null, 'element', self::MAX_ESSENTIAL_ELEMENTS);
    if (!$elements) {
      return null;
    }
    return [
      'summary' => $summary,
      'question_clear' => $questionClear,
      'answer_matches_question' => $answerMatches,
      'essential_elements' => $elements,
      'issues' => self::normalizeItems($a['issues'] ?? null, 'issue', self::MAX_ISSUES),
      'clarifying_questions' => self::normalizeItems($a['clarifying_questions'] ?? null, 'question', self::MAX_CLARIFYING_QUESTIONS),
    ];
  }

  /** Uitvoer van de Assessment Agent: {rubric, explanation}. */
  public static function normalizeAssessment($a): ?array {
    if (!is_array($a)) {
      return null;
    }
    $rubric = self::normalizeRubric($a['rubric'] ?? null);
    if ($rubric === null) {
      return null;
    }
    return ['rubric' => $rubric, 'explanation' => self::cleanText($a['explanation'] ?? null) ?? ''];
  }

  /** Uitvoer van de Validation Agent: zes controles (elk één keer), wijzigingen en een verbeterde rubric. */
  public static function normalizeValidation($v): ?array {
    if (!is_array($v)) {
      return null;
    }
    $checks = [];
    foreach (self::listOf($v['checks'] ?? null) as $c) {
      if (!is_array($c)) {
        continue;
      }
      $name = $c['check'] ?? null;
      if (!in_array($name, self::CHECKS, true) || isset($checks[$name]) || !is_bool($c['ok'] ?? null)) {
        continue;
      }
      $checks[$name] = ['check' => $name, 'ok' => $c['ok'], 'comment' => self::cleanText($c['comment'] ?? null) ?? ''];
    }
    if (count($checks) !== count(self::CHECKS)) {
      return null;
    }
    $rubric = self::normalizeRubric($v['rubric'] ?? null);
    if ($rubric === null) {
      return null;
    }
    return [
      // Vaste volgorde, ongeacht de volgorde van het model
      'checks' => array_map(fn($name) => $checks[$name], self::CHECKS),
      'changes' => self::normalizeItems($v['changes'] ?? null, 'change', self::MAX_CHANGES),
      'rubric' => $rubric,
      'suggested_question_text' => self::cleanText($v['suggested_question_text'] ?? null) ?? '',
      'explanation' => self::cleanText($v['explanation'] ?? null) ?? '',
    ];
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
