<?php

require_once __DIR__ . '/../../config/database.php';

class StudentExam {
  
  public static function start($studentId, $examId) {
    $pdo = Database::connect();
    $uniqueId = "examen_" . $examId . "_" . $studentId . "_" . date('Ymd_His');
    
    $stmt = $pdo->prepare("
        INSERT INTO student_exams (student_id, exam_id, unique_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$studentId, $examId, $uniqueId]);
		  
    return $pdo->lastInsertId();
  }
  
  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM student_exams WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
    
  public static function allByStudent($studentId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT se.*, e.title FROM student_exams se JOIN exams e ON se.exam_id = e.id WHERE se.student_id = ?");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
