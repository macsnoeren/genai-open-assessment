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
    
    // check of users tabel bestaat
    $pdo->exec("
	       CREATE TABLE IF NOT EXISTS users (
						 id INTEGER PRIMARY KEY AUTOINCREMENT,
	       name TEXT NOT NULL,
	       email TEXT UNIQUE NOT NULL,
	       password TEXT NOT NULL,
	       role TEXT CHECK(role IN ('student','docent')) NOT NULL,
	       created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	       updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
	       )
	       ");
	       
    // check of er users zijn
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
	       
    if ($count == 0) {
      self::createDefaultUser();
    }
  }
  
    private static function createDefaultUser() {
      $pdo = self::$pdo;
      
      $stmt = $pdo->prepare("
			    INSERT INTO users (name, email, password, role)
			    VALUES (?, ?, ?, ?)
			    ");
			    
      $stmt->execute([
		      'Default Docent',
		      'docent@school.nl',
		      password_hash('admin123', PASSWORD_DEFAULT),
		      'docent'
		      ]);
    }
  }
    
?>
