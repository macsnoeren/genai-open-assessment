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
require_once __DIR__ . '/StudentAnswer.php';
require_once __DIR__ . '/Questions.php';
require_once __DIR__ . '/AnswerAssessment.php';
require_once __DIR__ . '/Integration.php';

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

  const FILTERS = ['open', 'needs_review', 'all'];
  const LIST_CANDIDATES = 500;   // de lijst wordt in PHP gefilterd; zie ARCHITECTURE.md §9

  /** Rangorde van de confidence: hoog > middel > laag. */
  const CONFIDENCE_RANK = ['laag' => 1, 'middel' => 2, 'hoog' => 3];

  private const SELECT = "
      SELECT ia.*, se.exam_id, se.guest_name, se.access_token, se.started_at, se.completed_at,
             e.title AS exam_title, i.name AS integration_name, i.min_confidence, i.webhook_url,
             i.return_origin
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

  // ---------------------------------------------------------------------
  // Status, confidence en review_needed (deterministisch, zie B6 en B7 in
  // ARCHITECTURE.md §6.9 en docs/integration-api.md)
  // ---------------------------------------------------------------------

  private static function below(string $confidence, string $threshold): bool {
    return (self::CONFIDENCE_RANK[$confidence] ?? 1) < (self::CONFIDENCE_RANK[$threshold] ?? 3);
  }

  private static function lowest(array $confidences): ?string {
    $lowest = null;
    foreach ($confidences as $confidence) {
      if ($lowest === null || self::CONFIDENCE_RANK[$confidence] < self::CONFIDENCE_RANK[$lowest]) {
        $lowest = $confidence;
      }
    }
    return $lowest;
  }

  /**
   * Het AI-resultaat van één antwoord in de vorm van contract 9 (veld "ai"),
   * of null als er nog geen AI-resultaat is. Een afgeronde agentic run gaat
   * voor; anders telt ai_feedback (ook zonder score: de worker gaf op).
   * @param array $answer rij uit student_answers
   * @param array|null $run nieuwste niet-superseded agentic run van het antwoord
   */
  public static function answerResult(array $answer, ?array $run, string $minConfidence): ?array {
    if ($run && $run['status'] === AnswerAssessment::STATUS_DONE) {
      $decoded = AnswerAssessment::decode($run);
      $decision = $decoded['decision'] ?? [];
      $confidence = in_array($decision['confidence'] ?? null, Integration::CONFIDENCES, true) ? $decision['confidence'] : 'laag';
      $humanReview = !empty($run['human_review_needed']);
      $reasons = [];
      if ($humanReview) {
        $reasons = $decision['reasons'] ?? [];
        if (!$reasons) {
          $reasons[] = 'AI vraagt menselijke controle';
        }
      }
      $belowThreshold = self::below($confidence, $minConfidence);
      if ($belowThreshold) {
        $reasons[] = 'Confidence ' . $confidence . ' ligt onder de drempel ' . $minConfidence;
      }

      $statuses = [];
      foreach ($decision['criteria'] ?? [] as $c) {
        $statuses[(int)$c['nr']] = $c['final_status'];
      }
      $criteria = [];
      foreach ($decoded['rubric']['criteria'] ?? [] as $c) {
        $criteria[] = [
          'name' => $c['name'],
          'weight' => $c['weight'],
          'status' => $statuses[(int)$c['nr']] ?? null,
        ];
      }
      $student = AnswerAssessment::studentSummary($run);
      return [
        'source' => 'agentic',
        'score' => (int)$run['final_score'],
        'feedback' => $student['feedback'] ?? '',
        'confidence' => $confidence,
        'review_needed' => $humanReview || $belowThreshold,
        'reasons' => array_values(array_unique($reasons)),
        'criteria' => $criteria,
      ];
    }

    $aiFeedback = $answer['ai_feedback'] ?? null;
    if ($aiFeedback === null || trim($aiFeedback) === '') {
      return null;
    }

    $modelScores = StudentAnswer::aiScores($aiFeedback);
    $confidences = [];
    $reasons = [];
    $alwaysReview = false;
    if (!$modelScores) {
      $confidences[] = 'laag';
      $reasons[] = 'Geen AI-score';
      $alwaysReview = true;
    }
    if (StudentAnswer::hasInjectionWarning($aiFeedback)) {
      $confidences[] = 'laag';
      $reasons[] = 'Mogelijke instructies aan de AI';
      $alwaysReview = true;
    }
    if (count($modelScores) === 1) {
      $confidences[] = 'middel';
      $reasons[] = 'Slechts één model';
    } elseif (count($modelScores) >= 2) {
      $min = min($modelScores);
      $max = max($modelScores);
      if ($min === $max) {
        $confidences[] = 'hoog';
      } elseif ($min <= 1 && $max >= 5) {
        $confidences[] = 'laag';
        $reasons[] = 'Modellen zijn het oneens';
        $alwaysReview = true;
      } else {
        $confidences[] = 'middel';
        $reasons[] = 'Kleine verschillen tussen modellen';
      }
    }
    $confidence = self::lowest($confidences);

    return [
      'source' => 'models',
      'score' => $modelScores ? round(array_sum($modelScores) / count($modelScores), 1) : null,
      'model_scores' => (object)$modelScores,
      'feedback' => $aiFeedback,
      'confidence' => $confidence,
      'review_needed' => $alwaysReview || self::below($confidence, $minConfidence),
      'reasons' => $reasons,
    ];
  }

  /**
   * De volledige samenvatting van een poging (contract 9). De status wordt
   * afgeleid uit completed_at, de AI-resultaten, teacher_score en reviewed_at.
   */
  public static function summary(array $attempt): array {
    $studentExamId = (int)$attempt['student_exam_id'];
    $answersByQuestion = [];
    foreach (StudentAnswer::allByStudentExam($studentExamId) as $answer) {
      $answersByQuestion[(int)$answer['question_id']] = $answer;
    }
    $runs = AnswerAssessment::latestByStudentExam($studentExamId);
    $minConfidence = (string)$attempt['min_confidence'];

    $answers = [];
    $aiScores = [];
    $teacherScores = [];
    $confidences = [];
    $reasons = [];
    $reviewNeeded = false;
    $allAi = true;
    $allTeacher = true;
    $times = [$attempt['updated_at'] ?? null, $attempt['completed_at'] ?? null];

    foreach (Question::allByExam($attempt['exam_id']) as $index => $question) {
      $answer = $answersByQuestion[(int)$question['id']] ?? null;
      if ($answer === null) {
        continue; // vraag toegevoegd na de poging: hoort er niet bij
      }
      $nr = $index + 1;
      $run = $runs[(int)$answer['id']] ?? null;
      $ai = self::answerResult($answer, $run, $minConfidence);
      $teacher = null;
      if ($answer['teacher_score'] !== null && $answer['teacher_score'] !== '') {
        $teacher = ['score' => (int)$answer['teacher_score'], 'feedback' => (string)($answer['teacher_feedback'] ?? '')];
        $teacherScores[] = (int)$answer['teacher_score'];
      } else {
        $allTeacher = false;
      }
      if ($ai === null) {
        $allAi = false;
      } else {
        if ($ai['score'] !== null) {
          $aiScores[] = $ai['score'];
        }
        $confidences[] = $ai['confidence'];
        $reviewNeeded = $reviewNeeded || $ai['review_needed'];
        foreach ($ai['reasons'] as $reason) {
          $reasons[] = 'Vraag ' . $nr . ': ' . $reason;
        }
      }
      $times[] = $answer['ai_updated_at'] ?? null;
      $times[] = $answer['updated_at'] ?? null;
      $times[] = $run['updated_at'] ?? null;

      $answers[] = [
        'question_id' => (int)$question['id'],
        'nr' => $nr,
        'question_text' => (string)$question['question_text'],
        'answer' => (string)($answer['answer'] ?? ''),
        'ai' => $ai,
        'teacher' => $teacher,
      ];
    }

    if (empty($attempt['completed_at'])) {
      $status = empty($attempt['launch_used_at']) ? self::STATUS_NOT_STARTED : self::STATUS_IN_PROGRESS;
    } elseif (!empty($attempt['reviewed_at']) || ($answers && $allTeacher)) {
      $status = self::STATUS_REVIEWED;
    } elseif ($allAi) {
      $status = self::STATUS_GRADED;
    } else {
      $status = self::STATUS_GRADING;
    }
    $isGraded = in_array($status, [self::STATUS_GRADED, self::STATUS_REVIEWED], true);
    $times = array_filter($times);

    return [
      'attempt_id' => $studentExamId,
      'external_ref' => (string)$attempt['external_ref'],
      'exam_id' => (int)$attempt['exam_id'],
      'exam_title' => (string)$attempt['exam_title'],
      'display_name' => (string)($attempt['guest_name'] ?? ''),
      'status' => $status,
      // Alleen bij graded: na reviewed heeft een mens al gekeken.
      'review_needed' => $status === self::STATUS_GRADED && $reviewNeeded,
      'confidence' => $isGraded ? self::lowest($confidences) : null,
      'reasons' => $isGraded ? $reasons : [],
      'started_at' => self::isoTime($attempt['launch_used_at'] ?? null),
      'submitted_at' => self::isoTime($attempt['completed_at'] ?? null),
      'reviewed_at' => self::isoTime($attempt['reviewed_at'] ?? null),
      'updated_at' => self::isoTime($times ? max($times) : null),
      'ai_score' => $isGraded && $aiScores ? round(array_sum($aiScores) / count($aiScores), 1) : null,
      'teacher_score' => $teacherScores ? round(array_sum($teacherScores) / count($teacherScores), 1) : null,
      'answers' => $answers,
    ];
  }

  /**
   * Pogingen van een koppeling voor GET integration_attempts. Kandidaten komen
   * uit de database (nieuwste eerst, maximaal LIST_CANDIDATES); de status wordt
   * per poging berekend en daarna gefilterd en afgesneden.
   * - open: niet reviewed, en niet graded zonder review_needed
   * - needs_review: graded met review_needed
   * - all: alles
   */
  public static function listForIntegration($integrationId, string $filter, int $limit): array {
    $sql = self::SELECT . " WHERE ia.integration_id = ?";
    if ($filter !== 'all') {
      $sql .= " AND ia.reviewed_at IS NULL";
    }
    $sql .= " ORDER BY ia.student_exam_id DESC LIMIT " . (int)self::LIST_CANDIDATES;
    $pdo = Database::connect();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$integrationId]);

    $list = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $attempt) {
      $summary = self::summary($attempt);
      $graded = $summary['status'] === self::STATUS_GRADED;
      $keep = match ($filter) {
        'open' => $summary['status'] !== self::STATUS_REVIEWED && !($graded && !$summary['review_needed']),
        'needs_review' => $graded && $summary['review_needed'],
        default => true,
      };
      if (!$keep) {
        continue;
      }
      $list[] = [
        'attempt_id' => $summary['attempt_id'],
        'external_ref' => $summary['external_ref'],
        'exam_id' => $summary['exam_id'],
        'status' => $summary['status'],
        'review_needed' => $summary['review_needed'],
        'updated_at' => $summary['updated_at'],
      ];
      if (count($list) >= $limit) {
        break;
      }
    }
    return $list;
  }
}
