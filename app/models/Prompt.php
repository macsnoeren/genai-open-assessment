<?php

require_once __DIR__ . '/../../config/database.php';

class Prompt {
  
  public static function all() {
    $pdo = Database::connect();
    $stmt = $pdo->query("SELECT * FROM prompts ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM prompts WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
  
  public static function create($title, $description, $promptText, $gradingScale = 'points') {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        INSERT INTO prompts (title, description, prompt_text, grading_scale)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$title, $description, $promptText, $gradingScale]);
    return $pdo->lastInsertId();
  }
  
  public static function update($id, $title, $description, $promptText, $gradingScale = 'points') {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        UPDATE prompts 
        SET title = ?, description = ?, prompt_text = ?, grading_scale = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$title, $description, $promptText, $gradingScale, $id]);
  }

  /** Aantal toetsen dat deze prompt gebruikt met een andere schaal dan $gradingScale. */
  public static function countExamsWithOtherScale($id, string $gradingScale): int {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE prompt_id = ? AND grading_scale != ?");
    $stmt->execute([$id, $gradingScale]);
    return (int)$stmt->fetchColumn();
  }
  
  public static function delete($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("DELETE FROM prompts WHERE id = ?");
    $stmt->execute([$id]);
  }
}
?>