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
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Questions.php';
require_once __DIR__ . '/../models/Prompt.php';
require_once __DIR__ . '/../models/StudentAnswer.php';
require_once __DIR__ . '/../models/StudentExam.php';
require_once __DIR__ . '/../models/QuestionDesign.php';
require_once __DIR__ . '/../models/AnswerAssessment.php';
require_once __DIR__ . '/../models/IntegrationAttempt.php';
require_once __DIR__ . '/../models/Grading.php';
require_once __DIR__ . '/../models/GradingScheme.php';

/**
 * Class DocentController
 * Handles actions related to teachers (docenten) and graders (beoordelaars).
 */
class DocentController {
  
  /**
   * Displays the dashboard for the docent.
   */
  public function dashboard() {
    requireRole('docent');

    if ($_SESSION['role'] === 'admin') {
        $exams = Exam::all();
    } else {
        $exams = Exam::allByDocent($_SESSION['user_id']);
    }
    $totalExams = count($exams);
    $filter = $this->dashboardFilter();
    $filterActive = $filter !== self::DASHBOARD_FILTER_DEFAULT;
    $exams = $this->applyDashboardFilter($exams, $filter);
    require __DIR__ . '/../views/docent/dashboard.php';
  }

  /** Filter van het dashboard als er niets is gekozen. */
  private const DASHBOARD_FILTER_DEFAULT = ['q' => '', 'owner' => 'all', 'status' => 'all'];
  private const DASHBOARD_OWNERS = ['all', 'mine', 'colleagues'];
  private const DASHBOARD_STATUSES = ['all', 'published', 'unpublished', 'ai_on', 'ai_off', 'shared'];

  /**
   * Leest het dashboardfilter uit de query string en onthoudt het in de sessie,
   * zodat het blijft staan als de docent na bewerken terugkeert naar het dashboard.
   * Zonder `filter` in de URL geldt het onthouden filter; `reset_filter` wist het.
   */
  private function dashboardFilter(): array {
    if (isset($_GET['reset_filter'])) {
        unset($_SESSION['dashboard_filter']);
    } elseif (isset($_GET['filter'])) {
        $owner = requestString($_GET, 'owner', 20, 'all');
        $status = requestString($_GET, 'status', 20, 'all');
        $_SESSION['dashboard_filter'] = [
            'q' => trim(mb_scrub(requestString($_GET, 'q', 100), 'UTF-8')),
            'owner' => in_array($owner, self::DASHBOARD_OWNERS, true) ? $owner : 'all',
            'status' => in_array($status, self::DASHBOARD_STATUSES, true) ? $status : 'all',
        ];
    }
    $filter = $_SESSION['dashboard_filter'] ?? [];
    return is_array($filter) ? array_merge(self::DASHBOARD_FILTER_DEFAULT, $filter) : self::DASHBOARD_FILTER_DEFAULT;
  }

  /** Houdt alleen de toetsen over die aan het dashboardfilter voldoen (volgorde blijft gelijk). */
  private function applyDashboardFilter(array $exams, array $filter): array {
    $userId = (int)$_SESSION['user_id'];
    return array_values(array_filter($exams, function ($exam) use ($filter, $userId) {
        if ($filter['q'] !== '' && mb_stripos((string)$exam['title'], $filter['q'], 0, 'UTF-8') === false) {
            return false;
        }
        $isMine = (int)$exam['docent_id'] === $userId;
        if (($filter['owner'] === 'mine' && !$isMine) || ($filter['owner'] === 'colleagues' && $isMine)) {
            return false;
        }
        switch ($filter['status']) {
            case 'published':   return !empty($exam['published']);
            case 'unpublished': return empty($exam['published']);
            case 'ai_on':       return !empty($exam['ai_grading_enabled']);
            case 'ai_off':      return empty($exam['ai_grading_enabled']);
            case 'shared':      return !empty($exam['shared']);
            default:            return true;
        }
    }));
  }
  
  /**
   * Shows the form to create a new exam.
   */
  public function createExam() {
    requireRole('docent');
    
    $exam = null;
    $action = 'exam_store';
    $title = 'Nieuwe toets';
    $prompts = Prompt::all();
    $gradingSchemes = GradingScheme::all();
    $defaultScheme = GradingScheme::defaultScheme();
    $scaleLocked = false;
    require __DIR__ . '/../views/docent/exam_form.php';
  }
  
  /**
   * Stores a newly created exam in the database.
   */
  public function storeExam() {
    validateCsrfToken();
    requireRole('docent');
    
    $title = trim(requestString($_POST, 'title', 255));
    $description = requestString($_POST, 'description');
    if ($title === '') {
        abort(400, 'Titel is verplicht.');
    }
    [$gradingScale, $gradingSchemeId, $showGradeLabel] = $this->readGradingSettings();
    $promptId = $this->readPromptId($gradingScale);
    $aiGradingEnabled = isset($_POST['ai_grading_enabled']) ? 1 : 0;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $published = isset($_POST['published']) ? 1 : 0;

    $examId = Exam::create(
		 $title,
		 $description,
		 $_SESSION['user_id'],
         $promptId,
         $aiGradingEnabled,
         $shared,
         $published,
         $gradingScale,
         $gradingSchemeId,
         $showGradeLabel
		 );
    AuditLog::log('exam_create', [
        'id' => $examId,
        'title' => $title,
        'description' => $description,
        'prompt_id' => $promptId,
        'ai_grading_enabled' => $aiGradingEnabled,
        'shared' => $shared,
        'published' => $published,
        'grading_scale' => $gradingScale,
        'grading_scheme_id' => $gradingSchemeId,
        'show_grade_label' => $showGradeLabel
    ]);
    
    header('Location: /?action=docent_dashboard');
    exit;
  }
  
  /**
   * Shows the form to edit an existing exam.
   */
  public function editExam() {
    requireRole('docent');
    
    $id = requestInt($_GET, 'id');
    $this->checkExamOwnership($id, true);
    
    $exam = Exam::find($id);
    $action = 'exam_update';
    $title = 'Toets bewerken';
    $prompts = Prompt::all();
    $gradingSchemes = GradingScheme::all();
    $defaultScheme = GradingScheme::defaultScheme();
    $scaleLocked = Exam::hasSubmittedAttempts($id);
    require __DIR__ . '/../views/docent/exam_form.php';
  }
  
  /**
   * Updates an existing exam in the database.
   */
  public function updateExam() {
    validateCsrfToken();
    requireRole('docent');
    
    $id = requestInt($_POST, 'id');
    $this->checkExamOwnership($id, true);
    
    $currentExam = Exam::find($id);
    $title = trim(requestString($_POST, 'title', 255));
    $description = requestString($_POST, 'description');
    if ($title === '') {
        abort(400, 'Titel is verplicht.');
    }
    [$gradingScale, $gradingSchemeId, $showGradeLabel] = $this->readGradingSettings();
    $currentScale = Grading::examScale($currentExam);
    if ($gradingScale !== $currentScale && Exam::hasSubmittedAttempts($id)) {
        abort(400, 'De schaal van deze toets kan niet meer wijzigen: er zijn al resultaten.');
    }
    $promptId = $this->readPromptId($gradingScale);
    $aiGradingEnabled = isset($_POST['ai_grading_enabled']) ? 1 : 0;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $published = isset($_POST['published']) ? 1 : 0;
    
    Exam::update(
		 $id,
		 $title,
		 $description,
         $promptId,
         $aiGradingEnabled,
         $shared,
         $published,
         $gradingScale,
         $gradingSchemeId,
         $showGradeLabel
		 );

    $changes = ['id' => $id];
    if ($currentExam['title'] !== $title) {
        $changes['title'] = ['old' => $currentExam['title'], 'new' => $title];
    }
    if ($currentExam['description'] !== $description) {
        $changes['description'] = ['old' => $currentExam['description'], 'new' => $description];
    }
    if ($currentExam['prompt_id'] != $promptId) {
        $changes['prompt_id'] = ['old' => $currentExam['prompt_id'], 'new' => $promptId];
        
        // Reset AI feedback for all students for this exam to ensure consistency
        StudentAnswer::clearAiFeedbackByExam($id);
        AuditLog::log('exam_ai_feedback_cleared', ['exam_id' => $id, 'reason' => 'prompt_change']);
    }
    if ($currentExam['ai_grading_enabled'] != $aiGradingEnabled) {
        $changes['ai_grading_enabled'] = ['old' => $currentExam['ai_grading_enabled'], 'new' => $aiGradingEnabled];
    }
    if ($currentExam['shared'] != $shared) {
        $changes['shared'] = ['old' => $currentExam['shared'], 'new' => $shared];
    }
    if (($currentExam['published'] ?? 0) != $published) {
        $changes['published'] = ['old' => $currentExam['published'] ?? 0, 'new' => $published];
    }
    if ($currentScale !== $gradingScale) {
        $changes['grading_scale'] = ['old' => $currentScale, 'new' => $gradingScale];
    }
    // Een ander schema of vinkje herberekent de cijfers bij het tonen (B5); niets wordt opnieuw beoordeeld
    if ($currentExam['grading_scheme_id'] != $gradingSchemeId) {
        $changes['grading_scheme_id'] = ['old' => $currentExam['grading_scheme_id'], 'new' => $gradingSchemeId];
    }
    if ((int)($currentExam['show_grade_label'] ?? 0) !== $showGradeLabel) {
        $changes['show_grade_label'] = ['old' => (int)($currentExam['show_grade_label'] ?? 0), 'new' => $showGradeLabel];
    }

    AuditLog::log('exam_update', $changes);
    
    header('Location: /?action=docent_dashboard');
    exit;
  }
  
  /**
   * Publieke gastlink vernieuwen (mode=renew, ook om hem weer aan te zetten) of
   * uitzetten (mode=disable). Alleen eigenaar of admin.
   */
  public function setPublicLink() {
    validateCsrfToken();
    requireRole('docent');

    $id = requestInt($_GET, 'id') ?? requestInt($_POST, 'id');
    $this->checkExamOwnership($id, true);
    $mode = requestString($_GET, 'mode', 10);
    if (!in_array($mode, ['renew', 'disable'], true)) {
        abort(400, 'Ongeldig verzoek.');
    }

    Exam::setPublicLink($id, $mode === 'renew');
    AuditLog::log('exam_public_link', ['exam_id' => $id, 'mode' => $mode]);
    $_SESSION['success_message'] = $mode === 'renew'
        ? 'Er is een nieuwe gastlink gemaakt. De oude link werkt niet meer.'
        : 'De gastlink staat uit. Lopende gastpogingen werken nog wel.';
    header('Location: /?action=docent_dashboard');
    exit;
  }

  /**
   * Deletes an exam.
   */
  public function deleteExam() {
    validateCsrfToken();
    requireRole('docent');
    
    $id = requestInt($_GET, 'id') ?? requestInt($_POST, 'id');
    $this->checkExamOwnership($id, true);
    
    AuditLog::log('exam_delete', ['id' => $id]);
    Exam::delete($id);
    header('Location: /?action=docent_dashboard');
    exit;
  }

  /**
   * Duplicates an exam including questions and student answers (but resets AI feedback).
   */
  public function duplicateExam() {
    validateCsrfToken();
    requireRole('docent');
    
    $id = requestInt($_GET, 'id') ?? requestInt($_POST, 'id');
    $this->checkExamOwnership($id, true);
    
    try {
        $newId = Exam::duplicate($id);
        AuditLog::log('exam_duplicate', ['source_id' => $id, 'new_id' => $newId]);
    } catch (Exception $e) {
        error_log('Exam duplicate failed: ' . $e->getMessage());
        $_SESSION['error'] = 'Dupliceren is mislukt.';
    }
    
    header('Location: /?action=docent_dashboard');
    exit;
  }

  /**
   * Lists all questions for a specific exam.
   * @param int $examId
   */
  public function questions($examId) {
    requireRole('docent');
    
    $this->checkExamOwnership($examId);
    
    $exam = Exam::find($examId);
    $canEdit = $this->canEditExam($exam);
    $questions = Question::allByExam($examId);
    // AI-vraagontwerpen zijn alleen voor wie de toets mag wijzigen
    $designs = $canEdit ? QuestionDesign::allByExam($examId) : [];
    
    // Nummer de vragen voor weergave
    foreach ($questions as $index => &$question) {
        $question['question_text'] = ($index + 1) . ". " . $question['question_text'];
    }
    unset($question);

    require __DIR__ . '/../views/docent/questions.php';
  }
  
  /**
   * Shows the form to create a new question.
   */
  public function createQuestion() {
    requireRole('docent');
    
    $examId = requestInt($_GET, 'exam_id');
    $this->checkExamOwnership($examId, true);
    $question = null;
    $action = 'question_store';
    $title = 'Nieuwe vraag';
    require __DIR__ . '/../views/docent/question_form.php';
  }
  
  /**
   * Stores a newly created question.
   */
  public function storeQuestion() {
    validateCsrfToken();
    requireRole('docent');
    
    $examId = requestInt($_POST, 'exam_id');
    $this->checkExamOwnership($examId, true);

    $questionText = trim(requestString($_POST, 'question_text'));
    $criteria = requestString($_POST, 'criteria');
    if ($questionText === '') {
        abort(400, 'Vraagtekst is verplicht.');
    }
    
    Question::create($examId, $questionText, $criteria);
    AuditLog::log('question_create', [
        'exam_id' => $examId, 
        'question_text' => $questionText,
        'criteria' => $criteria
    ]);
    
    header('Location: /?action=questions&exam_id=' . $examId);
    exit;
  }
  
  /**
   * Shows the form to edit a question.
   */
  public function editQuestion() {
    requireRole('docent');
    
    $id = requestInt($_GET, 'id');
    $question = $id !== null ? Question::find($id) : null;
    if (!$question) {
        abort(404, 'Vraag niet gevonden.');
    }
    $this->checkExamOwnership($question['exam_id'], true);
    $examId = $question['exam_id'];
    $action = 'question_update';
    $title = 'Vraag bewerken';
    require __DIR__ . '/../views/docent/question_form.php';
  }
  
  /**
   * Updates an existing question.
   */
  public function updateQuestion() {
    validateCsrfToken();
    requireRole('docent');
    
    $id = requestInt($_POST, 'id');
    $currentQuestion = $id !== null ? Question::find($id) : null;
    if (!$currentQuestion) {
        abort(404, 'Vraag niet gevonden.');
    }
    $this->checkExamOwnership($currentQuestion['exam_id'], true);

    $questionText = trim(requestString($_POST, 'question_text'));
    $criteria = requestString($_POST, 'criteria');
    if ($questionText === '') {
        abort(400, 'Vraagtekst is verplicht.');
    }

    Question::update($id, $questionText, $criteria);

    $changes = ['id' => $id];
    if ($currentQuestion['question_text'] !== $questionText) {
        $changes['question_text'] = ['old' => $currentQuestion['question_text'], 'new' => $questionText];
    }
    if ($currentQuestion['criteria'] !== $criteria) {
        $changes['criteria'] = ['old' => $currentQuestion['criteria'], 'new' => $criteria];
    }

    AuditLog::log('question_update', $changes);
    
    header('Location: /?action=questions&exam_id=' . $currentQuestion['exam_id']);
    exit;
  }
  
  /**
   * Deletes a question.
   */
  public function deleteQuestion() {
    validateCsrfToken();
    requireRole('docent');
    
    $id = requestInt($_GET, 'id') ?? requestInt($_POST, 'id');
    $question = $id !== null ? Question::find($id) : null;
    if (!$question) {
        abort(404, 'Vraag niet gevonden.');
    }
    $this->checkExamOwnership($question['exam_id'], true);
    $examId = $question['exam_id'];
    AuditLog::log('question_delete', [
        'id' => $id,
        'question_text' => $question['question_text']
    ]);
    Question::delete($id);
    
    header('Location: /?action=questions&exam_id=' . $examId);
    exit;
  }

  /**
   * Views the results of all students for a specific exam.
   * @param int $examId
   */
public function viewExamResults($examId) {
    requireRole('docent');
    
    $this->checkExamOwnership($examId);

    $exam = Exam::find($examId);
    $canEdit = $this->canEditExam($exam);
    $studentExams = StudentExam::findWithStudentDetailsByExam($examId);
    // Knop "AI opnieuw" per poging; completed_at en integration_name zitten al in de rij
    $aiResettableCount = 0;
    foreach ($studentExams as &$se) {
        $se['can_reset_ai'] = $canEdit && $this->aiResetBlockedReason($se, $exam, !empty($se['integration_name']) || !empty($se['external_ref'])) === null;
        $aiResettableCount += $se['can_reset_ai'] ? 1 : 0;
        // Eindcijfer per poging (inclusief een handmatige aanpassing)
        $se['result'] = !empty($se['completed_at']) ? Grading::attemptResult((int)$se['student_exam_id']) : null;
    }
    unset($se);
    require __DIR__ . '/../views/docent/exam_results.php';
}

  /**
   * Views the detailed answers of a specific student exam attempt.
   * @param int $studentExamId
   */
public function viewStudentAnswers($studentExamId) {
    requireRole('docent');

    $studentExam = $studentExamId !== null ? StudentExam::find($studentExamId) : null;
    if (!$studentExam) {
        abort(404, 'Toetspoging niet gevonden.');
    }
    $this->checkExamOwnership($studentExam['exam_id']);
    $exam = Exam::find($studentExam['exam_id']);
    $canEdit = $this->canEditExam($exam);
    // Agentic beoordelingen per antwoord (alleen in deze docentweergave, niet in de blinde beoordeling)
    $assessmentRuns = AnswerAssessment::latestByStudentExam($studentExamId);

    // Poging via een externe koppeling: die website haalt het resultaat op (geen deelbare link)
    $integrationAttempt = IntegrationAttempt::findByStudentExam($studentExamId);

    // AI-resultaten opnieuw laten uitvoeren: alleen met schrijfrecht, en de reden tonen als het niet kan
    $aiResetReason = $canEdit ? $this->aiResetBlockedReason($studentExam, $exam, $integrationAttempt !== null) : null;
    $canResetAi = $canEdit && $aiResetReason === null;

    // Genereer een deelbare link voor gaststudenten zodat zij hun resultaat kunnen inzien
    $shareableLink = null;
    if ($studentExam['student_id'] === null && !empty($studentExam['access_token']) && !$integrationAttempt) {
        $shareableLink = appBaseUrl() . "/?action=student_view_results&student_exam_id={$studentExamId}&token={$studentExam['access_token']}";
    }

    $pdo = Database::connect();
        $stmt = $pdo->prepare("
        SELECT sa.id, q.question_text, sa.answer, q.criteria, sa.ai_feedback, sa.teacher_score, sa.teacher_level, sa.teacher_feedback,
               " . AnswerAssessment::agenticScoreSql('sa') . " AS agentic_score,
               " . AnswerAssessment::agenticLevelSql('sa') . " AS agentic_level
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.student_exam_id = ?
        ORDER BY sa.id ASC
    ");
        $stmt->execute([$studentExamId]);
	    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eindresultaat (berekend cijfer en een eventuele handmatige aanpassing): alleen via Grading
    $gradingScale = Grading::examScale($exam);
    $attemptResult = Grading::attemptResult((int)$studentExamId);

    if ($gradingScale === Grading::SCALE_LEVELS) {
        // AI-cijfer per bron over de antwoorden waarvoor die bron een niveau heeft
        $aiGrades = $attemptResult['scheme'] ? Grading::aiGrades($answers, $attemptResult['scheme']) : [];
        foreach ($answers as &$a) {
            $a['ai_levels'] = StudentAnswer::aiLevels($a['ai_feedback'], $a['agentic_level']);
        }
        unset($a);
    } else {
        // Eindscore docent: het gemiddelde van de docentscores (Grading::attemptResult())
        $finalScore = $attemptResult['computed'];
        $aiModelScores = [];
        foreach ($answers as $a) {
            foreach (StudentAnswer::aiScores($a['ai_feedback'], $a['agentic_score']) as $source => $score) {
                $aiModelScores[$source][] = $score;
            }
        }

        $finalAiScores = [];
        foreach ($aiModelScores as $model => $scores) {
            if (count($scores) > 0) {
                $finalAiScores[$model] = array_sum($scores) / count($scores);
            }
        }
        ksort($finalAiScores);
    }

    require __DIR__ . '/../views/docent/student_answers.php';
    }

  /**
   * Shows the grading interface for a student exam (blind grading).
   * @param int $studentExamId
   */
  public function gradeStudentExam($studentExamId) {
    requireRole('beoordelaar');

    $studentExam = $studentExamId !== null ? StudentExam::find($studentExamId) : null;
    if (!$studentExam) {
        abort(404, 'Toetspoging niet gevonden.');
    }
    $this->checkGradingPermission($studentExam['exam_id']);
    $exam = Exam::find($studentExam['exam_id']);
    $gradingScale = Grading::examScale($exam);

    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT sa.id, q.question_text, sa.answer, q.criteria, sa.teacher_score, sa.teacher_level, sa.teacher_feedback
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.student_exam_id = ?
    ");
    $stmt->execute([$studentExamId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Eindcijfer (zonder AI): het berekende cijfer en het formulier om het handmatig aan te passen
    $attemptResult = Grading::attemptResult((int)$studentExamId);

    require __DIR__ . '/../views/docent/grade_exam.php';
  }

  /**
   * Saves the teacher's feedback and score for a specific answer.
   */
  public function saveTeacherFeedback() {
    validateCsrfToken();
    requireRole('beoordelaar');

    // Autorisatie uitsluitend op basis van het antwoord zelf (niet op POST-input)
    $studentAnswerId = requestInt($_POST, 'student_answer_id');
    $answer = $studentAnswerId !== null ? StudentAnswer::findWithExam($studentAnswerId) : null;
    if (!$answer) {
        abort(404, 'Antwoord niet gevonden.');
    }
    $this->checkGradingPermission($answer['exam_id']);
    $studentExamId = (int)$answer['student_exam_id'];

    // De schaal komt uit de database (de toets van het antwoord), nooit uit de POST
    $isLevels = Grading::examScale($answer) === Grading::SCALE_LEVELS;
    $score = null;
    $level = null;
    if ($isLevels) {
        $levelRaw = trim(requestString($_POST, 'teacher_level', 20));
        if ($levelRaw !== '' && !Grading::isLevel($levelRaw)) {
            abort(400, 'Ongeldig niveau.');
        }
        $level = $levelRaw === '' ? null : $levelRaw;
    } else {
        $scoreRaw = trim(requestString($_POST, 'teacher_score', 10));
        if ($scoreRaw === '') {
            $score = null;
        } elseif (preg_match('/^(10|[0-9])$/', $scoreRaw)) {
            $score = (int)$scoreRaw;
        } else {
            abort(400, 'Score moet een geheel getal van 0 t/m 10 zijn.');
        }
    }
    $feedback = requestString($_POST, 'teacher_feedback');

    $redirectAction = $this->gradingRedirectAction();

    if ($isLevels) {
        StudentAnswer::updateTeacherLevel($studentAnswerId, $level, $feedback);
    } else {
        StudentAnswer::updateTeacherGrade($studentAnswerId, $score, $feedback);
    }

    $changes = ['student_answer_id' => $studentAnswerId, 'student_exam_id' => $studentExamId];
    if ($isLevels) {
        if (($answer['teacher_level'] ?? null) !== $level) {
            $changes['teacher_level'] = ['old' => $answer['teacher_level'] ?? null, 'new' => $level];
        }
    } elseif ((string)($answer['teacher_score'] ?? '') !== (string)($score ?? '')) {
        $changes['teacher_score'] = ['old' => $answer['teacher_score'], 'new' => $score];
    }
    if (($answer['teacher_feedback'] ?? '') !== $feedback) {
        $changes['teacher_feedback'] = ['old' => $answer['teacher_feedback'] ?? '', 'new' => $feedback];
    }
    AuditLog::log('teacher_grade', $changes);

    // Externe koppeling: heeft nu elk antwoord een docentscore (of docentniveau), dan attempt.reviewed.
    try {
        IntegrationAttempt::checkReviewed($studentExamId);
    } catch (Throwable $e) {
        error_log('Integratie-event attempt.reviewed mislukt: ' . $e->getMessage());
    }

    header('Location: /?action=' . $redirectAction . '&student_exam_id=' . $studentExamId . '#answer-' . $studentAnswerId);
    exit;
  }

  /**
   * Past het eindcijfer van een toetspoging handmatig aan (B8). Mag iedereen die
   * de toets mag nakijken. Een cijfer 0-10 met één decimaal, of bij een toets met
   * woordbeoordeling een woord; de reden is verplicht.
   */
  public function overrideFinalGrade() {
    validateCsrfToken();
    requireRole('beoordelaar');

    [$studentExam, $exam] = $this->findAttemptForGrading();
    $studentExamId = (int)$studentExam['id'];

    $reason = trim(requestString($_POST, 'reason', MAX_GRADE_OVERRIDE_REASON + 1));
    $reasonLength = mb_strlen($reason, 'UTF-8');
    if ($reasonLength < 1 || $reasonLength > MAX_GRADE_OVERRIDE_REASON) {
        abort(400, 'Een reden is verplicht (hooguit ' . MAX_GRADE_OVERRIDE_REASON . ' tekens).');
    }

    // Cijfer of woord: dat bepaalt de toets in de database, niet het formulier
    $grade = null;
    $label = null;
    if (Grading::examScale($exam) === Grading::SCALE_LEVELS && !empty($exam['show_grade_label'])) {
        $label = trim(requestString($_POST, 'grade_label', 20));
        if (!Grading::isLevel($label)) {
            abort(400, 'Ongeldig woord: kies onvoldoende, voldoende, goed of uitstekend.');
        }
    } else {
        $raw = str_replace(',', '.', trim(requestString($_POST, 'grade', 10)));
        if (!preg_match('/^(10(\.0)?|[0-9](\.[0-9])?)$/', $raw)) {
            abort(400, 'Het cijfer moet tussen 0 en 10 liggen, met hooguit één decimaal.');
        }
        $grade = Grading::roundGrade((float)$raw);
    }

    $before = Grading::attemptResult($studentExamId);
    StudentExam::setOverride($studentExamId, $grade, $label, $reason, (int)$_SESSION['user_id'], $before['computed']);
    AuditLog::log('final_grade_override', [
        'student_exam_id' => $studentExamId,
        'old' => $before['override'],
        'new' => $label ?? $grade,
        'reason' => $reason,
        'computed' => $before['computed'],
    ]);

    $_SESSION['success_message'] = 'Het eindcijfer is aangepast.';
    header('Location: /?action=' . $this->gradingRedirectAction() . '&student_exam_id=' . $studentExamId . '#final-grade');
    exit;
  }

  /** Verwijdert een handmatig eindcijfer; daarna geldt weer het berekende cijfer. */
  public function clearFinalGradeOverride() {
    validateCsrfToken();
    requireRole('beoordelaar');

    [$studentExam] = $this->findAttemptForGrading();
    $studentExamId = (int)$studentExam['id'];

    $before = Grading::attemptResult($studentExamId);
    if ($before['override'] !== null) {
        StudentExam::clearOverride($studentExamId);
        AuditLog::log('final_grade_override_clear', [
            'student_exam_id' => $studentExamId,
            'old' => $before['override'],
            'reason' => $before['override_reason'],
            'computed' => $before['computed'],
        ]);
        $_SESSION['success_message'] = 'De aanpassing van het eindcijfer is verwijderd.';
    }

    header('Location: /?action=' . $this->gradingRedirectAction() . '&student_exam_id=' . $studentExamId . '#final-grade');
    exit;
  }

  /**
   * De ingeleverde toetspoging uit de POST (student_exam_id) met zijn toets, na de
   * controle dat de gebruiker die toets mag nakijken. De toets komt uit de database.
   * @return array [student_exam, exam]
   */
  private function findAttemptForGrading(): array {
    $studentExamId = requestInt($_POST, 'student_exam_id');
    $studentExam = $studentExamId !== null ? StudentExam::find($studentExamId) : null;
    if (!$studentExam) {
        abort(404, 'Toetspoging niet gevonden.');
    }
    $this->checkGradingPermission($studentExam['exam_id']);
    if (empty($studentExam['completed_at'])) {
        abort(400, 'Deze toetspoging is nog niet ingeleverd.');
    }
    return [$studentExam, Exam::find($studentExam['exam_id'])];
  }

  /** Terug naar de docentweergave of de blinde beoordeling; een beoordelaar altijd naar de blinde beoordeling. */
  private function gradingRedirectAction(): string {
    $redirectAction = requestString($_POST, 'redirect_action', 40, 'view_student_answers');
    if (!in_array($redirectAction, ['view_student_answers', 'grade_student_exam'], true)) {
        $redirectAction = 'view_student_answers';
    }
    if ($redirectAction === 'view_student_answers' && $_SESSION['role'] === 'beoordelaar') {
        $redirectAction = 'grade_student_exam';
    }
    return $redirectAction;
  }

  /**
   * Updates the name of a guest student.
   */
  public function updateGuestName() {
    validateCsrfToken();
    requireRole('docent');

    $studentExamId = requestInt($_POST, 'student_exam_id');
    $guestName = trim(requestString($_POST, 'guest_name', MAX_NAME_LENGTH));

    if ($studentExamId !== null && $guestName !== '') {
        $studentExam = StudentExam::find($studentExamId);
        
        if ($studentExam) {
            $this->checkExamOwnership($studentExam['exam_id'], true);
            
            $oldName = $studentExam['guest_name'];
            $updated = StudentExam::updateGuestName($studentExamId, $guestName);
            
            if ($updated) {
                AuditLog::log('guest_name_update', [
                    'student_exam_id' => $studentExamId, 
                    'new_name' => $guestName,
                    'old_name' => $oldName
                ]);
                $_SESSION['success_message'] = "Naam van gaststudent succesvol aangepast.";
            }
        }
    }

    header("Location: /?action=view_student_answers&student_exam_id=" . $studentExamId);
    exit;
  }

  /**
   * Deletes a student's exam attempt.
   */
  public function deleteStudentExam() {
    validateCsrfToken();
    requireRole('docent');
    
    $studentExamId = requestInt($_GET, 'student_exam_id') ?? requestInt($_POST, 'student_exam_id');
    $studentExam = $studentExamId !== null ? StudentExam::find($studentExamId) : null;
    
    if ($studentExam) {
        $this->checkExamOwnership($studentExam['exam_id'], true);
        AuditLog::log('student_exam_delete', ['id' => $studentExamId]);
        StudentExam::delete($studentExamId);
        header('Location: /?action=exam_results&exam_id=' . $studentExam['exam_id']);
        exit;
    }
    
    header('Location: /?action=docent_dashboard');
    exit;
  }

  /**
   * Verwijdert de AI-resultaten (AI-feedback en agentic beoordeling) van één
   * antwoord, zodat de AI het opnieuw beoordeelt. De docentbeoordeling blijft staan.
   */
  public function resetAiResultsAnswer() {
    validateCsrfToken();
    requireRole('docent');

    // Autorisatie uitsluitend op basis van het antwoord zelf (niet op POST-input)
    $studentAnswerId = requestInt($_POST, 'student_answer_id');
    $answer = $studentAnswerId !== null ? StudentAnswer::findWithExam($studentAnswerId) : null;
    if (!$answer) {
        abort(404, 'Antwoord niet gevonden.');
    }
    $examId = (int)$answer['exam_id'];
    $this->checkExamOwnership($examId, true);
    $studentExamId = (int)$answer['student_exam_id'];
    $studentExam = StudentExam::find($studentExamId);
    $exam = Exam::find($examId);

    $redirect = '/?action=view_student_answers&student_exam_id=' . $studentExamId . '#answer-' . $studentAnswerId;
    $reason = $this->aiResetBlockedReason($studentExam, $exam, IntegrationAttempt::findByStudentExam($studentExamId) !== null);
    if ($reason === null && $this->aiResetRateLimited()) {
        $reason = $this->aiResetRateLimitMessage();
    }
    if ($reason !== null) {
        $_SESSION['error'] = $reason;
        header('Location: ' . $redirect);
        exit;
    }

    $this->performAiReset('answer', $examId, [$studentExamId => [$studentAnswerId]]);
    $_SESSION['success_message'] = 'De AI-resultaten van dit antwoord zijn verwijderd. De AI beoordeelt het opnieuw.';
    header('Location: ' . $redirect);
    exit;
  }

  /**
   * Verwijdert de AI-resultaten van alle antwoorden van één toetspoging in één
   * keer, zodat de AI ze opnieuw beoordeelt. Terug naar de antwoordenpagina, of
   * met return=exam_results naar de resultatenpagina van de toets.
   */
  public function resetAiResultsAttempt() {
    validateCsrfToken();
    requireRole('docent');

    $studentExamId = requestInt($_POST, 'student_exam_id');
    $studentExam = $studentExamId !== null ? StudentExam::findWithStudentName($studentExamId) : null;
    if (!$studentExam) {
        abort(404, 'Toetspoging niet gevonden.');
    }
    // Toets-id uit de database, nooit uit het formulier
    $examId = (int)$studentExam['exam_id'];
    $this->checkExamOwnership($examId, true);
    $exam = Exam::find($examId);

    // Alleen een vaste keuze: nooit een vrije URL uit het formulier
    $redirect = requestString($_POST, 'return', 20) === 'exam_results'
        ? '/?action=exam_results&exam_id=' . $examId
        : '/?action=view_student_answers&student_exam_id=' . $studentExamId;

    $reason = $this->aiResetBlockedReason($studentExam, $exam, IntegrationAttempt::findByStudentExam($studentExamId) !== null);
    $answerIds = StudentAnswer::answerIdsByStudentExam($studentExamId);
    if ($reason === null && !$answerIds) {
        $reason = 'Deze toetspoging heeft geen antwoorden.';
    }
    if ($reason === null && $this->aiResetRateLimited()) {
        $reason = $this->aiResetRateLimitMessage();
    }
    if ($reason !== null) {
        $_SESSION['error'] = $reason;
        header('Location: ' . $redirect);
        exit;
    }

    $counts = $this->performAiReset('attempt', $examId, [$studentExamId => $answerIds]);
    $_SESSION['success_message'] = 'AI-resultaten van ' . $studentExam['name'] . ' verwijderd: '
        . $counts['ai_feedback'] . ' AI-feedback, ' . $counts['agentic_runs'] . ' agentic beoordelingen. '
        . 'De AI beoordeelt de antwoorden opnieuw.';
    header('Location: ' . $redirect);
    exit;
  }

  /**
   * Verwijdert de AI-resultaten van alle ingeleverde pogingen van een toets in
   * één keer, zodat de AI ze opnieuw beoordeelt. Pogingen die niet kunnen (niet
   * ingeleverd, externe koppeling) worden overgeslagen en geteld.
   */
  public function resetAiResultsExam() {
    validateCsrfToken();
    requireRole('docent');

    $examId = requestInt($_POST, 'exam_id');
    $this->checkExamOwnership($examId, true);
    $exam = Exam::find($examId);

    $redirect = '/?action=exam_results&exam_id=' . (int)$examId;
    $error = null;
    if ((int)$exam['ai_grading_enabled'] !== 1) {
        $error = 'AI-beoordeling staat uit voor deze toets. Zet die eerst aan; anders worden de AI-resultaten alleen verwijderd.';
    } elseif ($this->aiResetRateLimited()) {
        $error = $this->aiResetRateLimitMessage();
    }

    $answerIdsByAttempt = [];
    $skipped = [];
    $skippedCounts = [];
    if ($error === null) {
        foreach (StudentExam::findWithStudentDetailsByExam($examId) as $se) {
            $studentExamId = (int)$se['student_exam_id'];
            $isIntegration = !empty($se['integration_name']) || !empty($se['external_ref']);
            $reason = $this->aiResetBlockedReason($se, $exam, $isIntegration);
            $answerIds = $reason === null ? StudentAnswer::answerIdsByStudentExam($studentExamId) : [];
            if ($reason === null && $answerIds) {
                $answerIdsByAttempt[$studentExamId] = $answerIds;
                continue;
            }
            $label = $reason === null ? 'geen antwoorden' : ($isIntegration ? 'externe koppeling' : 'nog niet ingeleverd');
            $skipped[] = ['student_exam_id' => $studentExamId, 'reason' => $label];
            $skippedCounts[$label] = ($skippedCounts[$label] ?? 0) + 1;
        }
        if (!$answerIdsByAttempt) {
            $error = 'Deze toets heeft geen pogingen waarvan de AI-resultaten opnieuw kunnen worden uitgevoerd.';
        }
    }
    if ($error !== null) {
        $_SESSION['error'] = $error;
        header('Location: ' . $redirect);
        exit;
    }

    $counts = $this->performAiReset('exam', (int)$examId, $answerIdsByAttempt, ['skipped' => $skipped]);
    $message = 'AI-resultaten van ' . count($answerIdsByAttempt) . ' pogingen verwijderd: '
        . $counts['ai_feedback'] . ' AI-feedback, ' . $counts['agentic_runs'] . ' agentic beoordelingen. '
        . 'De AI beoordeelt de antwoorden opnieuw.';
    if ($skippedCounts) {
        $parts = [];
        foreach ($skippedCounts as $label => $n) {
            $parts[] = $n . ' ' . $label;
        }
        $message .= ' Overgeslagen: ' . implode(', ', $parts) . '.';
    }
    $_SESSION['success_message'] = $message;
    header('Location: ' . $redirect);
    exit;
  }

  /**
   * Shows a list of assessments pending grading.
   */
  public function pendingAssessments() {
    requireRole('beoordelaar');

    $pdo = Database::connect();
    // Haal toetsen op die ingeleverd zijn, gekoppeld aan deze docent, en nog niet volledig beoordeeld zijn.
    $sql = "
        SELECT se.id, se.completed_at, COALESCE(u.name, se.guest_name, 'Gast') as student_name, e.title as exam_title,
               COUNT(sa.id) as total_answers,
               COUNT(COALESCE(sa.teacher_score, sa.teacher_level)) as graded_answers
        FROM student_exams se
        LEFT JOIN users u ON se.student_id = u.id
        JOIN exams e ON se.exam_id = e.id
        LEFT JOIN student_answers sa ON se.id = sa.student_exam_id
        WHERE se.completed_at IS NOT NULL
    ";

    $params = [];
    // Als het een docent is, filter op eigen examens. Beoordelaars en admins zien alles.
    if ($_SESSION['role'] === 'docent') {
        $sql .= " AND e.docent_id = ? ";
        $params[] = $_SESSION['user_id'];
    }

    $sql .= " GROUP BY se.id
        HAVING graded_answers < total_answers
        ORDER BY se.completed_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pendingExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require __DIR__ . '/../views/docent/pending_assessments.php';
  }

  /**
   * Displays the audit log.
   */
  public function auditLog() {
    requireRole('docent');
    
    $page = requestInt($_GET, 'page') ?? 1;
    if ($page < 1) $page = 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;

    // Admin ziet alles; een docent ziet uitsluitend zijn eigen acties.
    $where = '';
    $params = [];
    if ($_SESSION['role'] !== 'admin') {
        $where = ' WHERE user_id = :user_id';
        $params[':user_id'] = (int)$_SESSION['user_id'];
    }

    $pdo = Database::connect();
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log" . $where);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $stmt = $pdo->prepare("SELECT * FROM audit_log" . $where . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    require __DIR__ . '/../views/docent/audit_log.php';
  }

  /**
   * Clears the audit log (Admin only).
   */
  public function clearAuditLog() {
    validateCsrfToken();
    requireRole('admin');

    // Het laatste uur blijft staan: de audit log is ook de bron voor de login-lockout en
    // alle rate limits (vensters van hooguit 60 minuten). Leegmaken zet die dus niet terug.
    $pdo = Database::connect();
    $pdo->exec("DELETE FROM audit_log WHERE created_at < datetime('now', '-60 minutes')");

    // Log the clearing action itself, so there's a trace of who did it.
    AuditLog::log('audit_log_cleared');

    header('Location: /?action=audit_log');
    exit;
  }

  /**
   * Compares teacher grading vs AI models.
   * @param int $examId
   */
  public function compareExamResults($examId) {
    requireRole('docent');

    $this->checkExamOwnership($examId);

    $exam = Exam::find($examId);
    $questions = Question::allByExam($examId);
    
    $prompt = null;
    if (!empty($exam['prompt_id'])) {
        $prompt = Prompt::find($exam['prompt_id']);
    }

    $isLevels = Grading::examScale($exam) === Grading::SCALE_LEVELS;
    if ($isLevels) {
        $levelComparison = $this->levelComparison((int)$examId, $exam);
        $comparisonData = $levelComparison['rows'];
        $modelsFound = $levelComparison['models'];
        require __DIR__ . '/../views/docent/exam_comparison.php';
        return;
    }
    
    $pdo = Database::connect();
    // Haal antwoorden op die zowel door docent als AI zijn beoordeeld
    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT sa.id, COALESCE(u.name, se.guest_name, 'Gast') as student_name, q.question_text, sa.teacher_score, sa.ai_feedback,
                   " . AnswerAssessment::agenticScoreSql('sa') . " AS agentic_score
            FROM student_answers sa
            JOIN student_exams se ON sa.student_exam_id = se.id
            LEFT JOIN users u ON se.student_id = u.id
            JOIN questions q ON sa.question_id = q.id
            WHERE se.exam_id = ?
            AND sa.teacher_score IS NOT NULL
        ) WHERE ai_feedback IS NOT NULL OR agentic_score IS NOT NULL
    ");
    $stmt->execute([$examId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $comparisonData = [];
    $modelsFound = [];
    $stats = [
        'Docent' => ['scores' => []]
    ];
    $studentScores = [];

    foreach ($rows as $row) {
        $entry = [
            'student' => $row['student_name'],
            'question' => $row['question_text'],
            'teacher_score' => (int)$row['teacher_score'],
            'models' => []
        ];

        $stats['Docent']['scores'][] = (int)$row['teacher_score'];
        
        if (!isset($studentScores[$row['student_name']])) {
            $studentScores[$row['student_name']] = ['Docent' => []];
        }
        $studentScores[$row['student_name']]['Docent'][] = (int)$row['teacher_score'];

        // AI-scores per bron: modellen uit ai_feedback plus de agentic beoordeling
        foreach (StudentAnswer::aiScores($row['ai_feedback'], $row['agentic_score']) as $modelName => $score) {
            $entry['models'][$modelName] = $score;
            $modelsFound[$modelName] = true;

            if (!isset($stats[$modelName])) {
                $stats[$modelName] = ['scores' => []];
            }
            $stats[$modelName]['scores'][] = $score;

            if (!isset($studentScores[$row['student_name']][$modelName])) {
                $studentScores[$row['student_name']][$modelName] = [];
            }
            $studentScores[$row['student_name']][$modelName][] = $score;
        }

        $comparisonData[] = $entry;
    }

    // Bereken gemiddelden per student
    $studentAverages = [];
    foreach ($studentScores as $student => $judges) {
        foreach ($judges as $judge => $scores) {
            if (count($scores) > 0) {
                $studentAverages[$student][$judge] = array_sum($scores) / count($scores);
            }
        }
    }

    // Bereken statistieken
    foreach ($stats as $name => &$data) {
        $scores = $data['scores'];
        $count = count($scores);
        
        if ($count > 0) {
            // Gemiddelde
            $mean = array_sum($scores) / $count;
            $data['mean'] = $mean;

            // Standaarddeviatie (Sample)
            $variance = 0;
            foreach ($scores as $s) {
                $variance += pow($s - $mean, 2);
            }
            $data['std_dev'] = ($count > 1) ? sqrt($variance / ($count - 1)) : 0;

            // Vergelijking met docent (als dit geen docent is)
            if ($name !== 'Docent') {
                $maeSum = 0; // Mean Absolute Error
                $mseSum = 0; // Mean Squared Error (voor RMSE)
                $docentScores = $stats['Docent']['scores'];
                
                // Correlatie berekening variabelen
                $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0; $sumY2 = 0;
                $n = 0;

                // We moeten itereren over de originele rijen om paren te matchen
                foreach ($comparisonData as $row) {
                    if (isset($row['models'][$name])) {
                        $x = $row['teacher_score'];
                        $y = $row['models'][$name];
                        
                        $maeSum += abs($x - $y);
                        $mseSum += pow($x - $y, 2);

                        $sumX += $x;
                        $sumY += $y;
                        $sumXY += ($x * $y);
                        $sumX2 += ($x * $x);
                        $sumY2 += ($y * $y);
                        $n++;
                    }
                }

                $data['mae'] = ($n > 0) ? $maeSum / $n : 0;
                $data['rmse'] = ($n > 0) ? sqrt($mseSum / $n) : 0;
                
                // Pearson Correlatie
                $numerator = $n * $sumXY - $sumX * $sumY;
                $denominator = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
                $data['correlation'] = ($denominator != 0) ? $numerator / $denominator : 0;
            }
        }
    }

    require __DIR__ . '/../views/docent/exam_comparison.php';
  }

  /**
   * Exports the comparison data to a CSV file.
   * @param int $examId
   */
  public function exportExamComparison($examId) {
    requireRole('docent');

    $this->checkExamOwnership($examId);

    $exam = Exam::find($examId);
    $questions = Question::allByExam($examId);
    
    $prompt = null;
    if (!empty($exam['prompt_id'])) {
        $prompt = Prompt::find($exam['prompt_id']);
    }

    if (Grading::examScale($exam) === Grading::SCALE_LEVELS) {
        $this->exportLevelComparison((int)$examId, $exam);
        exit;
    }
    
    $pdo = Database::connect();
    // Haal antwoorden op die zowel door docent als AI zijn beoordeeld
    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT sa.id, COALESCE(u.name, se.guest_name, 'Gast') as student_name, q.question_text, sa.teacher_score, sa.ai_feedback,
                   " . AnswerAssessment::agenticScoreSql('sa') . " AS agentic_score
            FROM student_answers sa
            JOIN student_exams se ON sa.student_exam_id = se.id
            LEFT JOIN users u ON se.student_id = u.id
            JOIN questions q ON sa.question_id = q.id
            WHERE se.exam_id = ?
            AND sa.teacher_score IS NOT NULL
        ) WHERE ai_feedback IS NOT NULL OR agentic_score IS NOT NULL
    ");
    $stmt->execute([$examId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Bepaal welke modellen er zijn
    $modelsFound = [];
    $studentScores = [];

    foreach ($rows as $row) {
        if (!isset($studentScores[$row['student_name']])) {
            $studentScores[$row['student_name']] = ['Docent' => []];
        }
        $studentScores[$row['student_name']]['Docent'][] = (int)$row['teacher_score'];

        foreach (StudentAnswer::aiScores($row['ai_feedback'], $row['agentic_score']) as $modelName => $score) {
            $modelsFound[$modelName] = true;
            $studentScores[$row['student_name']][$modelName][] = $score;
        }
    }
    $modelNames = array_keys($modelsFound);
    sort($modelNames);

    // CSV Headers instellen
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="comparison_' . preg_replace('/[^a-z0-9]/i', '_', $exam['title']) . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM voor Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header rij
    $headers = ['Student', 'Vraag', 'Docent Score'];
    foreach ($modelNames as $model) {
        $headers[] = csvSafe($model . ' Score');
        $headers[] = csvSafe($model . ' Verschil');
    }
    fputcsv($output, $headers, ';');

    foreach ($rows as $row) {
        $rowModels = StudentAnswer::aiScores($row['ai_feedback'], $row['agentic_score']);

        $csvRow = [
            csvSafe($row['student_name']),
            csvSafe($row['question_text']),
            $row['teacher_score']
        ];

        foreach ($modelNames as $model) {
            if (isset($rowModels[$model])) {
                $csvRow[] = $rowModels[$model];
                $csvRow[] = $rowModels[$model] - $row['teacher_score'];
            } else {
                $csvRow[] = '';
                $csvRow[] = '';
            }
        }
        fputcsv($output, $csvRow, ';');
    }
    
    // Voeg eindscores toe
    fputcsv($output, [], ';');
    fputcsv($output, ['EINDSCORES (GEMIDDELDEN)'], ';');

    $summaryHeaders = ['Student', 'Docent Gemiddelde'];
    foreach ($modelNames as $model) {
        $summaryHeaders[] = csvSafe($model . ' Gemiddelde');
        $summaryHeaders[] = csvSafe($model . ' Verschil');
    }
    fputcsv($output, $summaryHeaders, ';');

    foreach ($studentScores as $student => $judges) {
        $csvRow = [csvSafe($student)];
        $docentAvg = isset($judges['Docent']) && count($judges['Docent']) > 0 ? array_sum($judges['Docent']) / count($judges['Docent']) : null;
        
        $csvRow[] = $docentAvg !== null ? number_format($docentAvg, 1, ',', '.') : '';

        foreach ($modelNames as $model) {
            if (isset($judges[$model]) && count($judges[$model]) > 0) {
                $modelAvg = array_sum($judges[$model]) / count($judges[$model]);
                $csvRow[] = number_format($modelAvg, 1, ',', '.');
                if ($docentAvg !== null) {
                    $csvRow[] = number_format($modelAvg - $docentAvg, 1, ',', '.');
                } else {
                    $csvRow[] = '';
                }
            } else {
                $csvRow[] = '';
                $csvRow[] = '';
            }
        }
        fputcsv($output, $csvRow, ';');
    }

    fclose($output);
    exit;
  }

  /**
   * Vergelijking docent tegen AI bij een toets met niveaus. Alleen antwoorden met
   * een docentniveau en minstens één AI-niveau tellen mee.
   * @return array rows (student, question, teacher_level, models bron => niveau),
   *         models (bron => true), crosstabs per bron (4×4 docentniveau × AI-niveau,
   *         n, exact- en voldoende/onvoldoende-percentage, gemiddelde afwijking in
   *         niveaus) en student_grades (student => bron of Docent => cijfer over de
   *         vergeleken antwoorden, met het puntenschema van de toets)
   */
  private function levelComparison(int $examId, array $exam): array {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("
          SELECT * FROM (
              SELECT sa.id, COALESCE(u.name, se.guest_name, 'Gast') AS student_name, q.question_text, sa.teacher_level, sa.ai_feedback,
                     " . AnswerAssessment::agenticLevelSql('sa') . " AS agentic_level
              FROM student_answers sa
              JOIN student_exams se ON sa.student_exam_id = se.id
              LEFT JOIN users u ON se.student_id = u.id
              JOIN questions q ON sa.question_id = q.id
              WHERE se.exam_id = ?
              AND sa.teacher_level IS NOT NULL
          ) WHERE ai_feedback IS NOT NULL OR agentic_level IS NOT NULL
          ORDER BY id ASC
      ");
      $stmt->execute([$examId]);

      $rows = [];
      $models = [];
      $levelsByStudent = [];
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
          if (!Grading::isLevel($row['teacher_level'])) {
              continue;
          }
          $aiLevels = StudentAnswer::aiLevels($row['ai_feedback'], $row['agentic_level']);
          if (!$aiLevels) {
              continue;
          }
          $rows[] = [
              'student' => $row['student_name'],
              'question' => $row['question_text'],
              'teacher_level' => $row['teacher_level'],
              'models' => $aiLevels,
          ];
          $levelsByStudent[$row['student_name']]['Docent'][] = $row['teacher_level'];
          foreach ($aiLevels as $source => $level) {
              $models[$source] = true;
              $levelsByStudent[$row['student_name']][$source][] = $level;
          }
      }
      ksort($models);

      $crosstabs = [];
      foreach (array_keys($models) as $source) {
          $matrix = array_fill(0, count(Grading::LEVELS), array_fill(0, count(Grading::LEVELS), 0));
          $n = $exact = $passAgree = $distance = 0;
          foreach ($rows as $row) {
              if (!isset($row['models'][$source])) {
                  continue;
              }
              $t = Grading::levelIndex($row['teacher_level']);
              $a = Grading::levelIndex($row['models'][$source]);
              $matrix[$t][$a]++;
              $n++;
              $exact += $t === $a ? 1 : 0;
              $passAgree += ($t > 0) === ($a > 0) ? 1 : 0;
              $distance += abs($t - $a);
          }
          $crosstabs[$source] = [
              'matrix' => $matrix,
              'n' => $n,
              'exact_pct' => $n > 0 ? 100 * $exact / $n : null,
              'pass_pct' => $n > 0 ? 100 * $passAgree / $n : null,
              'mean_distance' => $n > 0 ? $distance / $n : null,
          ];
      }

      $scheme = !empty($exam['grading_scheme_id']) ? GradingScheme::find($exam['grading_scheme_id']) : null;
      $studentGrades = [];
      foreach ($levelsByStudent as $student => $judges) {
          foreach ($judges as $judge => $levels) {
              $studentGrades[$student][$judge] = $scheme ? Grading::grade($levels, $scheme) : null;
          }
      }

      return ['rows' => $rows, 'models' => $models, 'crosstabs' => $crosstabs, 'student_grades' => $studentGrades, 'scheme' => $scheme ?: null];
  }

  /** CSV-export van levelComparison(): per antwoord de niveaus en "gelijk", daarna de cijfers per student. */
  private function exportLevelComparison(int $examId, array $exam): void {
      $data = $this->levelComparison($examId, $exam);
      $modelNames = array_keys($data['models']);

      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="comparison_' . preg_replace('/[^a-z0-9]/i', '_', $exam['title']) . '_' . date('Y-m-d') . '.csv"');
      $output = fopen('php://output', 'w');
      fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

      $headers = ['Student', 'Vraag', 'Docentniveau'];
      foreach ($modelNames as $model) {
          $headers[] = csvSafe($model . ' Niveau');
          $headers[] = csvSafe($model . ' Gelijk');
      }
      fputcsv($output, $headers, ';');

      foreach ($data['rows'] as $row) {
          $csvRow = [csvSafe($row['student']), csvSafe($row['question']), csvSafe($row['teacher_level'])];
          foreach ($modelNames as $model) {
              if (isset($row['models'][$model])) {
                  $csvRow[] = csvSafe($row['models'][$model]);
                  $csvRow[] = $row['models'][$model] === $row['teacher_level'] ? 'ja' : 'nee';
              } else {
                  $csvRow[] = '';
                  $csvRow[] = '';
              }
          }
          fputcsv($output, $csvRow, ';');
      }

      fputcsv($output, [], ';');
      fputcsv($output, ['CIJFERS PER STUDENT (OVER DE VERGELEKEN ANTWOORDEN)'], ';');
      $summaryHeaders = ['Student', 'Docent Cijfer'];
      foreach ($modelNames as $model) {
          $summaryHeaders[] = csvSafe($model . ' Cijfer');
          $summaryHeaders[] = csvSafe($model . ' Verschil');
      }
      fputcsv($output, $summaryHeaders, ';');
      foreach ($data['student_grades'] as $student => $grades) {
          $docent = $grades['Docent'] ?? null;
          $csvRow = [csvSafe($student), $docent !== null ? Grading::formatGrade($docent) : ''];
          foreach ($modelNames as $model) {
              $grade = $grades[$model] ?? null;
              $csvRow[] = $grade !== null ? Grading::formatGrade($grade) : '';
              $csvRow[] = ($grade !== null && $docent !== null) ? number_format($grade - $docent, 1, ',', '') : '';
          }
          fputcsv($output, $csvRow, ';');
      }
      fclose($output);
  }

  /**
   * Reden waarom de AI-resultaten van deze toetspoging niet opnieuw kunnen worden
   * uitgevoerd, of null als het kan. Schrijfrecht op de toets controleert de aanroeper.
   * @param bool $isIntegration poging via een externe koppeling (contract 9: de
   *        status mag daar niet van graded terug naar grading)
   */
  private function aiResetBlockedReason(array $studentExam, array $exam, bool $isIntegration): ?string {
      if (empty($studentExam['completed_at'])) {
          return 'Deze toetspoging is nog niet ingeleverd.';
      }
      if ((int)$exam['ai_grading_enabled'] !== 1) {
          return 'AI-beoordeling staat uit voor deze toets. Zet die eerst aan; anders worden de AI-resultaten alleen verwijderd.';
      }
      if ($isIntegration) {
          return 'Deze poging komt van een externe koppeling. Daar kunnen de AI-resultaten niet opnieuw worden uitgevoerd, '
              . 'omdat de externe website de beoordeling al heeft ontvangen.';
      }
      return null;
  }

  /** True als de huidige docent het maximum aantal resets per uur heeft bereikt (één per reset, niet per antwoord). */
  private function aiResetRateLimited(): bool {
      return AuditLog::countRecent('ai_results_reset', 60, null, $_SESSION['name']) >= AI_RESULTS_RESET_MAX_PER_HOUR;
  }

  private function aiResetRateLimitMessage(): string {
      return 'Je hebt het afgelopen uur al ' . AI_RESULTS_RESET_MAX_PER_HOUR
          . ' keer AI-resultaten opnieuw laten uitvoeren. Probeer het later opnieuw.';
  }

  /**
   * Voert een reset van de AI-resultaten uit: alle antwoorden in één transactie
   * terug naar "net ingeleverd", daarna per poging de automatische agentic runs
   * starten (net als bij inleveren) en één auditregel.
   * @param string $scope 'answer', 'attempt' of 'exam'
   * @param array $answerIdsByAttempt student_exam_id => [student_answer_id, ...]
   * @return array tellingen: ai_feedback, agentic_runs, new_runs
   */
  private function performAiReset(string $scope, int $examId, array $answerIdsByAttempt, array $auditExtra = []): array {
      $answerIds = array_merge(...array_values($answerIdsByAttempt));
      $oldScores = array_filter(StudentAnswer::aiScoresByIds($answerIds));

      $counts = StudentAnswer::resetAiResults($answerIds);

      $newRuns = [];
      foreach (array_keys($answerIdsByAttempt) as $studentExamId) {
          try {
              $newRuns += AnswerAssessment::createAutomaticRuns((int)$studentExamId, ASSESSMENT_AUTO_START_BATCH);
          } catch (Throwable $e) {
              // De reset is al gedaan; de assessment-worker start de runs dan bij zijn volgende poll.
              error_log('Automatische agentic runs na reset mislukt: ' . $e->getMessage());
          }
      }

      AuditLog::log('ai_results_reset', array_merge([
          'scope' => $scope,
          'exam_id' => $examId,
          'student_exam_ids' => array_map('intval', array_keys($answerIdsByAttempt)),
          'student_answer_ids' => $answerIds,
          'ai_feedback_cleared' => $counts['ai_feedback'],
          'agentic_runs_superseded' => $counts['agentic_runs'],
          'old_ai_scores' => $oldScores,
          'new_runs' => $newRuns,
      ], $auditExtra));

      return $counts + ['new_runs' => count($newRuns)];
  }

  /**
   * Schaal, puntenschema en woordbeoordeling uit het toetsformulier.
   * Bij levels moet het schema bestaan; bij points zijn schema en vinkje leeg.
   * @return array [grading_scale, grading_scheme_id|null, show_grade_label 0|1]
   */
  private function readGradingSettings(): array {
      $scale = requestString($_POST, 'grading_scale', 20, Grading::SCALE_POINTS);
      if (!Grading::isScale($scale)) {
          abort(400, 'Ongeldige schaal.');
      }
      if ($scale === Grading::SCALE_POINTS) {
          return [$scale, null, 0];
      }
      $schemeId = requestInt($_POST, 'grading_scheme_id');
      if ($schemeId === null || !GradingScheme::find($schemeId)) {
          abort(400, 'Ongeldig puntenschema.');
      }
      return [$scale, $schemeId, isset($_POST['show_grade_label']) ? 1 : 0];
  }

  /** Prompt uit het toetsformulier: moet bestaan en dezelfde schaal hebben als de toets (B9). */
  private function readPromptId(string $gradingScale): ?int {
      $promptId = requestInt($_POST, 'prompt_id');
      if ($promptId === null) {
          return null;
      }
      $prompt = Prompt::find($promptId);
      if (!$prompt) {
          abort(400, 'Ongeldige prompt.');
      }
      if (($prompt['grading_scale'] ?? Grading::SCALE_POINTS) !== $gradingScale) {
          abort(400, 'Deze prompt hoort bij een andere schaal dan de toets.');
      }
      return $promptId;
  }

  /**
   * True als de huidige gebruiker de toets mag wijzigen/verwijderen (eigenaar of admin).
   */
  private function canEditExam($exam): bool {
      if (!$exam) return false;
      if ($_SESSION['role'] === 'admin') return true;
      return (int)$exam['docent_id'] === (int)$_SESSION['user_id'];
  }

  /**
   * Checks whether the current user may access the exam.
   * - lezen ($write = false): eigenaar, admin, of gedeelde toets (shared = 1)
   * - schrijven ($write = true): alleen eigenaar of admin
   * @param int|null $examId
   * @param bool $write
   */
  private function checkExamOwnership($examId, bool $write = false) {
      $exam = $examId !== null ? Exam::find($examId) : null;
      if (!$exam) {
          abort(404, 'Toets niet gevonden.');
      }
      if ($this->canEditExam($exam)) return;
      if (!$write && $exam['shared']) return;

      abort(403, $write
          ? 'Geen toegang: alleen de eigenaar van deze toets mag deze wijzigen.'
          : 'Geen toegang: U bent niet de eigenaar van deze toets.');
  }

  /**
   * Checks if the current user is allowed to grade this exam.
   * Docents can only grade their own or shared exams. Beoordelaars and Admins can grade all.
   * @param int|null $examId
   */
  private function checkGradingPermission($examId) {
      if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'beoordelaar') return;
      
      $exam = $examId !== null ? Exam::find($examId) : null;
      if (!$exam || ((int)$exam['docent_id'] !== (int)$_SESSION['user_id'] && !$exam['shared'])) {
          abort(403, 'Geen toegang: U mag deze toets niet beoordelen.');
      }
  }

}

?>
