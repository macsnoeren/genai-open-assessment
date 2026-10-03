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
require_once __DIR__ . '/../models/Questions.php';
require_once __DIR__ . '/../models/QuestionDesign.php';

/**
 * Class QuestionDesignController
 * De agentic vraagontwerper: de docent voert een vraag en gewenst antwoord in,
 * de ontwerp-worker laat de AI-agents een rubric voorstellen, en de docent
 * stuurt bij of keurt goed. Pas bij goedkeuring ontstaat een gewone vraag.
 */
class QuestionDesignController {

  /**
   * Shows the form to start a new question design.
   */
  public function create() {
    requireRole('docent');

    $exam = $this->loadExamForWrite(requestInt($_GET, 'exam_id'));
    $old = $_SESSION['design_form_old'] ?? [];
    unset($_SESSION['design_form_old']);
    require __DIR__ . '/../views/docent/question_design_form.php';
  }

  /**
   * Stores a new question design; the design worker picks it up from here.
   */
  public function store() {
    validateCsrfToken();
    requireRole('docent');

    $exam = $this->loadExamForWrite(requestInt($_POST, 'exam_id'));
    $examId = (int)$exam['id'];
    $formUrl = '/?action=question_design_create&exam_id=' . $examId;

    $questionText = $this->readText('question_text', MAX_DESIGN_TEXT_LENGTH);
    $modelAnswer = $this->readText('model_answer', MAX_DESIGN_TEXT_LENGTH);
    // Bij een fout komt de docent terug op het formulier met de ingevulde tekst.
    $_SESSION['design_form_old'] = [
        'question_text' => $questionText ?? requestString($_POST, 'question_text', MAX_DESIGN_TEXT_LENGTH),
        'model_answer' => $modelAnswer ?? requestString($_POST, 'model_answer', MAX_DESIGN_TEXT_LENGTH),
    ];
    if ($questionText === null || $modelAnswer === null) {
        $this->redirectWithError($formUrl, 'De vraag en het gewenste antwoord mogen elk maximaal '
            . MAX_DESIGN_TEXT_LENGTH . ' tekens lang zijn.');
    }
    if ($questionText === '' || $modelAnswer === '') {
        $this->redirectWithError($formUrl, 'Vul zowel de vraag als het gewenste antwoord in.');
    }
    if (AuditLog::countRecent('question_design_create', 60, null, $_SESSION['name']) >= DESIGN_START_MAX_PER_HOUR) {
        $this->redirectWithError($formUrl, 'Je hebt het afgelopen uur al ' . DESIGN_START_MAX_PER_HOUR
            . ' vraagontwerpen gestart. Probeer het later opnieuw.');
    }
    unset($_SESSION['design_form_old']);

    $id = QuestionDesign::create($examId, $_SESSION['user_id'], $questionText, $modelAnswer);
    AuditLog::log('question_design_create', ['id' => $id, 'exam_id' => $examId]);

    header('Location: /?action=question_design_view&id=' . $id);
    exit;
  }

  /**
   * Shows a question design with everything the agents produced so far.
   */
  public function view() {
    requireRole('docent');

    $design = QuestionDesign::decode($this->loadDesignForWrite(requestInt($_GET, 'id')));
    $exam = Exam::find($design['exam_id']);
    require __DIR__ . '/../views/docent/question_design_view.php';
  }

  /**
   * Saves the teacher's answers to the clarifying questions; the worker then makes the rubric.
   */
  public function answer() {
    validateCsrfToken();
    requireRole('docent');

    $design = QuestionDesign::decode($this->loadDesignForWrite(requestInt($_POST, 'id')));
    $viewUrl = '/?action=question_design_view&id=' . (int)$design['id'];
    if ($design['status'] !== QuestionDesign::STATUS_AWAITING_ANSWERS) {
        $this->redirectWithError($viewUrl, 'Dit ontwerp wacht niet (meer) op jouw antwoorden.');
    }

    // De vragen komen uit de database; van de client komen alleen de antwoorden.
    $answers = [];
    foreach ($design['analysis']['clarifying_questions'] ?? [] as $i => $question) {
        $answer = $this->readText("answer_$i", MAX_DESIGN_INPUT_LENGTH);
        if ($answer === null) {
            $this->redirectWithError($viewUrl, 'Een antwoord mag maximaal ' . MAX_DESIGN_INPUT_LENGTH . ' tekens lang zijn.');
        }
        $answers[] = ['question' => $question['question'], 'why' => $question['why'], 'answer' => $answer];
    }

    $revision = requestInt($_POST, 'revision');
    if ($revision === null || !QuestionDesign::saveTeacherAnswers($design['id'], $revision, $answers)) {
        $this->redirectWithError($viewUrl, 'Dit formulier is verouderd. Bekijk de actuele stand en probeer het opnieuw.');
    }
    AuditLog::log('question_design_answer', [
        'id' => (int)$design['id'],
        'answered' => count(array_filter($answers, fn($a) => $a['answer'] !== '')),
        'questions' => count($answers),
    ]);

    header('Location: ' . $viewUrl);
    exit;
  }

  /**
   * Deletes a question design (not the question that was created from it).
   */
  public function delete() {
    validateCsrfToken();
    requireRole('docent');

    $design = $this->loadDesignForWrite(requestInt($_GET, 'id') ?? requestInt($_POST, 'id'));
    QuestionDesign::delete($design['id']);
    AuditLog::log('question_design_delete', ['id' => (int)$design['id'], 'exam_id' => (int)$design['exam_id']]);

    header('Location: /?action=questions&exam_id=' . (int)$design['exam_id']);
    exit;
  }

  /**
   * Laadt een toets die de huidige gebruiker mag wijzigen: eigenaar of admin.
   * Zelfde regel als DocentController::canEditExam(); gedeelde toetsen tellen niet.
   */
  private function loadExamForWrite(?int $examId): array {
    $exam = $examId !== null ? Exam::find($examId) : null;
    if (!$exam) {
        abort(404, 'Toets niet gevonden.');
    }
    if ($_SESSION['role'] !== 'admin' && (int)$exam['docent_id'] !== (int)$_SESSION['user_id']) {
        abort(403, 'Geen toegang: alleen de eigenaar van deze toets mag vragen ontwerpen.');
    }
    return $exam;
  }

  /**
   * Laadt een ontwerp en controleert de toegang via de toets uit de database
   * (nooit via een exam_id die de client meestuurt).
   */
  private function loadDesignForWrite(?int $id): array {
    $design = $id !== null ? QuestionDesign::find($id) : null;
    if (!$design) {
        abort(404, 'Vraagontwerp niet gevonden.');
    }
    $this->loadExamForWrite((int)$design['exam_id']);
    return $design;
  }

  /**
   * Leest een tekstveld uit $_POST: getrimd, of null als het langer is dan
   * $maxChars tekens (geweigerd, niet afgekapt) of geen geldige UTF-8 bevat.
   */
  private function readText(string $key, int $maxChars): ?string {
    // requestString kapt af op bytes; ruim nemen zodat de tekenlimiet hieronder beslist.
    $value = trim(requestString($_POST, $key, $maxChars * 4 + 1));
    if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > $maxChars) {
        return null;
    }
    return $value;
  }

  private function redirectWithError(string $url, string $message): void {
    $_SESSION['error'] = $message;
    header('Location: ' . $url);
    exit;
  }
}
