<?php

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Questions.php';
require_once __DIR__ . '/../models/StudentExam.php';
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
    requireRole('student');
    
    $examId = $_GET['exam_id'];
    $studentId = $_SESSION['user_id'];
    
    $studentExamId = StudentExam::start($studentId, $examId);
    header("Location: /?action=take_exam&student_exam_id={$studentExamId}");
    exit;
  }
  
  public function takeExam() {
    requireLogin();
    requireRole('student');
    
    $studentExamId = $_GET['student_exam_id'];
    $studentExam = StudentExam::find($studentExamId);
    $questions = Question::allByExam($studentExam['exam_id']);
    
    require __DIR__ . '/../views/student/take_exam.php';
  }
  
  public function submitExam() {
    requireLogin();
    requireRole('student');
    
    $studentExamId = $_POST['student_exam_id'];
    
    foreach ($_POST['answers'] as $questionId => $answer) {
      StudentAnswer::save($studentExamId, $questionId, $answer);
    }
    
    // Optioneel: exam markeren als completed
    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE student_exams SET completed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$studentExamId]);
    
    header("Location: /?action=my_exams");
    exit;
  }
  
  public function myExams() {
    requireLogin();
    requireRole('student');
    
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
    requireRole('student');
    
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
