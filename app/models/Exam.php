<?php

require_once __DIR__ . '/../../config/database.php';

class Exam {
  
  public static function allByDocent($docentId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE docent_id = ?");
    $stmt->execute([$docentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public static function create($title, $description, $docentId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
			  INSERT INTO exams (title, description, docent_id)
			  VALUES (?, ?, ?)
			  ");
    $stmt->execute([$title, $description, $docentId]);
  }

    public static function all() {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("SELECT * FROM exams ORDER BY created_at DESC");
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id) {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function update($id, $title, $description) {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("
			                UPDATE exams
			    SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP
			                WHERE id = ?
			            ");
      $stmt->execute([$title, $description, $id]);
    }

      public static function delete($id) {
	$pdo = Database::connect();
	$stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
	$stmt->execute([$id]);
      }
    }
  
 ?>
