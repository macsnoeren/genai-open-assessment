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
require_once __DIR__ . '/StudentAnswer.php';

/**
 * Agentic beoordelingen van studentantwoorden: de assessment-agents (Evidence,
 * Assessment, Validation) beoordelen een antwoord met de rubric van de vraag en
 * de orchestrator in de worker beslist of menselijke controle nodig is.
 *
 * Dit is een AI-beoordeling, net als ai_feedback: het resultaat komt nooit in de
 * docentbeoordeling (student_answers.teacher_score/teacher_feedback). De docent
 * beoordeelt altijd zelf en apart.
 *
 * Elke start is een nieuwe rij; een eerdere open of afgeronde run van hetzelfde
 * antwoord wordt superseded (net als bij een reset van de AI-resultaten, zie
 * StudentAnswer::resetAiResults()). De tabel is ook de wachtrij voor de
 * assessment-worker. Statusovergangen zijn atomair (WHERE id = ? AND status = ?),
 * zodat een verouderd resultaat van de worker nooit een nieuwere run raakt.
 */
class AnswerAssessment {

  // Statussen (geen CHECK in het schema; dit is de bron van waarheid)
  const STATUS_PENDING = 'pending';
  const STATUS_DONE = 'done';
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
  const JSON_COLUMNS = ['rubric', 'evidence', 'rounds', 'decision', 'run_log'];

  /** Naam van de agentic beoordeling als bron in de AI-statistieken, naast de modellen uit ai_feedback. */
  const AI_SOURCE = 'Agentic AI';

  /**
   * Kopjes waaraan de webapp rubric-criteria herkent. Een grove controle: de
   * worker parseert de criteria echt (parse_rubric_criteria) en stuurt anders
   * een fout terug, waarna het antwoord terugvalt op de gewone AI-beoordeling.
   */
  const RUBRIC_HEADINGS = ['Beoordelingscriteria:', 'Puntentoekenning:'];

  /** Statussen waarmee een antwoord bij agentic beoordelen hoort (niet bij process_ai_feedback). */
  const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_DONE];

  /** Nederlands label voor een status. */
  public static function statusLabel(string $status): string {
    switch ($status) {
      case self::STATUS_PENDING: return 'Wordt beoordeeld';
      case self::STATUS_DONE: return 'Beoordeeld door AI';
      case self::STATUS_FAILED: return 'Mislukt';
      case self::STATUS_SUPERSEDED: return 'Vervangen';
      default: return 'Onbekend';
    }
  }

  /** Bootstrap-klasse voor de statusbadge. */
  public static function statusClass(string $status): string {
    switch ($status) {
      case self::STATUS_DONE: return 'bg-info text-dark';
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

  /** Bootstrap-klasse voor een confidence-badge. */
  public static function confidenceClass(string $confidence): string {
    return ['hoog' => 'bg-success', 'middel' => 'bg-info text-dark', 'laag' => 'bg-danger'][$confidence] ?? 'bg-secondary';
  }

  /**
   * Normalisatie voor de citaatcontrole: kleine letters, witruimte samengevoegd,
   * aanhalingstekens gelijkgetrokken en leestekens aan de randen weg.
   * Spiegel van _normalize_quote() in bin/assessment_agents.py.
   */
  private static function normalizeQuote(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(['‘', '’', '‚', '‛', '`', '´'], "'", $text);
    $text = str_replace(['“', '”', '„', '‟', '«', '»'], '"', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text, " .,;:!?…'\"");
  }

  /**
   * True als het citaat (na normalisatie) letterlijk in het antwoord staat.
   * Een citaat korter dan 3 tekens telt als niet gevonden. Zelfde regel als
   * verify_quotes() in de worker, die de orchestrator-beslissing hierop baseert.
   */
  public static function quoteFound(string $answer, string $quote): bool {
    $quote = self::normalizeQuote($quote);
    if (mb_strlen($quote, 'UTF-8') < 3) {
      return false;
    }
    return mb_strpos(self::normalizeQuote($answer), $quote, 0, 'UTF-8') !== false;
  }

  // ---------------------------------------------------------------------
  // Aanmaken en lezen
  // ---------------------------------------------------------------------

  /**
   * Nieuwe run (status pending) met snapshots van vraag, criteria en antwoord.
   * $userId is null bij een automatisch gestarte run.
   * In dezelfde transactie worden de open en afgeronde runs van dit antwoord
   * (pending, done, failed) superseded.
   */
  public static function create(int $studentAnswerId, ?int $userId, string $question, string $criteria, string $answer): int {
    $pdo = Database::connect();
    $pdo->beginTransaction();
    try {
      self::supersedeActiveRuns($pdo, [$studentAnswerId]);

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

  /**
   * Zet de open en afgeronde runs (pending, done, failed) van deze antwoorden op
   * superseded. Draait op de meegegeven verbinding, zodat de aanroeper het in
   * een eigen transactie kan doen (create(), StudentAnswer::resetAiResults()).
   * Geeft het aantal vervangen runs terug.
   */
  public static function supersedeActiveRuns(PDO $pdo, array $studentAnswerIds): int {
    $ids = array_values(array_map('intval', $studentAnswerIds));
    if (!$ids) {
      return 0;
    }
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
      UPDATE answer_assessments
      SET status = ?, updated_at = CURRENT_TIMESTAMP
      WHERE student_answer_id IN ($placeholders) AND status IN (?, ?, ?)
    ");
    $stmt->execute(array_merge([self::STATUS_SUPERSEDED], $ids,
                               [self::STATUS_PENDING, self::STATUS_DONE, self::STATUS_FAILED]));
    return $stmt->rowCount();
  }

  /** Eén run, met de naam van de aanvrager. */
  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      SELECT aa.*, ur.name AS requested_by_name
      FROM answer_assessments aa
      LEFT JOIN users ur ON aa.requested_by = ur.id
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
      SELECT id, status, final_score, human_review_needed, created_at, updated_at
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

  /** pending → done, met het genormaliseerde resultaat (zie normalizeResult()). */
  public static function saveResult($id, array $result): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
      UPDATE answer_assessments
      SET status = ?, rubric = ?, evidence = ?, rounds = ?, decision = ?, run_log = ?,
          final_score = ?, human_review_needed = ?, error_message = NULL, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND status = ?
    ");
    $stmt->execute([
      self::STATUS_DONE,
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
  // Normalisatie van het resultaat van de worker (contract 8: spiegel van
  // validate_*() in bin/assessment_agents.py). Alleen bekende velden worden
  // overgenomen, tekst afgekapt en lijsten op hun maximum gekapt. Een
  // ontbrekend criterium, een dubbel nr of een waarde buiten een enum maakt
  // het onderdeel ongeldig: null.
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

  /** De waarde als die (strikt) in $allowed staat, anders null. */
  public static function enum($v, array $allowed): ?string {
    return (is_string($v) && in_array($v, $allowed, true)) ? $v : null;
  }

  /** Geheel getal (of een string met alleen cijfers), anders null. Booleans tellen niet. */
  private static function intValue($v): ?int {
    if (is_int($v)) {
      return $v;
    }
    if (is_string($v) && preg_match('/^\d{1,6}$/', trim($v))) {
      return (int)trim($v);
    }
    return null;
  }

  /** Lijst (array met volgnummers) of een lege array. */
  private static function listOf($v): array {
    return (is_array($v) && array_is_list($v)) ? $v : [];
  }

  /**
   * Zet een lijst criteria om in een map nr => item, op volgorde van nr.
   * Elk nummer 1..$count precies één keer; anders (ook bij een item dat geen
   * object is of een ongeldig nr heeft) null.
   */
  public static function criterionMap($items, int $count): ?array {
    if (!is_array($items) || !array_is_list($items) || $count < 1) {
      return null;
    }
    $map = [];
    foreach ($items as $item) {
      $nr = is_array($item) ? self::intValue($item['nr'] ?? null) : null;
      if ($nr === null || $nr < 1 || $nr > $count || isset($map[$nr])) {
        return null;
      }
      $map[$nr] = $item;
    }
    if (count($map) !== $count) {
      return null;
    }
    ksort($map);
    return $map;
  }

  /** Citaten: 0..MAX_QUOTES niet-lege strings van maximaal MAX_QUOTE tekens. */
  private static function quotes($v): array {
    $out = [];
    foreach (self::listOf($v) as $quote) {
      $text = self::cleanText($quote, self::MAX_QUOTE);
      if ($text !== null && $text !== '') {
        $out[] = $text;
      }
      if (count($out) >= self::MAX_QUOTES) {
        break;
      }
    }
    return $out;
  }

  private static function score($v): ?int {
    $score = self::intValue($v);
    return ($score !== null && in_array($score, self::SCORES, true)) ? $score : null;
  }

  /** Rubric zoals de worker die uit de criteria-snapshot parseerde: 1–10 criteria, vier niveaus, alternatieven. */
  public static function normalizeRubric($r): ?array {
    if (!is_array($r)) {
      return null;
    }
    $items = $r['criteria'] ?? null;
    if (!is_array($items) || count($items) > self::MAX_CRITERIA) {
      return null;
    }
    $map = self::criterionMap($items, count($items));
    if ($map === null) {
      return null;
    }
    $criteria = [];
    foreach ($map as $nr => $c) {
      $name = self::cleanText($c['name'] ?? null);
      $description = self::cleanText($c['description'] ?? null);
      $weight = self::enum($c['weight'] ?? null, self::WEIGHTS);
      if ($name === null || $name === '' || $description === null || $description === '' || $weight === null) {
        return null;
      }
      $criteria[] = ['nr' => $nr, 'name' => $name, 'weight' => $weight, 'description' => $description];
    }

    $levels = [];
    $rawLevels = is_array($r['levels'] ?? null) ? $r['levels'] : [];
    foreach (self::LEVELS as $level) {
      $text = self::cleanText($rawLevels[$level] ?? null);
      if ($text === null || $text === '') {
        return null;
      }
      $levels[$level] = $text;
    }

    $alternatives = [];
    foreach (self::listOf($r['alternatives'] ?? null) as $alt) {
      $text = self::cleanText($alt);
      if ($text !== null && $text !== '') {
        $alternatives[] = $text;
      }
      if (count($alternatives) >= self::MAX_ALTERNATIVES) {
        break;
      }
    }

    return [
      'model_answer' => self::cleanText($r['model_answer'] ?? null, self::MAX_MODEL_ANSWER) ?? '',
      'criteria' => $criteria,
      'levels' => $levels,
      'alternatives' => $alternatives,
    ];
  }

  /** Uitvoer van de Evidence Agent: per criterium citaten, interpretatie en wat ontbreekt. */
  public static function normalizeEvidence($e, int $count): ?array {
    $map = is_array($e) ? self::criterionMap($e['criteria'] ?? null, $count) : null;
    if ($map === null) {
      return null;
    }
    $criteria = [];
    foreach ($map as $nr => $c) {
      $found = self::enum($c['evidence_found'] ?? null, self::EVIDENCE_FOUND);
      $confidence = self::enum($c['confidence'] ?? null, self::CONFIDENCES);
      if ($found === null || $confidence === null) {
        return null;
      }
      $criteria[] = [
        'nr' => $nr,
        'evidence_found' => $found,
        'evidence' => self::quotes($c['evidence'] ?? null),
        'interpretation' => self::cleanText($c['interpretation'] ?? null) ?? '',
        'confidence' => $confidence,
        'missing_evidence' => self::cleanText($c['missing_evidence'] ?? null) ?? '',
      ];
    }
    return ['criteria' => $criteria, 'summary' => self::cleanText($e['summary'] ?? null) ?? ''];
  }

  /** Uitvoer van de Assessment Agent: status per criterium, score, confidence en feedback. */
  public static function normalizeAssessment($a, int $count): ?array {
    $map = is_array($a) ? self::criterionMap($a['criteria'] ?? null, $count) : null;
    if ($map === null) {
      return null;
    }
    $criteria = [];
    foreach ($map as $nr => $c) {
      $status = self::enum($c['status'] ?? null, self::STATUSES);
      $confidence = self::enum($c['confidence'] ?? null, self::CONFIDENCES);
      if ($status === null || $confidence === null) {
        return null;
      }
      $criteria[] = [
        'nr' => $nr,
        'status' => $status,
        'assessment' => self::cleanText($c['assessment'] ?? null) ?? '',
        'reasoning' => self::cleanText($c['reasoning'] ?? null) ?? '',
        'evidence_used' => self::quotes($c['evidence_used'] ?? null),
        'confidence' => $confidence,
      ];
    }
    $score = self::score($a['score'] ?? null);
    $confidence = self::enum($a['confidence'] ?? null, self::CONFIDENCES);
    if ($score === null || $confidence === null) {
      return null;
    }
    return [
      'criteria' => $criteria,
      'score' => $score,
      'confidence' => $confidence,
      'feedback' => self::cleanText($a['feedback'] ?? null) ?? '',
    ];
  }

  /** Uitvoer van de Validation Agent: zeven controles (elk één keer), issues, correcties en een eindoordeel. */
  public static function normalizeValidation($v, int $count): ?array {
    if (!is_array($v)) {
      return null;
    }
    $checks = [];
    foreach (self::listOf($v['checks'] ?? null) as $c) {
      $name = is_array($c) ? self::enum($c['check'] ?? null, self::CHECKS) : null;
      if ($name === null || isset($checks[$name]) || !is_bool($c['ok'] ?? null)) {
        continue;
      }
      $checks[$name] = ['check' => $name, 'ok' => $c['ok'], 'comment' => self::cleanText($c['comment'] ?? null) ?? ''];
    }
    if (count($checks) !== count(self::CHECKS) || !is_bool($v['validated'] ?? null)) {
      return null;
    }

    $issues = [];
    foreach (self::listOf($v['issues'] ?? null) as $item) {
      $nr = is_array($item) ? self::intValue($item['nr'] ?? null) : null;
      $text = is_array($item) ? self::cleanText($item['issue'] ?? null) : null;
      if ($nr === null || $nr > $count || $text === null || $text === '') {
        continue;
      }
      $issues[] = ['nr' => $nr, 'issue' => $text];
      if (count($issues) >= self::MAX_ISSUES) {
        break;
      }
    }

    $corrections = [];
    foreach (self::listOf($v['corrections'] ?? null) as $item) {
      $nr = is_array($item) ? self::intValue($item['nr'] ?? null) : null;
      $from = is_array($item) ? self::enum($item['from'] ?? null, self::STATUSES) : null;
      $to = is_array($item) ? self::enum($item['to'] ?? null, self::STATUSES) : null;
      if ($nr === null || $nr < 1 || $nr > $count || $from === null || $to === null) {
        continue;
      }
      $corrections[] = ['nr' => $nr, 'from' => $from, 'to' => $to, 'why' => self::cleanText($item['why'] ?? null) ?? ''];
      if (count($corrections) >= self::MAX_CORRECTIONS) {
        break;
      }
    }

    $final = $v['final_assessment'] ?? null;
    $map = is_array($final) ? self::criterionMap($final['criteria'] ?? null, $count) : null;
    $score = is_array($final) ? self::score($final['score'] ?? null) : null;
    $confidence = self::enum($v['confidence'] ?? null, self::CONFIDENCES);
    if ($map === null || $score === null || $confidence === null) {
      return null;
    }
    $finalCriteria = [];
    foreach ($map as $nr => $c) {
      $status = self::enum($c['status'] ?? null, self::STATUSES);
      if ($status === null) {
        return null;
      }
      $finalCriteria[] = ['nr' => $nr, 'status' => $status];
    }

    return [
      // Vaste volgorde, ongeacht de volgorde van het model
      'checks' => array_map(fn($name) => $checks[$name], self::CHECKS),
      'validated' => $v['validated'],
      'issues' => $issues,
      'corrections' => $corrections,
      'final_assessment' => ['criteria' => $finalCriteria, 'score' => $score],
      'confidence' => $confidence,
      'explanation' => self::cleanText($v['explanation'] ?? null) ?? '',
    ];
  }

  /**
   * Beslissing van de orchestrator (deterministisch berekend in de worker).
   * human_review_needed wordt true als het veld ontbreekt of geen boolean is.
   */
  public static function normalizeDecision($d, int $count): ?array {
    $map = is_array($d) ? self::criterionMap($d['criteria'] ?? null, $count) : null;
    if ($map === null) {
      return null;
    }
    $criteria = [];
    foreach ($map as $nr => $c) {
      $found = self::enum($c['evidence_found'] ?? null, self::EVIDENCE_FOUND);
      $assessmentStatus = self::enum($c['assessment_status'] ?? null, self::STATUSES);
      $finalStatus = self::enum($c['final_status'] ?? null, self::STATUSES);
      $agreement = self::enum($c['agreement'] ?? null, self::AGREEMENTS);
      $unverified = self::intValue($c['unverified_quotes'] ?? null);
      if ($found === null || $assessmentStatus === null || $finalStatus === null || $agreement === null
          || $unverified === null || $unverified > 2 * self::MAX_QUOTES) {
        return null;
      }
      $criteria[] = [
        'nr' => $nr,
        'evidence_found' => $found,
        'assessment_status' => $assessmentStatus,
        'final_status' => $finalStatus,
        'agreement' => $agreement,
        'unverified_quotes' => $unverified,
      ];
    }
    $score = self::score($d['score'] ?? null);
    $confidence = self::enum($d['confidence'] ?? null, self::CONFIDENCES);
    $extraRounds = self::intValue($d['extra_rounds'] ?? null);
    if ($score === null || $confidence === null || $extraRounds === null || $extraRounds > self::MAX_ROUNDS - 1) {
      return null;
    }
    $reasons = [];
    foreach (self::listOf($d['reasons'] ?? null) as $reason) {
      $text = self::cleanText($reason);
      if ($text !== null && $text !== '') {
        $reasons[] = $text;
      }
      if (count($reasons) >= self::MAX_REASONS) {
        break;
      }
    }
    $humanReview = $d['human_review_needed'] ?? null;
    return [
      'criteria' => $criteria,
      'score' => $score,
      'score_capped' => ($d['score_capped'] ?? false) === true,
      'confidence' => $confidence,
      // Veilige default: bij twijfel over het veld kijkt de docent extra goed
      'human_review_needed' => is_bool($humanReview) ? $humanReview : true,
      'reasons' => $reasons,
      'extra_rounds' => $extraRounds,
    ];
  }

  /** Run-gegevens (alleen ter informatie): modellen, tijdsduren, injection-vermoeden en tijdstippen. */
  public static function normalizeRunLog($l): array {
    $l = is_array($l) ? $l : [];
    $duration = fn($v) => (is_int($v) || is_float($v)) && $v >= 0 ? round((float)$v, 1) : null;

    $models = is_array($l['models'] ?? null) ? $l['models'] : [];
    $durations = is_array($l['durations'] ?? null) ? $l['durations'] : [];
    $rounds = [];
    foreach (array_slice(self::listOf($durations['rounds'] ?? null), 0, self::MAX_ROUNDS) as $round) {
      $round = is_array($round) ? $round : [];
      $rounds[] = ['assessment' => $duration($round['assessment'] ?? null), 'validation' => $duration($round['validation'] ?? null)];
    }
    return [
      'models' => [
        'evidence' => self::cleanText($models['evidence'] ?? null, self::MAX_SHORT) ?? '',
        'assessment' => self::cleanText($models['assessment'] ?? null, self::MAX_SHORT) ?? '',
        'validation' => self::cleanText($models['validation'] ?? null, self::MAX_SHORT) ?? '',
      ],
      'durations' => ['evidence' => $duration($durations['evidence'] ?? null), 'rounds' => $rounds],
      'injection_suspected' => ($l['injection_suspected'] ?? false) === true,
      'started_at' => self::cleanText($l['started_at'] ?? null, self::MAX_SHORT) ?? '',
      'finished_at' => self::cleanText($l['finished_at'] ?? null, self::MAX_SHORT) ?? '',
    ];
  }

  /**
   * Het volledige resultaat van submit_assessment_result. De rubric bepaalt
   * het aantal criteria voor de rest. Bij een ongeldig onderdeel: null, en
   * $error noemt welk onderdeel (voor de 400-melding).
   */
  public static function normalizeResult($r, ?string &$error = null): ?array {
    $error = null;
    if (!is_array($r)) {
      $error = 'Invalid result';
      return null;
    }
    $rubric = self::normalizeRubric($r['rubric'] ?? null);
    if ($rubric === null) {
      $error = 'Invalid rubric';
      return null;
    }
    $count = count($rubric['criteria']);

    $evidence = self::normalizeEvidence($r['evidence'] ?? null, $count);
    if ($evidence === null) {
      $error = 'Invalid evidence';
      return null;
    }

    $rawRounds = $r['rounds'] ?? null;
    if (!is_array($rawRounds) || !array_is_list($rawRounds) || count($rawRounds) < 1 || count($rawRounds) > self::MAX_ROUNDS) {
      $error = 'Invalid rounds (expected 1 to ' . self::MAX_ROUNDS . ')';
      return null;
    }
    $rounds = [];
    foreach ($rawRounds as $i => $round) {
      $assessment = is_array($round) ? self::normalizeAssessment($round['assessment'] ?? null, $count) : null;
      $validation = is_array($round) ? self::normalizeValidation($round['validation'] ?? null, $count) : null;
      if ($assessment === null || $validation === null) {
        $error = 'Invalid ' . ($assessment === null ? 'assessment' : 'validation') . ' in round ' . ($i + 1);
        return null;
      }
      $rounds[] = ['assessment' => $assessment, 'validation' => $validation];
    }

    $decision = self::normalizeDecision($r['decision'] ?? null, $count);
    if ($decision === null || $decision['extra_rounds'] !== count($rounds) - 1) {
      $error = 'Invalid decision';
      return null;
    }

    return [
      'rubric' => $rubric,
      'evidence' => $evidence,
      'rounds' => $rounds,
      'decision' => $decision,
      'run_log' => self::normalizeRunLog($r['run_log'] ?? null),
    ];
  }

  // ---------------------------------------------------------------------
  // De agentic beoordeling als AI-bron (naast ai_feedback)
  // ---------------------------------------------------------------------

  /**
   * SQL-fragment (voor een SELECT met student_answers als $sa) dat de score van
   * de actuele agentic beoordeling oplevert, of NULL. Zie aiScores().
   */
  public static function agenticScoreSql(string $sa = 'sa'): string {
    return "(SELECT aa.final_score FROM answer_assessments aa
             WHERE aa.student_answer_id = $sa.id AND aa.status = '" . self::STATUS_DONE . "'
             ORDER BY aa.id DESC LIMIT 1)";
  }

  /**
   * Wat de student van een agentic beoordeling ziet: alleen de score en de
   * feedback van de AI (niet de citaten, redeneringen en controles), of null
   * als de run (nog) niet klaar is.
   */
  public static function studentSummary(?array $run): ?array {
    if (!$run || $run['status'] !== self::STATUS_DONE) {
      return null;
    }
    $run = self::decode($run);
    $rounds = $run['rounds'] ?? [];
    $last = $rounds ? $rounds[count($rounds) - 1] : null;
    return [
      'score' => (int)$run['final_score'],
      'feedback' => (string)($last['assessment']['feedback'] ?? ''),
    ];
  }

  // ---------------------------------------------------------------------
  // Automatisch starten en de verdeling met process_ai_feedback
  // ---------------------------------------------------------------------

  /** True als de criteria de rubric-kopjes bevatten (zelfde regel als rubricSql()). */
  public static function looksLikeRubric(?string $criteria): bool {
    foreach (self::RUBRIC_HEADINGS as $heading) {
      if (stripos((string)$criteria, $heading) === false) {
        return false;
      }
    }
    return true;
  }

  /** SQL-voorwaarde voor looksLikeRubric() op een kolom, met de bijbehorende parameters. */
  private static function rubricSql(string $column): array {
    $parts = array_fill(0, count(self::RUBRIC_HEADINGS), "$column LIKE ?");
    $params = array_map(fn($heading) => '%' . $heading . '%', self::RUBRIC_HEADINGS);
    return ['(' . implode(' AND ', $parts) . ')', $params];
  }

  /**
   * SQL-voorwaarden die bepalen welke antwoorden NIET naar process_ai_feedback
   * gaan (te combineren met de wachtrij-voorwaarden van getPendingAiGrading()):
   * - een antwoord met een actieve agentic run (pending of done);
   * - met AGENTIC_AUTO_ASSESSMENT: een niet-leeg antwoord op een vraag met
   *   rubric-kopjes zonder run die niet vervangen is (dus ook na een reset van
   *   de AI-resultaten); dat wordt automatisch agentic gestart.
   * Een antwoord waarvan de laatste run mislukte, gaat dus wel naar de gewone
   * AI-beoordeling (vangnet).
   *
   * @return array [sql, params]; sql begint met " AND"
   */
  public static function excludeFromAiGradingSql(string $sa = 'sa', string $q = 'q'): array {
    $placeholders = implode(', ', array_fill(0, count(self::ACTIVE_STATUSES), '?'));
    $sql = " AND NOT EXISTS (SELECT 1 FROM answer_assessments aa
                             WHERE aa.student_answer_id = $sa.id AND aa.status IN ($placeholders))";
    $params = self::ACTIVE_STATUSES;
    if (AGENTIC_AUTO_ASSESSMENT) {
      [$autoSql, $autoParams] = self::autoEligibleSql($sa, $q);
      $sql .= " AND NOT ($autoSql)";
      $params = array_merge($params, $autoParams);
    }
    return [$sql, $params];
  }

  /**
   * Voorwaarde "wordt automatisch agentic beoordeeld" voor een antwoord dat
   * al in de AI-wachtrij zou staan (ingeleverd, AI-beoordeling aan, geen
   * ai_feedback): niet leeg, rubric-kopjes in de criteria en nog geen run die
   * niet vervangen is. Superseded runs tellen niet mee: na een reset van de
   * AI-resultaten (StudentAnswer::resetAiResults()) zijn alle runs vervangen en
   * moet het antwoord weer automatisch agentic starten. Buiten een reset wordt
   * een run alleen superseded samen met een nieuwere run (create()).
   */
  private static function autoEligibleSql(string $sa, string $q): array {
    [$rubricSql, $params] = self::rubricSql("$q.criteria");
    $sql = "TRIM(COALESCE($sa.answer, ''), ' ' || char(9) || char(10) || char(13)) != ''
            AND $rubricSql
            AND NOT EXISTS (SELECT 1 FROM answer_assessments aa2
                            WHERE aa2.student_answer_id = $sa.id AND aa2.status != ?)";
    $params[] = self::STATUS_SUPERSEDED;
    return [$sql, $params];
  }

  /**
   * Start agentic runs (zonder aanvrager) voor de antwoorden die automatisch
   * agentic beoordeeld worden, optioneel alleen binnen één toetspoging.
   * Geeft [run-id => student_answer_id] terug; leeg als AGENTIC_AUTO_ASSESSMENT uit staat.
   */
  public static function createAutomaticRuns(?int $studentExamId, int $limit): array {
    if (!AGENTIC_AUTO_ASSESSMENT) {
      return [];
    }
    [$autoSql, $params] = self::autoEligibleSql('sa', 'q');
    $sql = "
      SELECT sa.id, sa.answer, q.question_text, q.criteria
      FROM student_answers sa
      JOIN student_exams se ON sa.student_exam_id = se.id
      JOIN exams e ON se.exam_id = e.id
      JOIN questions q ON sa.question_id = q.id
      WHERE se.completed_at IS NOT NULL
        AND e.ai_grading_enabled = 1
        AND (sa.ai_feedback IS NULL OR sa.ai_feedback = '')
        AND $autoSql";
    if ($studentExamId !== null) {
      $sql .= " AND sa.student_exam_id = ?";
      $params[] = $studentExamId;
    }
    $sql .= " ORDER BY sa.id ASC LIMIT " . (int)$limit;

    $pdo = Database::connect();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $created = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $id = self::create((int)$row['id'], null, (string)$row['question_text'], (string)$row['criteria'], (string)$row['answer']);
      $created[$id] = (int)$row['id'];
    }
    return $created;
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
