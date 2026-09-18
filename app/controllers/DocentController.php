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
    require __DIR__ . '/../views/docent/dashboard.php';
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
    $promptId = requestInt($_POST, 'prompt_id');
    if ($promptId !== null && !Prompt::find($promptId)) {
        abort(400, 'Ongeldige prompt.');
    }
    $aiGradingEnabled = isset($_POST['ai_grading_enabled']) ? 1 : 0;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $published = isset($_POST['published']) ? 1 : 0;

    Exam::create(
		 $title,
		 $description,
		 $_SESSION['user_id'],
         $promptId,
         $aiGradingEnabled,
         $shared,
         $published
		 );
    AuditLog::log('exam_create', [
        'title' => $title,
        'description' => $description,
        'prompt_id' => $promptId,
        'ai_grading_enabled' => $aiGradingEnabled,
        'shared' => $shared,
        'published' => $published
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
    $promptId = requestInt($_POST, 'prompt_id');
    if ($promptId !== null && !Prompt::find($promptId)) {
        abort(400, 'Ongeldige prompt.');
    }
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
         $published
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

    AuditLog::log('exam_update', $changes);
    
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
    $canEdit = $this->canEditExam(Exam::find($studentExam['exam_id']));

    // Genereer een deelbare link voor gaststudenten zodat zij hun resultaat kunnen inzien
    $shareableLink = null;
    if ($studentExam['student_id'] === null && !empty($studentExam['access_token'])) {
        $shareableLink = appBaseUrl() . "/?action=student_view_results&student_exam_id={$studentExamId}&token={$studentExam['access_token']}";
    }

    $pdo = Database::connect();
        $stmt = $pdo->prepare("
        SELECT sa.id, q.question_text, sa.answer, q.criteria, sa.ai_feedback, sa.teacher_score, sa.teacher_feedback
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.student_exam_id = ?
    ");
        $stmt->execute([$studentExamId]);
	    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Bereken eindscore (gemiddelde)
    $totalScore = 0;
    $scoredCount = 0;
    $aiModelScores = [];

    foreach ($answers as $a) {
        if (isset($a['teacher_score']) && $a['teacher_score'] !== null && $a['teacher_score'] !== '') {
            $totalScore += (float)$a['teacher_score'];
            $scoredCount++;
        }

        if (!empty($a['ai_feedback'])) {
            preg_match_all('/Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)/is', $a['ai_feedback'], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $modelName = trim($match[1]);
                $score = (int)$match[2];
                if (!isset($aiModelScores[$modelName])) {
                    $aiModelScores[$modelName] = [];
                }
                $aiModelScores[$modelName][] = $score;
            }
        }
    }
    $finalScore = $scoredCount > 0 ? $totalScore / $scoredCount : null;

    $finalAiScores = [];
    foreach ($aiModelScores as $model => $scores) {
        if (count($scores) > 0) {
            $finalAiScores[$model] = array_sum($scores) / count($scores);
        }
    }
    ksort($finalAiScores);

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

    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT sa.id, q.question_text, sa.answer, q.criteria, sa.teacher_score, sa.teacher_feedback
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.student_exam_id = ?
    ");
    $stmt->execute([$studentExamId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $scoreRaw = trim(requestString($_POST, 'teacher_score', 10));
    if ($scoreRaw === '') {
        $score = null;
    } elseif (preg_match('/^(10|[0-9])$/', $scoreRaw)) {
        $score = (int)$scoreRaw;
    } else {
        abort(400, 'Score moet een geheel getal van 0 t/m 10 zijn.');
    }
    $feedback = requestString($_POST, 'teacher_feedback');

    $redirectAction = requestString($_POST, 'redirect_action', 40, 'view_student_answers');
    if (!in_array($redirectAction, ['view_student_answers', 'grade_student_exam'], true)) {
        $redirectAction = 'view_student_answers';
    }
    // Docenten mogen alleen via de blinde beoordeling naar de docentweergave als ze de toets mogen inzien
    if ($redirectAction === 'view_student_answers' && $_SESSION['role'] === 'beoordelaar') {
        $redirectAction = 'grade_student_exam';
    }

    StudentAnswer::updateTeacherGrade($studentAnswerId, $score, $feedback);

    $changes = ['student_answer_id' => $studentAnswerId, 'student_exam_id' => $studentExamId];
    if ((string)($answer['teacher_score'] ?? '') !== (string)($score ?? '')) {
        $changes['teacher_score'] = ['old' => $answer['teacher_score'], 'new' => $score];
    }
    if (($answer['teacher_feedback'] ?? '') !== $feedback) {
        $changes['teacher_feedback'] = ['old' => $answer['teacher_feedback'] ?? '', 'new' => $feedback];
    }
    AuditLog::log('teacher_grade', $changes);

    header('Location: /?action=' . $redirectAction . '&student_exam_id=' . $studentExamId . '#answer-' . $studentAnswerId);
    exit;
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
   * Shows a list of assessments pending grading.
   */
  public function pendingAssessments() {
    requireRole('beoordelaar');

    $pdo = Database::connect();
    // Haal toetsen op die ingeleverd zijn, gekoppeld aan deze docent, en nog niet volledig beoordeeld zijn.
    $sql = "
        SELECT se.id, se.completed_at, COALESCE(u.name, se.guest_name, 'Gast') as student_name, e.title as exam_title,
               COUNT(sa.id) as total_answers,
               COUNT(sa.teacher_score) as graded_answers
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

    $pdo = Database::connect();
    $pdo->exec("DELETE FROM audit_log");

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
    
    $pdo = Database::connect();
    // Haal antwoorden op die zowel door docent als AI zijn beoordeeld
    $stmt = $pdo->prepare("
        SELECT sa.id, COALESCE(u.name, se.guest_name, 'Gast') as student_name, q.question_text, sa.teacher_score, sa.ai_feedback
        FROM student_answers sa
        JOIN student_exams se ON sa.student_exam_id = se.id
        LEFT JOIN users u ON se.student_id = u.id
        JOIN questions q ON sa.question_id = q.id
        WHERE se.exam_id = ? 
        AND sa.teacher_score IS NOT NULL 
        AND sa.ai_feedback IS NOT NULL
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

        // Parse AI feedback string
        // Verwacht formaat uit Python script: "Model: [naam] ... Aantal punten: [score]"
        preg_match_all('/Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)/is', $row['ai_feedback'], $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $modelName = trim($match[1]);
            $score = (int)$match[2];

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
    
    $pdo = Database::connect();
    // Haal antwoorden op die zowel door docent als AI zijn beoordeeld
    $stmt = $pdo->prepare("
        SELECT sa.id, COALESCE(u.name, se.guest_name, 'Gast') as student_name, q.question_text, sa.teacher_score, sa.ai_feedback
        FROM student_answers sa
        JOIN student_exams se ON sa.student_exam_id = se.id
        LEFT JOIN users u ON se.student_id = u.id
        JOIN questions q ON sa.question_id = q.id
        WHERE se.exam_id = ? 
        AND sa.teacher_score IS NOT NULL 
        AND sa.ai_feedback IS NOT NULL
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

        preg_match_all('/Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)/is', $row['ai_feedback'], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $modelsFound[trim($match[1])] = true;
            $modelName = trim($match[1]);
            $modelsFound[$modelName] = true;
            if (!isset($studentScores[$row['student_name']][$modelName])) {
                $studentScores[$row['student_name']][$modelName] = [];
            }
            $studentScores[$row['student_name']][$modelName][] = (int)$match[2];
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
        $rowModels = [];
        preg_match_all('/Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)/is', $row['ai_feedback'], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $rowModels[trim($match[1])] = (int)$match[2];
        }

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
