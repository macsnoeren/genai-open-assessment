<?php

require_once __DIR__ . '/../../config/database.php';

class Exam {
  
  public static function allByDocent($docentId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE docent_id = ?");
    $stmt->execute([$docentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public static function create($title, $description, $docentId, $promptId = null) {
    $pdo = Database::connect();
    // Genereer een unieke publieke token
    $publicToken = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("
			  INSERT INTO exams (title, description, docent_id, public_token, prompt_id)
			  VALUES (?, ?, ?, ?, ?)
			  ");
    $stmt->execute([$title, $description, $docentId, $publicToken, $promptId]);
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

    public static function findByPublicToken($token) {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("SELECT * FROM exams WHERE public_token = ?");
      $stmt->execute([$token]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function update($id, $title, $description, $promptId = null) {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("
			                UPDATE exams
			    SET title = ?, description = ?, prompt_id = ?, updated_at = CURRENT_TIMESTAMP
			                WHERE id = ?
			            ");
      $stmt->execute([$title, $description, $promptId, $id]);
    }

      public static function delete($id) {
	$pdo = Database::connect();
	$stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
	$stmt->execute([$id]);
      }
    }
  
 ?>
