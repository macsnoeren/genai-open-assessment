<?php

require_once __DIR__ . '/../../config/database.php';

class Question {
  
  public static function allByExam($examId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
    $stmt->execute([$examId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public static function create($examId, $text, $criteria) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
			  INSERT INTO questions (exam_id, question_text, criteria)
			  VALUES (?, ?, ?)
			  ");
    $stmt->execute([$examId, $text, $criteria]);
  }
  
  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
  
  public static function update($id, $text, $criteria) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
			  UPDATE questions
			  SET question_text = ?, criteria = ?, updated_at = CURRENT_TIMESTAMP
			  WHERE id = ?
			  ");
    $stmt->execute([$text, $criteria, $id]);
  }
    
    public static function delete($id) {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
      $stmt->execute([$id]);
    }
  }
?>
