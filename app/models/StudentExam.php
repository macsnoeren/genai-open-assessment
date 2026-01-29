<?php

require_once __DIR__ . '/../../config/database.php';

class StudentExam {
  
  public static function start($studentId, $examId) {
    $pdo = Database::connect();
    $uniqueId = "toets_" . $examId . "_" . $studentId . "_" . date('Ymd_His');
    
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
    $stmt = $pdo->prepare("SELECT se.*, e.title FROM student_exams se JOIN exams e ON se.exam_id = e.id WHERE se.student_id = ? ORDER BY se.started_at DESC");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function delete($id) {
    $pdo = Database::connect();
    // Verwijder eerst de antwoorden van dit examen
    $stmt = $pdo->prepare("DELETE FROM student_answers WHERE student_exam_id = ?");
    $stmt->execute([$id]);
    
    // Verwijder het examen resultaat zelf
    $stmt = $pdo->prepare("DELETE FROM student_exams WHERE id = ?");
    $stmt->execute([$id]);
  }

  public static function findWithStudentDetailsByExam($examId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT 
            se.id as student_exam_id, 
            se.unique_id, 
            u.name, 
            se.started_at, 
            se.completed_at
        FROM student_exams se
        JOIN users u ON se.student_id = u.id
        WHERE se.exam_id = ?
        ORDER BY se.started_at DESC
    ");
    $stmt->execute([$examId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
