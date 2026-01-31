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
  
  public static function create($title, $description, $promptText) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        INSERT INTO prompts (title, description, prompt_text)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$title, $description, $promptText]);
    return $pdo->lastInsertId();
  }
  
  public static function update($id, $title, $description, $promptText) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        UPDATE prompts 
        SET title = ?, description = ?, prompt_text = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$title, $description, $promptText, $id]);
  }
  
  public static function delete($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("DELETE FROM prompts WHERE id = ?");
    $stmt->execute([$id]);
  }
}
?>