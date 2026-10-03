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
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/StudentAnswer.php';
require_once __DIR__ . '/../models/StudentExam.php';
require_once __DIR__ . '/../models/AnswerAssessment.php';

/**
 * Class AnswerAssessmentController
 * Agentic beoordelen: de docent laat een studentantwoord door de
 * assessment-agents beoordelen met de rubric van de vraag, bekijkt bewijs,
 * interpretatie en oordelen per criterium, en past aan en keurt goed.
 * Pas bij goedkeuring ontstaat een docentscore; de AI beslist niets definitief.
 *
 * Alleen de rol docent (en admin) met leestoegang tot de toets. De beoordelaar
 * ziet niets van het agentic resultaat (blinde beoordeling).
 */
class AnswerAssessmentController {

  /** Maximale lengte (bytes) van de docentfeedback bij goedkeuren. */
  private const MAX_FEEDBACK_LENGTH = 10000;

  /**
   * Starts an agentic assessment run for one answer. A previous open or
   * finished run of the same answer is superseded.
   */
  public function start() {
    validateCsrfToken();
    requireRole('docent');

    $answer = $this->loadAnswerForAssessment(requestInt($_POST, 'student_answer_id'));
    $answersUrl = $this->answersUrl($answer);

    $reason = $this->startableReason($answer);
    if ($reason !== null) {
        $this->redirectWithError($answersUrl, $reason);
    }
    if ($this->remainingStarts() < 1) {
        $this->redirectWithError($answersUrl, $this->rateLimitMessage());
    }

    $id = $this->createRun($answer);

    header('Location: /?action=answer_assessment_view&id=' . $id);
    exit;
  }

  /**
   * Starts agentic assessment runs for all answers of one exam attempt.
   * Answers that cannot be started, or that already have a run, are skipped.
   */
  public function startExam() {
    validateCsrfToken();
    requireRole('docent');

    $studentExamId = requestInt($_POST, 'student_exam_id');
    $studentExam = $studentExamId !== null ? StudentExam::find($studentExamId) : null;
    if (!$studentExam) {
        abort(404, 'Toetspoging niet gevonden.');
    }
    // Toegang via de toets uit de database
    $this->checkExamReadAccess((int)$studentExam['exam_id']);
    $answersUrl = '/?action=view_student_answers&student_exam_id=' . (int)$studentExam['id'];

    $existing = AnswerAssessment::latestByStudentExam($studentExam['id']);
    $remaining = $this->remainingStarts();
    $started = 0;
    $skipped = 0;
    $rateLimited = 0;
    foreach (StudentAnswer::allByStudentExam($studentExam['id']) as $row) {
        $answer = StudentAnswer::findForAssessment($row['id']);
        // Bij een bulkstart blijft een bestaande run staan (alleen een mislukte wordt opnieuw gestart);
        // opnieuw beoordelen gaat per antwoord via de pagina van de run.
        $run = $existing[(int)$row['id']] ?? null;
        if (!$answer || $this->startableReason($answer) !== null
            || ($run && $run['status'] !== AnswerAssessment::STATUS_FAILED)) {
            $skipped++;
            continue;
        }
        if ($started >= $remaining) {
            $rateLimited++;
            continue;
        }
        $this->createRun($answer);
        $started++;
    }

    $message = $started . ' ' . ($started === 1 ? 'antwoord' : 'antwoorden') . ' gestart voor agentic beoordeling, '
        . $skipped . ' overgeslagen (al beoordeeld of niet te beoordelen).';
    if ($rateLimited > 0) {
        $_SESSION['error'] = $rateLimited . ' ' . ($rateLimited === 1 ? 'antwoord' : 'antwoorden')
            . ' niet gestart: ' . $this->rateLimitMessage();
    }
    $_SESSION['success_message'] = $message;
    header('Location: ' . $answersUrl);
    exit;
  }

  /**
   * Shows one run: question, answer, and everything the agents and the orchestrator produced.
   */
  public function view() {
    requireRole('docent');

    [$run, $answer] = $this->loadRun(requestInt($_GET, 'id'));
    $run = AnswerAssessment::decode($run);
    $history = AnswerAssessment::historyByAnswer($answer['id']);
    // Opnieuw beoordelen alleen vanaf de actuele run, niet vanaf een vervangen run in de geschiedenis
    $latest = AnswerAssessment::latestByAnswer($answer['id']);
    $isLatest = $latest !== null && (int)$latest['id'] === (int)$run['id'];
    $criteriaChanged = $this->normalizeNewlines((string)$answer['criteria'])
        !== $this->normalizeNewlines((string)$run['criteria_snapshot']);
    $workerActive = $this->isAssessmentWorkerActive();
    require __DIR__ . '/../views/docent/answer_assessment_view.php';
  }

  // ---------------------------------------------------------------------
  // Hulpfuncties
  // ---------------------------------------------------------------------

  /**
   * Laadt een antwoord met toets en vraag, en controleert de leestoegang tot de
   * toets (uit de database, nooit via een id die de client meestuurt).
   */
  private function loadAnswerForAssessment(?int $studentAnswerId): array {
    $answer = $studentAnswerId !== null ? StudentAnswer::findForAssessment($studentAnswerId) : null;
    if (!$answer) {
        abort(404, 'Antwoord niet gevonden.');
    }
    $this->checkExamReadAccess((int)$answer['exam_id']);
    return $answer;
  }

  /** Laadt een run en het bijbehorende antwoord; de toegang loopt via het antwoord uit de database. */
  private function loadRun(?int $id): array {
    $run = $id !== null ? AnswerAssessment::find($id) : null;
    if (!$run) {
        abort(404, 'Agentic beoordeling niet gevonden.');
    }
    return [$run, $this->loadAnswerForAssessment((int)$run['student_answer_id'])];
  }

  /**
   * Leestoegang tot een toets: eigenaar, admin of gedeelde toets.
   * Zelfde regel als DocentController::checkExamOwnership($id, false).
   */
  private function checkExamReadAccess(int $examId): void {
    $exam = Exam::find($examId);
    if (!$exam) {
        abort(404, 'Toets niet gevonden.');
    }
    if ($_SESSION['role'] === 'admin' || (int)$exam['docent_id'] === (int)$_SESSION['user_id'] || $exam['shared']) {
        return;
    }
    abort(403, 'Geen toegang: U bent niet de eigenaar van deze toets.');
  }

  /**
   * Nederlandse reden waarom dit antwoord niet agentic beoordeeld kan worden,
   * of null als het kan. De rubric-controle is grof (alleen de kopjes); de
   * worker parseert de criteria echt en stuurt anders een duidelijke fout terug.
   */
  private function startableReason(array $answer): ?string {
    if (empty($answer['completed_at'])) {
        return 'De toetspoging is nog niet ingeleverd.';
    }
    if (trim((string)$answer['answer']) === '') {
        return 'Het antwoord is leeg; er valt niets te beoordelen.';
    }
    if ((int)$answer['ai_grading_enabled'] !== 1) {
        return 'AI-beoordeling staat uit voor deze toets. Zet die aan bij de instellingen van de toets om agentic te beoordelen.';
    }
    $criteria = (string)$answer['criteria'];
    if (!preg_match('/^\s*Beoordelingscriteria\s*:\s*$/mi', $criteria) || !preg_match('/^\s*Puntentoekenning\s*:\s*$/mi', $criteria)) {
        return 'De criteria van deze vraag hebben geen rubric-opbouw (met de kopjes "Beoordelingscriteria:" en "Puntentoekenning:"); '
            . 'agentic beoordelen kan alleen met een rubric. Ontwerp de vraag met de AI-vraagontwerper of neem die opbouw over.';
    }
    return null;
  }

  /** Aantal runs dat de huidige docent dit uur nog mag starten. */
  private function remainingStarts(): int {
    return max(0, ASSESSMENT_START_MAX_PER_HOUR - AuditLog::countRecent('answer_assessment_start', 60, null, $_SESSION['name']));
  }

  private function rateLimitMessage(): string {
    return 'je hebt het afgelopen uur al ' . ASSESSMENT_START_MAX_PER_HOUR
        . ' agentic beoordelingen gestart. Probeer het later opnieuw.';
  }

  /** Maakt een run met snapshots van vraag, criteria en antwoord; één auditregel per run (ook de rate limit telt die). */
  private function createRun(array $answer): int {
    $id = AnswerAssessment::create((int)$answer['id'], (int)$_SESSION['user_id'],
        (string)$answer['question_text'], (string)$answer['criteria'], (string)$answer['answer']);
    AuditLog::log('answer_assessment_start', ['id' => $id, 'student_answer_id' => (int)$answer['id']]);
    return $id;
  }

  private function answersUrl(array $answer): string {
    return '/?action=view_student_answers&student_exam_id=' . (int)$answer['student_exam_id'] . '#answer-' . (int)$answer['id'];
  }

  private function normalizeNewlines(string $text): string {
    return trim(str_replace("\r\n", "\n", $text));
  }

  /** True als de assessment-worker recent jobs heeft opgehaald. */
  private function isAssessmentWorkerActive(): bool {
    $pingFile = __DIR__ . '/../../database/last_assessment_ping.txt';
    $lastPing = is_readable($pingFile) ? file_get_contents($pingFile) : false;
    return $lastPing !== false && is_numeric($lastPing) && (time() - (int)$lastPing) < ASSESSMENT_WORKER_STALE_SECONDS;
  }

  private function redirectWithError(string $url, string $message): void {
    $_SESSION['error'] = $message;
    header('Location: ' . $url);
    exit;
  }
}
