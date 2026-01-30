<?php

require_once __DIR__ . '/../../config/database.php';

class StudentExam {
  
  public static function start($studentId, $examId) {
    $uniqueId = uniqid('EXAM-');
    
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
			  INSERT INTO student_exams (student_id, exam_id, unique_id)
			  VALUES (?, ?, ?)
			  ");
    $stmt->execute([$studentId, $examId, $uniqueId]);
    
    return $pdo->lastInsertId();
  }

  public static function startGuest($examId, $guestName) {
    $uniqueId = uniqid('GUEST-');
    $accessToken = bin2hex(random_bytes(32)); // Token voor de cookie
    
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
			  INSERT INTO student_exams (exam_id, unique_id, guest_name, access_token)
			  VALUES (?, ?, ?, ?)
			  ");
    $stmt->execute([$examId, $uniqueId, $guestName, $accessToken]);
    
    return [
        'id' => $pdo->lastInsertId(),
        'access_token' => $accessToken
    ];
  }
  
  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM student_exams WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function findByAccessToken($token) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM student_exams WHERE access_token = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
  
  public static function allByStudent($studentId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
			  SELECT se.*, e.title, e.description 
			  FROM student_exams se
			  JOIN exams e ON se.exam_id = e.id
			  WHERE se.student_id = ?
			  ORDER BY se.started_at DESC
			  ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function findWithStudentDetailsByExam($examId) {
      $pdo = Database::connect();
      // Gebruik COALESCE om guest_name te tonen als student_id NULL is (geen join match)
      $stmt = $pdo->prepare("
          SELECT se.*, 
                 COALESCE(u.name, se.guest_name, 'Onbekend') as name, 
                 se.id as student_exam_id
          FROM student_exams se
          LEFT JOIN users u ON se.student_id = u.id
          WHERE se.exam_id = ?
          ORDER BY se.started_at DESC
      ");
      $stmt->execute([$examId]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function delete($id) {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("DELETE FROM student_exams WHERE id = ?");
      $stmt->execute([$id]);
  }
}
?>