<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

class Database {
  private static $pdo;
  
  public static function connect() {
    if (!self::$pdo) {
      self::$pdo = new PDO("sqlite:" . __DIR__ . "/../database/database.sqlite");
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      // SQLite dwingt foreign keys (CASCADE/RESTRICT/SET NULL) alleen af als dit per verbinding aanstaat.
      self::$pdo->exec('PRAGMA foreign_keys = ON');
      
      self::initialize();
    }
    return self::$pdo;
  }
  
  private static function initialize() {
    $pdo = self::$pdo;
    
    // Check of de users tabel bestaat
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if (!$stmt->fetch()) {
        throw new RuntimeException("Database is nog niet geïnitialiseerd. Voer eerst setup/init_db.php uit.");
    }

    self::migrate();
	       
    // check of er users zijn
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
	       
    if ($count == 0) {
      self::createDefaultUser();
    }
  }

  /**
   * Lichte schema-migraties voor bestaande databases.
   */
  private static function migrate() {
    $pdo = self::$pdo;

    $examColumns = $pdo->query("PRAGMA table_info(exams)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('published', $examColumns, true)) {
        // Zichtbaarheid voor ingelogde studenten; bestaande toetsen worden standaard NIET gepubliceerd.
        $pdo->exec("ALTER TABLE exams ADD COLUMN published INTEGER DEFAULT 0");
    }

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='question_designs'");
    if (!$stmt->fetch()) {
        // AI-vraagontwerpen (agentic vraagontwerper). Zelfde definitie als in setup/schema.sql.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS question_designs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exam_id INTEGER NOT NULL,
                docent_id INTEGER,
                question_text TEXT NOT NULL,
                model_answer TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'analysis_pending',
                revision INTEGER NOT NULL DEFAULT 1,
                analysis TEXT,
                teacher_answers TEXT,
                assessment TEXT,
                validation TEXT,
                teacher_feedback TEXT,
                error_message TEXT,
                question_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                approved_at DATETIME,
                FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
                FOREIGN KEY (docent_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL
            )
        ");
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
