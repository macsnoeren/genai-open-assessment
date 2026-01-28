<?php

require_once __DIR__ . '/../../config/database.php';

class StudentAnswer {
  
  public static function save($studentExamId, $questionId, $answer) {
    $pdo = Database::connect();
    
    // Controleer of antwoord al bestaat
    $stmt = $pdo->prepare("SELECT id FROM student_answers WHERE student_exam_id = ? AND question_id = ?");
    $stmt->execute([$studentExamId, $questionId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
      $stmt = $pdo->prepare("UPDATE student_answers SET answer = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
      $stmt->execute([$answer, $existing['id']]);
    } else {
      $stmt = $pdo->prepare("INSERT INTO student_answers (student_exam_id, question_id, answer) VALUES (?, ?, ?)");
      $stmt->execute([$studentExamId, $questionId, $answer]);
    }
  }
  
  public static function allByStudentExam($studentExamId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM student_answers WHERE student_exam_id = ?");
    $stmt->execute([$studentExamId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
