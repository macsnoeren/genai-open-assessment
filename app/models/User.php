<?php

require_once __DIR__ . '/../../config/database.php';

class User {
  
  public static function findByEmail($email) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
  
}
?>
