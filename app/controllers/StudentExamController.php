<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Questions.php';
require_once __DIR__ . '/../models/StudentExam.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/StudentAnswer.php';

/**
 * Class StudentExamController
 * Handles the student's perspective of taking exams.
 */
class StudentExamController {
  
  /**
   * Lists available exams for the student.
   */
  public function listExams() {
    requireLogin();
    requireRole('student');
    
    $exams = Exam::all();
    require __DIR__ . '/../views/student/exams_list.php';
  }
  
  /**
   * Starts an exam attempt for a student.
   */
  public function startExam() {
    requireLogin();
    
    $examId = $_GET['exam_id'];
    $studentId = $_SESSION['user_id'];
    
    $studentExamId = StudentExam::start($studentId, $examId);
    AuditLog::log('exam_start', ['exam_id' => $examId, 'student_exam_id' => $studentExamId]);
    header("Location: /?action=take_exam&student_exam_id={$studentExamId}");
    exit;
  }

  /**
   * Handles the entry point for a guest link.
   */
  public function guestEntry() {
      $token = $_GET['token'] ?? '';
      $exam = Exam::findByPublicToken($token);

      if (!$exam) {
          die("Ongeldige link.");
      }

      // Check of er al een cookie is voor DEZE specifieke toets (of algemeen)
      // Voor eenvoud checken we nu 1 cookie 'guest_access_token'. 
      // Als de student meerdere toetsen tegelijk wil doen als gast, overschrijft dit elkaar.
      // In een productieomgeving zou je een array in de cookie of meerdere cookies gebruiken.
      if (isset($_COOKIE['guest_access_token'])) {
          $studentExam = StudentExam::findByAccessToken($_COOKIE['guest_access_token']);
          // Check of de cookie bij DEZE toets hoort
          if ($studentExam && $studentExam['exam_id'] == $exam['id']) {
              header("Location: /?action=take_exam&student_exam_id={$studentExam['id']}");
              exit;
          }
      }

      // Geen sessie gevonden, toon naam invulscherm
      require __DIR__ . '/../views/student/guest_login.php';
  }

  /**
   * Registers a guest and starts the exam.
   */
  public function guestStart() {
      $token = $_POST['token'] ?? '';
      $name = trim($_POST['name'] ?? '');
      
      $exam = Exam::findByPublicToken($token);
      if (!$exam || empty($name)) {
          die("Ongeldige aanvraag.");
      }

      $result = StudentExam::startGuest($exam['id'], $name);
      
      // Zet cookie voor 30 dagen
      setcookie('guest_access_token', $result['access_token'], time() + (86400 * 30), "/", "", false, true);

      header("Location: /?action=take_exam&student_exam_id={$result['id']}");
      exit;
  }
  
  /**
   * Displays the exam form for taking the exam.
   */
  public function takeExam() {
    // Check login of gast-sessie
    $isGuest = false;
    if (!isset($_SESSION['user_id'])) {
        // Probeer gast toegang
        if (isset($_COOKIE['guest_access_token'])) {
            $isGuest = true;
        } else {
            // Geen login en geen gast cookie
            header('Location: /?action=login');
            exit;
        }
    }

    $studentExamId = $_GET['student_exam_id'];
    $studentExam = StudentExam::find($studentExamId);

    // Security check: student kan alleen eigen toetsen inzien (of gast via token)
    if ($isGuest) {
        if ($studentExam['access_token'] !== $_COOKIE['guest_access_token']) {
            die("Geen toegang (ongeldig token).");
        }
    } else {
        if (!$studentExam || $studentExam['student_id'] != $_SESSION['user_id']) {
            die("Geen toegang.");
        }
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
  
  /**
   * Submits the exam answers (either interim save or final submit).
   */
  public function submitExam() {
    // Check login of gast-sessie
    $isGuest = false;
    if (!isset($_SESSION['user_id'])) {
        if (isset($_COOKIE['guest_access_token'])) {
            $isGuest = true;
        } else {
            header('Location: /?action=login');
            exit;
        }
    }
    
    $studentExamId = $_POST['student_exam_id'];
    $actionType = $_POST['action_type'] ?? 'submit'; // 'submit' is de standaard

    // Extra validatie voor gasten
    if ($isGuest) {
        $se = StudentExam::find($studentExamId);
        if ($se['access_token'] !== $_COOKIE['guest_access_token']) {
            die("Geen toegang.");
        }
    }
    
    foreach ($_POST['answers'] as $questionId => $answer) {
      StudentAnswer::save($studentExamId, $questionId, $answer);
    }
    
    if ($actionType === 'submit') {
        // Toets markeren als ingeleverd
        $pdo = Database::connect();
        AuditLog::log('exam_submit_final', ['student_exam_id' => $studentExamId]);
        $stmt = $pdo->prepare("UPDATE student_exams SET completed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$studentExamId]);
        
        if ($isGuest) {
             // Gasten hebben geen dashboard, toon bedankt pagina of resultaten (indien direct beschikbaar)
             // Voor nu sturen we ze terug naar de toets pagina, die toont dan 'ingeleverd'.
             // Of we kunnen een simpele 'bedankt' view maken.
             // Laten we ze naar de take_exam sturen, die we kunnen aanpassen om status te tonen.
             header("Location: /?action=take_exam&student_exam_id={$studentExamId}");
        } else {
            header("Location: /?action=my_exams");
        }
    } else {
        // Alleen opslaan en terugsturen naar de toetspagina
        AuditLog::log('exam_save_interim', ['student_exam_id' => $studentExamId]);
        $_SESSION['success_message'] = 'Je antwoorden zijn tussentijds opgeslagen.';
        header("Location: /?action=take_exam&student_exam_id={$studentExamId}");
    }

    exit;
  }
  
  /**
   * Lists exams taken by the student.
   */
  public function myExams() {
    requireLogin();
    
    $studentId = $_SESSION['user_id'];
    $studentExams = StudentExam::allByStudent($studentId);
    
    require __DIR__ . '/../views/student/my_exams.php';
  }

  /**
   * Displays the student dashboard.
   */
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

  /**
   * Views the results of a specific exam attempt.
   */
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
