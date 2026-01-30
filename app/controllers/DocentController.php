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
    
    $exams = Exam::allByDocent($_SESSION['user_id']);
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
    require __DIR__ . '/../views/docent/exam_form.php';
  }
  
  /**
   * Stores a newly created exam in the database.
   */
  public function storeExam() {
    requireLogin();
    requireRole('docent');
    
    Exam::create(
		 $_POST['title'],
		 $_POST['description'],
		 $_SESSION['user_id']
		 );
    AuditLog::log('exam_create', [
        'title' => $_POST['title'],
        'description' => $_POST['description']
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
    
    $exam = Exam::find($_GET['id']);
    $action = 'exam_update';
    $title = 'Toets bewerken';
    require __DIR__ . '/../views/docent/exam_form.php';
  }
  
  /**
   * Updates an existing exam in the database.
   */
  public function updateExam() {
    requireLogin();
    requireRole('docent');
    
    $currentExam = Exam::find($_POST['id']);
    
    Exam::update(
		 $_POST['id'],
		 $_POST['title'],
		 $_POST['description']
		 );

    $changes = ['id' => $_POST['id']];
    if ($currentExam['title'] !== $_POST['title']) {
        $changes['title'] = ['old' => $currentExam['title'], 'new' => $_POST['title']];
    }
    if ($currentExam['description'] !== $_POST['description']) {
        $changes['description'] = ['old' => $currentExam['description'], 'new' => $_POST['description']];
    }

    AuditLog::log('exam_update', $changes);
    
    header('Location: /?action=docent_dashboard');
    exit;
  }
  
  /**
   * Deletes an exam.
   */
  public function deleteExam() {
    requireLogin();
    requireRole('docent');
    
    AuditLog::log('exam_delete', ['id' => $_GET['id']]);
    Exam::delete($_GET['id']);
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
    
    $exam = Exam::find($examId);
    $questions = Question::allByExam($examId);
    
    require __DIR__ . '/../views/docent/questions.php';
  }
  
  /**
   * Shows the form to create a new question.
   */
  public function createQuestion() {
    requireLogin();
    requireRole('docent');
    
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
    requireLogin();
    requireRole('docent');
    
    Question::create(
		     $_POST['exam_id'],
		     $_POST['question_text'],
		     $_POST['model_answer'],
		     $_POST['criteria']
		     );
    AuditLog::log('question_create', [
        'exam_id' => $_POST['exam_id'], 
        'question_text' => $_POST['question_text'],
        'model_answer' => $_POST['model_answer'],
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
    $examId = $question['exam_id'];
    $action = 'question_update';
    $title = 'Vraag bewerken';
    require __DIR__ . '/../views/docent/question_form.php';
  }
  
  /**
   * Updates an existing question.
   */
  public function updateQuestion() {
    requireLogin();
    requireRole('docent');
    
    $currentQuestion = Question::find($_POST['id']);

    Question::update(
		     $_POST['id'],
		     $_POST['question_text'],
		     $_POST['model_answer'],
		     $_POST['criteria']
		     );

    $changes = ['id' => $_POST['id']];
    if ($currentQuestion['question_text'] !== $_POST['question_text']) {
        $changes['question_text'] = ['old' => $currentQuestion['question_text'], 'new' => $_POST['question_text']];
    }
    if ($currentQuestion['model_answer'] !== $_POST['model_answer']) {
        $changes['model_answer'] = ['old' => $currentQuestion['model_answer'], 'new' => $_POST['model_answer']];
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
    requireLogin();
    requireRole('docent');
    
    $question = Question::find($_GET['id']);
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

    $pdo = Database::connect();
        $stmt = $pdo->prepare("
        SELECT sa.id, q.question_text, sa.answer, q.model_answer, q.criteria, sa.ai_feedback, sa.teacher_score, sa.teacher_feedback
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.student_exam_id = ?
    ");
        $stmt->execute([$studentExamId]);
	    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT sa.id, q.question_text, sa.answer, q.model_answer, q.criteria, sa.teacher_score, sa.teacher_feedback
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
    requireLogin();
    requireRole('beoordelaar');

    $studentAnswerId = $_POST['student_answer_id'];
    $score = $_POST['teacher_score'] === '' ? null : $_POST['teacher_score'];
    $feedback = $_POST['teacher_feedback'];
    $studentExamId = $_POST['student_exam_id'];
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
    requireLogin();
    requireRole('docent');
    
    $studentExamId = $_GET['student_exam_id'] ?? null;
    $studentExam = $studentExamId ? StudentExam::find($studentExamId) : null;
    
    if ($studentExam) {
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
        SELECT se.id, se.completed_at, u.name as student_name, e.title as exam_title,
               COUNT(sa.id) as total_answers,
               COUNT(sa.teacher_score) as graded_answers
        FROM student_exams se
        JOIN users u ON se.student_id = u.id
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

}

?>
