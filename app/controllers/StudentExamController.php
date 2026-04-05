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
      $isGuest = true;
      require __DIR__ . '/../views/student/guest_login.php';
  }

  /**
   * Registers a guest and starts the exam.
   */
  public function guestStart() {
      validateCsrfToken();
      $token = $_POST['token'] ?? '';
      $name = trim($_POST['name'] ?? '');
      
      $exam = Exam::findByPublicToken($token);
      if (!$exam || empty($name)) {
          die("Ongeldige aanvraag.");
      }

      $result = StudentExam::startGuest($exam['id'], $name);
      
      // Zet cookie voor 30 dagen
      $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
      setcookie('guest_access_token', $result['access_token'], time() + (86400 * 30), "/", "", $secure, true);

      header("Location: /?action=take_exam&student_exam_id={$result['id']}");
      exit;
  }

  /**
   * Logs out a guest user (clears cookie) so they can change their name/start over.
   */
  public function guestLogout() {
      $studentExamId = $_GET['student_exam_id'] ?? null;
      
      // Verwijder de cookie
      $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
      setcookie('guest_access_token', '', time() - 3600, "/", "", $secure, true);

      if ($studentExamId) {
          $studentExam = StudentExam::find($studentExamId);
          if ($studentExam) {
              $exam = Exam::find($studentExam['exam_id']);
              if ($exam && $exam['public_token']) {
                  header("Location: /?action=guest&token={$exam['public_token']}");
                  exit;
              }
          }
      }
      
      header("Location: /");
      exit;
  }
  
  /**
   * Displays the exam form for taking the exam.
   */
  public function takeExam() {
    $studentExamId = $_GET['student_exam_id'];
    $studentExam = StudentExam::find($studentExamId);
    if (!$studentExam) {
        die("Toetspoging niet gevonden.");
    }

    // Bepaal of dit een gastpoging is of een geregistreerde student
    $isGuest = ($studentExam['student_id'] === null);

    if ($isGuest) {
        // Check of er toegang is via een cookie OF via een token in de URL
        $urlToken = $_GET['token'] ?? null;
        $cookieToken = $_COOKIE['guest_access_token'] ?? null;

        if ($studentExam['access_token'] !== $urlToken && $studentExam['access_token'] !== $cookieToken) {
            die("Geen toegang (ongeldig token).");
        }
    } else {
        requireLogin();
        if ($studentExam['student_id'] != $_SESSION['user_id']) {
            die("Geen toegang.");
        }
    }

    if (!empty($studentExam['completed_at'])) {
        header("Location: /?action=student_view_results&student_exam_id={$studentExamId}");
        exit;
    }

    $questions = Question::allByExam($studentExam['exam_id']);

    // Nummer de vragen voor weergave
    foreach ($questions as $index => &$question) {
        $question['question_text'] = ($index + 1) . ". " . $question['question_text'];
    }
    unset($question);

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
    validateCsrfToken();
    
    $studentExamId = $_POST['student_exam_id'];
    $se = StudentExam::find($studentExamId);
    if (!$se) {
        die("Toetspoging niet gevonden.");
    }

    $actionType = $_POST['action_type'] ?? 'submit'; // 'submit' is de standaard

    $isGuest = ($se['student_id'] === null);

    if ($isGuest) {
        if (!isset($_COOKIE['guest_access_token']) || $se['access_token'] !== $_COOKIE['guest_access_token']) {
            die("Geen toegang.");
        }
    } else {
        requireLogin();
        if (!$se || $se['student_id'] != $_SESSION['user_id']) {
            die("Geen toegang: Dit is niet jouw toetspoging.");
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
             header("Location: /?action=student_view_results&student_exam_id={$studentExamId}");
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
    $studentExamId = $_GET['student_exam_id'] ?? null;
    $studentExam = StudentExam::find($studentExamId);

    if (!$studentExam) {
        header("Location: /?action=student_dashboard");
        exit;
    }

    $isGuest = ($studentExam['student_id'] === null);

    if ($isGuest) {
        // Controleer op toegang via cookie OF via een token in de URL
        $urlToken = $_GET['token'] ?? null;
        $cookieToken = $_COOKIE['guest_access_token'] ?? null;

        if ($studentExam['access_token'] !== $urlToken && $studentExam['access_token'] !== $cookieToken) {
            die("Geen toegang (ongeldig token).");
        }
    } else {
        requireLogin();
        if ($studentExam['student_id'] != $_SESSION['user_id']) {
            die("Geen toegang.");
        }
    }
    
    $exam = Exam::find($studentExam['exam_id']);
    $questions = Question::allByExam($studentExam['exam_id']);

    // Nummer de vragen voor weergave
    foreach ($questions as $index => &$question) {
        $question['question_text'] = ($index + 1) . ". " . $question['question_text'];
    }
    unset($question);

    $answersRaw = StudentAnswer::allByStudentExam($studentExamId);
    
    $answers = [];
    $totalScore = 0;
    $scoredCount = 0;
    $aiModelScores = [];

    foreach ($answersRaw as $a) {
      $answers[$a['question_id']] = $a;
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
    
    require __DIR__ . '/../views/student/view_results.php';
  }
}
?>
