<?php

require_once __DIR__ . '/../../config/database.php';

class ApiKey {
  
  public static function all() {
    $pdo = Database::connect();
    return $pdo->query("SELECT id, name, api_key, active, created_at FROM api_keys ORDER BY created_at DESC")
               ->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public static function create($name) {
    $key = bin2hex(random_bytes(32));
    
    $pdo = Database::connect();
    $stmt = $pdo->prepare("INSERT INTO api_keys (name, api_key) VALUES (?, ?)");
    $stmt->execute([$name, $key]);
    
    return $key;
  }
  
  public static function toggle($id) {
    $pdo = Database::connect();
    $pdo->prepare("UPDATE api_keys SET active = 1 - active WHERE id = ?")->execute([$id]);
  }
  
  public static function delete($id) {
    $pdo = Database::connect();
    $pdo->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$id]);
  }

  public static function isValid($key) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE api_key = ? AND active = 1");
    $stmt->execute([$key]);
    return (bool) $stmt->fetch();
  }
}
?>
