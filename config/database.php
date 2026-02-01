<?php

class Database {
  private static $pdo;
  
  public static function connect() {
    if (!self::$pdo) {
      self::$pdo = new PDO("sqlite:" . __DIR__ . "/../database/database.sqlite");
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      
      self::initialize();
    }
    return self::$pdo;
  }
  
  private static function initialize() {
    $pdo = self::$pdo;
    
    // Check of de users tabel bestaat
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if (!$stmt->fetch()) {
        die("Database is nog niet geïnitialiseerd. Voer eerst het setup script uit.");
    }
	       
    // check of er users zijn
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
	       
    if ($count == 0) {
      self::createDefaultUser();
    }
  }
  
    private static function createDefaultUser() {
      $pdo = self::$pdo;
      
      $stmt = $pdo->prepare("
			    INSERT INTO users (name, email, password, role, force_password_change)
			    VALUES (?, ?, ?, ?, 1)
			    ");
			    
      $stmt->execute([
		      'Administrator',
		      'admin@school.nl',
		      password_hash('admin123', PASSWORD_DEFAULT),
		      'admin'
		      ]);
    }
  }
    
?>
