<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Questions.php';
require_once __DIR__ . '/../models/Prompt.php';
require_once __DIR__ . '/../models/StudentAnswer.php';

/**
 * Class DocentController
 * Handles actions related to teachers (docenten) and graders (beoordelaars).
 */
class DocentController {
  
  /**
   * Displays the dashboard for the docent.
   */
  public function dashboard() {
    requireLogin();
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
    requireLogin();
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
    requireLogin();
    requireRole('docent');
    
    $promptId = !empty($_POST['prompt_id']) ? $_POST['prompt_id'] : null;
    $aiGradingEnabled = isset($_POST['ai_grading_enabled']) ? 1 : 0;
    $shared = isset($_POST['shared']) ? 1 : 0;

    Exam::create(
		 $_POST['title'],
		 $_POST['description'],
		 $_SESSION['user_id'],
         $promptId,
         $aiGradingEnabled,
         $shared
		 );
    AuditLog::log('exam_create', [
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'prompt_id' => $promptId,
        'ai_grading_enabled' => $aiGradingEnabled,
        'shared' => $shared
    ]);
    
    header('Location: /?action=docent_dashboard');
    exit;
  }
  
  /**
   * Shows the form to edit an existing exam.
   */
  public function editExam() {
    requireLogin();
    requireRole('docent');
    
    $this->checkExamOwnership($_GET['id']);
    
    $exam = Exam::find($_GET['id']);
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
    requireLogin();
    requireRole('docent');
    
    $this->checkExamOwnership($_POST['id']);
    
    $currentExam = Exam::find($_POST['id']);
    $promptId = !empty($_POST['prompt_id']) ? $_POST['prompt_id'] : null;
    $aiGradingEnabled = isset($_POST['ai_grading_enabled']) ? 1 : 0;
    $shared = isset($_POST['shared']) ? 1 : 0;
    
    Exam::update(
		 $_POST['id'],
		 $_POST['title'],
		 $_POST['description'],
         $promptId,
         $aiGradingEnabled,
         $shared
		 );

    $changes = ['id' => $_POST['id']];
    if ($currentExam['title'] !== $_POST['title']) {
        $changes['title'] = ['old' => $currentExam['title'], 'new' => $_POST['title']];
    }
    if ($currentExam['description'] !== $_POST['description']) {
        $changes['description'] = ['old' => $currentExam['description'], 'new' => $_POST['description']];
    }
    if ($currentExam['prompt_id'] != $promptId) {
        $changes['prompt_id'] = ['old' => $currentExam['prompt_id'], 'new' => $promptId];
        
        // Reset AI feedback for all students for this exam to ensure consistency
        StudentAnswer::clearAiFeedbackByExam($_POST['id']);
        AuditLog::log('exam_ai_feedback_cleared', ['exam_id' => $_POST['id'], 'reason' => 'prompt_change']);
    }
    if ($currentExam['ai_grading_enabled'] != $aiGradingEnabled) {
        $changes['ai_grading_enabled'] = ['old' => $currentExam['ai_grading_enabled'], 'new' => $aiGradingEnabled];
    }
    if ($currentExam['shared'] != $shared) {
        $changes['shared'] = ['old' => $currentExam['shared'], 'new' => $shared];
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
    requireLogin();
    requireRole('docent');
    
    $this->checkExamOwnership($_GET['id']);
    
    AuditLog::log('exam_delete', ['id' => $_GET['id']]);
    Exam::delete($_GET['id']);
    header('Location: /?action=docent_dashboard');
    exit;
  }

  /**
   * Duplicates an exam including questions and student answers (but resets AI feedback).
   */
  public function duplicateExam() {
    validateCsrfToken();
    requireLogin();
    requireRole('docent');
    
    $this->checkExamOwnership($_GET['id']);
    
    try {
        Exam::duplicate($_GET['id']);
    } catch (Exception $e) {
        // Foutafhandeling (optioneel: logging of sessie bericht)
    }
    
    header('Location: /?action=docent_dashboard');
    exit;
  }

  /**
   * Lists all questions for a specific exam.
   * @param int $examId
   */
  public function questions($examId) {
    requireLogin();
    requireRole('docent');
    
    $this->checkExamOwnership($examId);
    
    $exam = Exam::find($examId);
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
    requireLogin();
    requireRole('docent');
    
    $this->checkExamOwnership($_GET['exam_id']);
    
    $examId = $_GET['exam_id'];
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
    requireLogin();
    requireRole('docent');
    
    $this->checkExamOwnership($_POST['exam_id']);
    
    Question::create(
		     $_POST['exam_id'],
		     $_POST['question_text'],
		     $_POST['criteria']
		     );
    AuditLog::log('question_create', [
        'exam_id' => $_POST['exam_id'], 
        'question_text' => $_POST['question_text'],
        'criteria' => $_POST['criteria']
    ]);
    
    header('Location: /?action=questions&exam_id=' . $_POST['exam_id']);
    exit;
  }
  
  /**
   * Shows the form to edit a question.
   */
  public function editQuestion() {
    requireLogin();
    requireRole('docent');
    
    $question = Question::find($_GET['id']);
    $this->checkExamOwnership($question['exam_id']);
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
    requireLogin();
    requireRole('docent');
    
    $currentQuestion = Question::find($_POST['id']);
    $this->checkExamOwnership($currentQuestion['exam_id']);

    Question::update(
		     $_POST['id'],
		     $_POST['question_text'],
		     $_POST['criteria']
		     );

    $changes = ['id' => $_POST['id']];
    if ($currentQuestion['question_text'] !== $_POST['question_text']) {
        $changes['question_text'] = ['old' => $currentQuestion['question_text'], 'new' => $_POST['question_text']];
    }
    if ($currentQuestion['criteria'] !== $_POST['criteria']) {
        $changes['criteria'] = ['old' => $currentQuestion['criteria'], 'new' => $_POST['criteria']];
    }

    AuditLog::log('question_update', $changes);
    
    header('Location: /?action=questions&exam_id=' . $_POST['exam_id']);
    exit;
  }
  
  /**
   * Deletes a question.
   */
  public function deleteQuestion() {
    validateCsrfToken();
    requireLogin();
    requireRole('docent');
    
    $question = Question::find($_GET['id']);
    $this->checkExamOwnership($question['exam_id']);
    $examId = $question['exam_id'];
    AuditLog::log('question_delete', [
        'id' => $_GET['id'],
        'question_text' => $question['question_text']
    ]);
    Question::delete($_GET['id']);
    
    header('Location: /?action=questions&exam_id=' . $examId);
    exit;
  }

  /**
   * Views the results of all students for a specific exam.
   * @param int $examId
   */
public function viewExamResults($examId) {
    requireLogin();
    requireRole('docent');
    
    $this->checkExamOwnership($examId);

    $studentExams = StudentExam::findWithStudentDetailsByExam($examId);
    require __DIR__ . '/../views/docent/exam_results.php';
}

  /**
   * Views the detailed answers of a specific student exam attempt.
   * @param int $studentExamId
   */
public function viewStudentAnswers($studentExamId) {
    requireLogin();
        requireRole('docent');

    $studentExam = StudentExam::find($studentExamId);
    $this->checkExamOwnership($studentExam['exam_id']);

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
    requireLogin();
    requireRole('beoordelaar');

    $studentExam = StudentExam::find($studentExamId);
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
    requireLogin();
    requireRole('beoordelaar');

    $studentAnswerId = $_POST['student_answer_id'];
    $score = $_POST['teacher_score'] === '' ? null : $_POST['teacher_score'];
    $feedback = $_POST['teacher_feedback'];
    $studentExamId = $_POST['student_exam_id'];
    
    $studentExam = StudentExam::find($studentExamId);
    if ($studentExam) {
        $this->checkGradingPermission($studentExam['exam_id']);
    }

    $redirectAction = $_POST['redirect_action'] ?? 'view_student_answers';

    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE student_answers SET teacher_score = ?, teacher_feedback = ? WHERE id = ?");
    $stmt->execute([$score, $feedback, $studentAnswerId]);

    header('Location: /?action=' . $redirectAction . '&student_exam_id=' . $studentExamId . '#answer-' . $studentAnswerId);
    exit;
  }

  /**
   * Deletes a student's exam attempt.
   */
  public function deleteStudentExam() {
    validateCsrfToken();
    requireLogin();
    requireRole('docent');
    
    $studentExamId = $_GET['student_exam_id'] ?? null;
    $studentExam = $studentExamId ? StudentExam::find($studentExamId) : null;
    
    if ($studentExam) {
        $this->checkExamOwnership($studentExam['exam_id']);
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
    requireLogin();
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
    requireLogin();
    requireRole('docent');
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;

    $pdo = Database::connect();
    $totalRecords = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $stmt = $pdo->prepare("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
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
    requireLogin();
    
    if ($_SESSION['role'] !== 'admin') {
        die("Geen toegang. Alleen admins kunnen de log wissen.");
    }

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
    requireLogin();
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
    requireLogin();
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
        $headers[] = $model . ' Score';
        $headers[] = $model . ' Verschil';
    }
    fputcsv($output, $headers, ';');

    foreach ($rows as $row) {
        $rowModels = [];
        preg_match_all('/Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)/is', $row['ai_feedback'], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $rowModels[trim($match[1])] = (int)$match[2];
        }

        $csvRow = [
            $row['student_name'],
            $row['question_text'],
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
        $summaryHeaders[] = $model . ' Gemiddelde';
        $summaryHeaders[] = $model . ' Verschil';
    }
    fputcsv($output, $summaryHeaders, ';');

    foreach ($studentScores as $student => $judges) {
        $csvRow = [$student];
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
   * Checks if the current user is the owner of the exam (or admin).
   * @param int $examId
   */
  private function checkExamOwnership($examId) {
      if ($_SESSION['role'] === 'admin') return;
      
      $exam = Exam::find($examId);
      if (!$exam || ($exam['docent_id'] != $_SESSION['user_id'] && !$exam['shared'])) {
          die("Geen toegang: U bent niet de eigenaar van deze toets.");
      }
  }

  /**
   * Checks if the current user is allowed to grade this exam.
   * Docents can only grade their own exams. Beoordelaars and Admins can grade all.
   * @param int $examId
   */
  private function checkGradingPermission($examId) {
      if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'beoordelaar') return;
      
      $exam = Exam::find($examId);
      if (!$exam || ($exam['docent_id'] != $_SESSION['user_id'] && !$exam['shared'])) {
          die("Geen toegang: U mag deze toets niet beoordelen.");
      }
  }

}

?>
