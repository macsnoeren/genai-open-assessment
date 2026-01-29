<?php

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Questions.php';
require_once __DIR__ . '/../models/StudentExam.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/StudentAnswer.php';

class StudentExamController {
  
  public function listExams() {
    requireLogin();
    requireRole('student');
    
    $exams = Exam::all();
    require __DIR__ . '/../views/student/exams_list.php';
  }
  
  public function startExam() {
    requireLogin();
    
    $examId = $_GET['exam_id'];
    $studentId = $_SESSION['user_id'];
    
    $studentExamId = StudentExam::start($studentId, $examId);
    AuditLog::log('exam_start', ['exam_id' => $examId, 'student_exam_id' => $studentExamId]);
    header("Location: /?action=take_exam&student_exam_id={$studentExamId}");
    exit;
  }
  
  public function takeExam() {
    requireLogin();
    
    $studentExamId = $_GET['student_exam_id'];
    $studentExam = StudentExam::find($studentExamId);

    // Security check: student kan alleen eigen toetsen inzien
    if (!$studentExam || $studentExam['student_id'] != $_SESSION['user_id']) {
        die("Geen toegang.");
    }

    $questions = Question::allByExam($studentExam['exam_id']);

    // Haal bestaande antwoorden op om het formulier vooraf in te vullen
    $answersRaw = StudentAnswer::allByStudentExam($studentExamId);
    $answers = [];
    foreach ($answersRaw as $a) {
        $answers[$a['question_id']] = $a;
    }
    
    require __DIR__ . '/../views/student/take_exam.php';
  }
  
  public function submitExam() {
    requireLogin();
    
    $studentExamId = $_POST['student_exam_id'];
    $actionType = $_POST['action_type'] ?? 'submit'; // 'submit' is de standaard
    
    foreach ($_POST['answers'] as $questionId => $answer) {
      StudentAnswer::save($studentExamId, $questionId, $answer);
    }
    
    if ($actionType === 'submit') {
        // Toets markeren als ingeleverd
        $pdo = Database::connect();
        AuditLog::log('exam_submit_final', ['student_exam_id' => $studentExamId]);
        $stmt = $pdo->prepare("UPDATE student_exams SET completed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$studentExamId]);
        
        header("Location: /?action=my_exams");
    } else {
        // Alleen opslaan en terugsturen naar de toetspagina
        AuditLog::log('exam_save_interim', ['student_exam_id' => $studentExamId]);
        $_SESSION['success_message'] = 'Je antwoorden zijn tussentijds opgeslagen.';
        header("Location: /?action=take_exam&student_exam_id={$studentExamId}");
    }

    exit;
  }
  
  public function myExams() {
    requireLogin();
    
    $studentId = $_SESSION['user_id'];
    $studentExams = StudentExam::allByStudent($studentId);
    
    require __DIR__ . '/../views/student/my_exams.php';
  }

  public function dashboard() {
    requireLogin();
    requireRole('student');

    $studentId = $_SESSION['user_id'];

    // Alle examens
    $exams = Exam::all();

    // Alle gemaakte examens door deze student
    $studentExams = StudentExam::allByStudent($studentId);

    require __DIR__ . '/../views/student/dashboard.php';
  }

  public function viewResults() {
    requireLogin();
    
    $studentExamId = $_GET['student_exam_id'] ?? null;
    
    if (!$studentExamId) {
      header("Location: /?action=student_dashboard");
      exit;
    }
    
    $studentExam = StudentExam::find($studentExamId);
    
    if (!$studentExam || $studentExam['student_id'] != $_SESSION['user_id']) {
      die("Geen toegang.");
    }
    
    $exam = Exam::find($studentExam['exam_id']);
    $questions = Question::allByExam($studentExam['exam_id']);
    $answersRaw = StudentAnswer::allByStudentExam($studentExamId);
    
    $answers = [];
    foreach ($answersRaw as $a) {
      $answers[$a['question_id']] = $a;
    }
    
    require __DIR__ . '/../views/student/view_results.php';
  }
}
?>
