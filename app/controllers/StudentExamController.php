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

  // ---------------------------------------------------------------------
  // Gast-cookie helpers
  // ---------------------------------------------------------------------

  private function setGuestCookie(string $name, string $value, int $lifetime): void {
      setcookie($name, $value, [
          'expires' => $lifetime > 0 ? time() + $lifetime : time() - 3600,
          'path' => '/',
          'domain' => '',
          'secure' => isHttps(),
          'httponly' => true,
          'samesite' => 'Strict',
      ]);
  }

  private static function isValidToken($token): bool {
      return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
  }

  /** Gevalideerde lijst van toegangstokens uit de guest_history cookie. */
  private function guestHistory(): array {
      $raw = $_COOKIE['guest_history'] ?? '';
      if (!is_string($raw) || $raw === '') {
          return [];
      }
      $history = json_decode($raw, true);
      if (!is_array($history)) {
          return [];
      }
      $history = array_values(array_unique(array_filter($history, [self::class, 'isValidToken'])));
      return array_slice($history, -20);
  }

  private function addToGuestHistory(string $token): void {
      $history = $this->guestHistory();
      if (!in_array($token, $history, true)) {
          $history[] = $token;
          $history = array_slice($history, -20);
          $this->setGuestCookie('guest_history', json_encode($history), GUEST_COOKIE_LIFETIME);
          $_COOKIE['guest_history'] = json_encode($history);
      }
  }

  private function currentGuestToken(): ?string {
      $token = $_COOKIE['guest_access_token'] ?? null;
      return self::isValidToken($token) ? $token : null;
  }

  /**
   * Als er een geldig token in de URL staat: opslaan in een cookie en redirecten
   * naar dezelfde URL zonder token, zodat het token niet in logs/history blijft.
   */
  private function absorbUrlToken(array $studentExam, bool $asCurrent): void {
      if (!isset($_GET['token'])) {
          return;
      }
      $urlToken = $_GET['token'];
      if (!self::isValidToken($urlToken) || !hash_equals((string)$studentExam['access_token'], $urlToken)) {
          abort(403, 'Geen toegang (ongeldig token).');
      }
      if ($asCurrent) {
          $this->setGuestCookie('guest_access_token', $urlToken, GUEST_COOKIE_LIFETIME);
      }
      $this->addToGuestHistory($urlToken);

      $query = $_GET;
      unset($query['token']);
      header('Location: /?' . http_build_query($query));
      exit;
  }

  /** Controleert of de huidige gast toegang heeft tot deze poging. */
  private function guestHasAccess(array $studentExam, bool $allowHistory): bool {
      $token = (string)$studentExam['access_token'];
      if ($token === '') {
          return false;
      }
      $current = $this->currentGuestToken();
      if ($current !== null && hash_equals($token, $current)) {
          return true;
      }
      return $allowHistory && in_array($token, $this->guestHistory(), true);
  }

  private function redirectToDashboard(): void {
      $role = $_SESSION['role'] ?? 'student';
      if ($role === 'docent' || $role === 'admin') {
          header('Location: /?action=docent_dashboard');
      } elseif ($role === 'beoordelaar') {
          header('Location: /?action=pending_assessments');
      } else {
          header('Location: /?action=student_dashboard');
      }
      exit;
  }

  // ---------------------------------------------------------------------
  // Ingelogde studenten
  // ---------------------------------------------------------------------
  
  /**
   * Lists available (published) exams for the student.
   */
  public function listExams() {
    requireRole('student');
    
    $exams = Exam::allPublished();
    require __DIR__ . '/../views/student/exams_list.php';
  }
  
  /**
   * Starts an exam attempt for a logged-in user.
   * Studenten: alleen gepubliceerde toetsen. Docenten: eigen of gedeelde toetsen (testen).
   */
  public function startExam() {
    requireLogin();
    
    $examId = requestInt($_GET, 'exam_id') ?? requestInt($_POST, 'exam_id');
    $exam = $examId !== null ? Exam::find($examId) : null;
    if (!$exam) {
        abort(404, 'Toets niet gevonden.');
    }

    $role = $_SESSION['role'] ?? 'student';
    $userId = (int)$_SESSION['user_id'];
    $allowed = false;
    if ($role === 'admin') {
        $allowed = true;
    } elseif ($role === 'docent') {
        $allowed = ((int)$exam['docent_id'] === $userId) || !empty($exam['shared']) || !empty($exam['published']);
    } else {
        $allowed = !empty($exam['published']);
    }
    if (!$allowed) {
        abort(403, 'Deze toets is niet beschikbaar.');
    }
    
    $studentExamId = StudentExam::start($userId, $examId);
    AuditLog::log('exam_start', ['exam_id' => $examId, 'student_exam_id' => $studentExamId]);
    header("Location: /?action=take_exam&student_exam_id={$studentExamId}");
    exit;
  }

  // ---------------------------------------------------------------------
  // Gasten
  // ---------------------------------------------------------------------

  /**
   * Handles the entry point for a guest link.
   */
  public function guestEntry() {
      $token = $_GET['token'] ?? '';
      $exam = is_string($token) && $token !== '' ? Exam::findByPublicToken($token) : null;

      if (!$exam) {
          abort(404, 'Ongeldige link.');
      }

      // Bestaande gastpoging voor DEZE toets hervatten
      $current = $this->currentGuestToken();
      if ($current !== null) {
          $studentExam = StudentExam::findByAccessToken($current);
          if ($studentExam && (int)$studentExam['exam_id'] === (int)$exam['id']) {
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
      $token = requestString($_POST, 'token', 64);
      $name = trim(requestString($_POST, 'name', MAX_NAME_LENGTH));
      
      $exam = $token !== '' ? Exam::findByPublicToken($token) : null;
      if (!$exam || $name === '') {
          abort(400, 'Ongeldige aanvraag.');
      }

      // Rate limiting per IP (kosten-/spam-bescherming)
      $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
      if (AuditLog::countRecent('guest_start', GUEST_START_WINDOW_MINUTES, $ip) >= GUEST_START_MAX_PER_IP) {
          AuditLog::log('guest_start_blocked', ['exam_id' => $exam['id']], 'Gast');
          abort(429, 'Te veel toetsstarts vanaf dit adres. Probeer het later opnieuw.');
      }

      $result = StudentExam::startGuest($exam['id'], $name);
      AuditLog::log('guest_start', ['exam_id' => $exam['id'], 'student_exam_id' => $result['id'], 'guest_name' => $name], 'Gast');
      
      $this->setGuestCookie('guest_access_token', $result['access_token'], GUEST_COOKIE_LIFETIME);
      $this->addToGuestHistory($result['access_token']);

      header("Location: /?action=take_exam&student_exam_id={$result['id']}");
      exit;
  }

  /**
   * Logs out a guest user (clears cookie) so they can change their name/start over.
   */
  public function guestLogout() {
      $studentExamId = requestInt($_GET, 'student_exam_id');
      
      $this->setGuestCookie('guest_access_token', '', 0);

      if ($studentExamId !== null) {
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

  // ---------------------------------------------------------------------
  // Toets maken en inleveren (student + gast)
  // ---------------------------------------------------------------------
  
  /**
   * Displays the exam form for taking the exam.
   */
  public function takeExam() {
    $studentExamId = requestInt($_GET, 'student_exam_id');
    $studentExam = $studentExamId !== null ? StudentExam::find($studentExamId) : null;
    if (!$studentExam) {
        abort(404, 'Toetspoging niet gevonden.');
    }

    // Bepaal of dit een gastpoging is of een geregistreerde student
    $isGuest = ($studentExam['student_id'] === null);

    if ($isGuest) {
        $this->absorbUrlToken($studentExam, true);
        if (!$this->guestHasAccess($studentExam, false)) {
            abort(403, 'Geen toegang (ongeldig token).');
        }
    } else {
        requireLogin();
        if ((int)$studentExam['student_id'] !== (int)$_SESSION['user_id']) {
            abort(403, 'Geen toegang.');
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
    
    $studentExamId = requestInt($_POST, 'student_exam_id');
    $se = $studentExamId !== null ? StudentExam::find($studentExamId) : null;
    if (!$se) {
        abort(404, 'Toetspoging niet gevonden.');
    }

    $actionType = requestString($_POST, 'action_type', 10, 'submit') === 'save' ? 'save' : 'submit';

    $isGuest = ($se['student_id'] === null);

    if ($isGuest) {
        if (!$this->guestHasAccess($se, false)) {
            abort(403, 'Geen toegang.');
        }
    } else {
        requireLogin();
        if ((int)$se['student_id'] !== (int)$_SESSION['user_id']) {
            abort(403, 'Geen toegang: Dit is niet jouw toetspoging.');
        }
    }

    // Na definitief inleveren mag er niets meer gewijzigd worden
    if (!empty($se['completed_at'])) {
        AuditLog::log('exam_submit_after_completion', ['student_exam_id' => $studentExamId], $isGuest ? 'Gast' : null);
        abort(403, 'Deze toets is al ingeleverd en kan niet meer worden gewijzigd.');
    }

    // Alleen antwoorden op vragen die bij DEZE toets horen
    $posted = $_POST['answers'] ?? [];
    if (!is_array($posted)) {
        abort(400, 'Ongeldige invoer.');
    }
    $validQuestionIds = Question::idsByExam($se['exam_id']);
    foreach ($posted as $questionId => $answer) {
      if (!is_string($answer) || !preg_match('/^\d{1,18}$/', (string)$questionId)) {
          continue;
      }
      $questionId = (int)$questionId;
      if (!in_array($questionId, $validQuestionIds, true)) {
          continue;
      }
      if (strlen($answer) > MAX_ANSWER_LENGTH) {
          $answer = substr($answer, 0, MAX_ANSWER_LENGTH);
      }
      StudentAnswer::save($studentExamId, $questionId, $answer);
    }
    
    if ($actionType === 'submit') {
        // Toets markeren als ingeleverd
        $pdo = Database::connect();
        AuditLog::log('exam_submit_final', ['student_exam_id' => $studentExamId], $isGuest ? 'Gast' : null);
        $stmt = $pdo->prepare("UPDATE student_exams SET completed_at = CURRENT_TIMESTAMP WHERE id = ? AND completed_at IS NULL");
        $stmt->execute([$studentExamId]);
        
        if ($isGuest) {
             header("Location: /?action=student_view_results&student_exam_id={$studentExamId}");
        } else {
            header("Location: /?action=my_exams");
        }
    } else {
        // Alleen opslaan en terugsturen naar de toetspagina
        AuditLog::log('exam_save_interim', ['student_exam_id' => $studentExamId], $isGuest ? 'Gast' : null);
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
    requireRole('student');

    $studentId = $_SESSION['user_id'];

    // Gepubliceerde examens
    $exams = Exam::allPublished();

    // Alle gemaakte examens door deze student
    $studentExams = StudentExam::allByStudent($studentId);

    require __DIR__ . '/../views/student/dashboard.php';
  }

  /**
   * Views the results of a specific exam attempt.
   */
  public function viewResults() {
    $studentExamId = requestInt($_GET, 'student_exam_id');
    $studentExam = $studentExamId !== null ? StudentExam::find($studentExamId) : null;

    $isGuest = true;
    $currentStudentId = null;

    if ($studentExam) {
        $isGuest = ($studentExam['student_id'] === null);
        $currentStudentId = $studentExam['student_id'];

        if ($isGuest) {
            $this->absorbUrlToken($studentExam, false);
            if (!$this->guestHasAccess($studentExam, true)) {
                abort(403, 'Geen toegang (ongeldig token).');
            }
        } else {
            requireLogin();
            if ((int)$studentExam['student_id'] !== (int)$_SESSION['user_id']) {
                abort(403, 'Geen toegang.');
            }
        }
    } elseif ($studentExamId !== null) {
        abort(404, 'Toetspoging niet gevonden.');
    } else {
        // Geen specifiek examen geselecteerd; dashboard modus op basis van login of cookies
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
            requireLogin();
            $isGuest = false;
            $currentStudentId = $_SESSION['user_id'];
        } elseif (!empty($this->guestHistory()) || $this->currentGuestToken() !== null) {
            $isGuest = true;
        } else {
            header("Location: /?action=login");
            exit;
        }
    }

    // Haal de lijst met alle relevante afgeronde toetsen op voor de student of gast
    $allStudentExams = [];
    if (!$isGuest) {
        $allStudentExams = StudentExam::allByStudent($currentStudentId);
    } else {
        $history = $this->guestHistory();
        $current = $this->currentGuestToken();
        if ($current !== null && !in_array($current, $history, true)) {
            $history[] = $current;
        }
        foreach ($history as $token) {
            $se = StudentExam::findByAccessToken($token);
            if ($se && !empty($se['completed_at'])) {
                $exData = Exam::find($se['exam_id']);
                $se['exam_title'] = $exData['title'] ?? 'Toets';
                $allStudentExams[] = $se;
            }
        }
    }

    if (!$studentExam) {
        // Toon enkel het dashboard overzicht met alle resultaten
        require __DIR__ . '/../views/student/view_results.php';
        return;
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
