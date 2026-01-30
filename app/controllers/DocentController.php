<?php

require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Questions.php';

class DocentController {
  
  public function dashboard() {
    requireLogin();
    requireRole('docent');
    
    $exams = Exam::allByDocent($_SESSION['user_id']);
    require __DIR__ . '/../views/docent/dashboard.php';
  }
  
  public function createExam() {
    requireLogin();
    requireRole('docent');
    
    $exam = null;
    $action = 'exam_store';
    $title = 'Nieuwe toets';
    require __DIR__ . '/../views/docent/exam_form.php';
  }
  
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
  
  public function editExam() {
    requireLogin();
    requireRole('docent');
    
    $exam = Exam::find($_GET['id']);
    $action = 'exam_update';
    $title = 'Toets bewerken';
    require __DIR__ . '/../views/docent/exam_form.php';
  }
  
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
  
  public function deleteExam() {
    requireLogin();
    requireRole('docent');
    
    AuditLog::log('exam_delete', ['id' => $_GET['id']]);
    Exam::delete($_GET['id']);
    header('Location: /?action=docent_dashboard');
    exit;
  }

  public function questions($examId) {
    requireLogin();
    requireRole('docent');
    
    $exam = Exam::find($examId);
    $questions = Question::allByExam($examId);
    
    require __DIR__ . '/../views/docent/questions.php';
  }
  
  public function createQuestion() {
    requireLogin();
    requireRole('docent');
    
    $examId = $_GET['exam_id'];
    $question = null;
    $action = 'question_store';
    $title = 'Nieuwe vraag';
    require __DIR__ . '/../views/docent/question_form.php';
  }
  
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
  
  public function editQuestion() {
    requireLogin();
    requireRole('docent');
    
    $question = Question::find($_GET['id']);
    $examId = $question['exam_id'];
    $action = 'question_update';
    $title = 'Vraag bewerken';
    require __DIR__ . '/../views/docent/question_form.php';
  }
  
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

public function viewExamResults($examId) {
    requireLogin();
    requireRole('docent');

    $studentExams = StudentExam::findWithStudentDetailsByExam($examId);
    require __DIR__ . '/../views/docent/exam_results.php';
}

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

  public function gradeStudentExam($studentExamId) {
    requireLogin();
    requireRole('docent');

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

  public function saveTeacherFeedback() {
    requireLogin();
    requireRole('docent');

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
