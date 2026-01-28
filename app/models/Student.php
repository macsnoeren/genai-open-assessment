<?php

require_once __DIR__ . '/../../config/database.php';

class Student {
  
  public static function all() {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'student'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
  
  public static function create($name, $email, $password) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
			  INSERT INTO users (name, email, password, role)
			  VALUES (?, ?, ?, 'student')
			  ");
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
  }
  
  public static function update($id, $name, $email, $password = null) {
    $pdo = Database::connect();
    if ($password) {
      $stmt = $pdo->prepare("
			    UPDATE users
			    SET name = ?, email = ?, password = ?, updated_at = CURRENT_TIMESTAMP
			    WHERE id = ? AND role = 'student'
			    ");
      $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $id]);
    } else {
      $stmt = $pdo->prepare("
			    UPDATE users
			    SET name = ?, email = ?, updated_at = CURRENT_TIMESTAMP
			    WHERE id = ? AND role = 'student'
			    ");
      $stmt->execute([$name, $email, $id]);
    }
  }
    
  public static function delete($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$id]);
  }
}
    
?>
