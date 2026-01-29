<?php

require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../helpers/auth.php';
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
    
    Exam::update(
		 $_POST['id'],
		 $_POST['title'],
		 $_POST['description']
		 );
    
    header('Location: /?action=docent_dashboard');
    exit;
  }
  
  public function deleteExam() {
    requireLogin();
    requireRole('docent');
    
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
    
    Question::update(
		     $_POST['id'],
		     $_POST['question_text'],
		     $_POST['model_answer'],
		     $_POST['criteria']
		     );
    
    header('Location: /?action=questions&exam_id=' . $_POST['exam_id']);
    exit;
  }
  
  public function deleteQuestion() {
    requireLogin();
    requireRole('docent');
    
    $question = Question::find($_GET['id']);
    $examId = $question['exam_id'];
    Question::delete($_GET['id']);
    
    header('Location: /?action=questions&exam_id=' . $examId);
    exit;
  }

public function viewExamResults($examId) {
    requireLogin();
        requireRole('docent');

    // Haal alle studenten examens voor dit examen
        $pdo = Database::connect();
	    $stmt = $pdo->prepare("
        SELECT se.id as student_exam_id, se.unique_id, u.name, se.started_at, se.completed_at
        FROM student_exams se
        JOIN users u ON se.student_id = u.id
        WHERE se.exam_id = ?
        ORDER BY se.started_at DESC
    ");
        $stmt->execute([$examId]);
	    $studentExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require __DIR__ . '/../views/docent/exam_results.php';
    }

public function viewStudentAnswers($studentExamId) {
    requireLogin();
        requireRole('docent');

    $studentExam = StudentExam::find($studentExamId);

    $pdo = Database::connect();
        $stmt = $pdo->prepare("
        SELECT q.question_text, sa.answer, q.model_answer, q.criteria, sa.ai_feedback
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.student_exam_id = ?
    ");
        $stmt->execute([$studentExamId]);
	    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require __DIR__ . '/../views/docent/student_answers.php';
    }

  public function deleteStudentExam() {
    requireLogin();
    requireRole('docent');
    
    $studentExamId = $_GET['student_exam_id'] ?? null;
    $studentExam = $studentExamId ? StudentExam::find($studentExamId) : null;
    
    if ($studentExam) {
        StudentExam::delete($studentExamId);
        header('Location: /?action=exam_results&exam_id=' . $studentExam['exam_id']);
        exit;
    }
    
    header('Location: /?action=docent_dashboard');
    exit;
  }
    
}

?>
