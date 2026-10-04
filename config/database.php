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

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='answer_assessments'");
    if (!$stmt->fetch()) {
        // Agentic beoordelingen van studentantwoorden. Zelfde definitie als in setup/schema.sql.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS answer_assessments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_answer_id INTEGER NOT NULL,
                requested_by INTEGER,
                status TEXT NOT NULL DEFAULT 'pending',
                question_snapshot TEXT NOT NULL,
                criteria_snapshot TEXT NOT NULL,
                answer_snapshot TEXT NOT NULL,
                rubric TEXT,
                evidence TEXT,
                rounds TEXT,
                decision TEXT,
                run_log TEXT,
                final_score INTEGER,
                human_review_needed INTEGER NOT NULL DEFAULT 0,
                error_message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_answer_id) REFERENCES student_answers(id) ON DELETE CASCADE,
                FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_answer_assessments_answer_status
                    ON answer_assessments (student_answer_id, status)");
    }

    $keyColumns = $pdo->query("PRAGMA table_info(api_keys)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('scope', $keyColumns, true)) {
        // Scope van een key; bestaande keys zijn van de workers en krijgen dus 'worker'.
        $pdo->exec("ALTER TABLE api_keys ADD COLUMN scope TEXT NOT NULL DEFAULT 'worker'");
    }

    // Externe koppeling. Zelfde definities als in setup/schema.sql.
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='integrations'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS integrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                api_key_id INTEGER NOT NULL UNIQUE,
                return_origin TEXT NOT NULL,
                webhook_url TEXT,
                webhook_secret TEXT NOT NULL,
                min_confidence TEXT NOT NULL DEFAULT 'hoog',
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
    }

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='integration_exams'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS integration_exams (
                integration_id INTEGER NOT NULL,
                exam_id INTEGER NOT NULL,
                PRIMARY KEY (integration_id, exam_id),
                FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE,
                FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
            )
        ");
    }

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='integration_attempts'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS integration_attempts (
                student_exam_id INTEGER PRIMARY KEY,
                integration_id INTEGER NOT NULL,
                external_ref TEXT NOT NULL,
                return_url TEXT NOT NULL,
                launch_token_hash TEXT UNIQUE,
                launch_expires_at DATETIME,
                launch_used_at DATETIME,
                reviewed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (integration_id, external_ref),
                FOREIGN KEY (student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE,
                FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE
            )
        ");
    }

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='integration_events'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS integration_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                integration_id INTEGER NOT NULL,
                student_exam_id INTEGER NOT NULL,
                event TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                next_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                delivered_at DATETIME,
                last_status INTEGER,
                last_error TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (student_exam_id, event),
                FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE,
                FOREIGN KEY (student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_integration_events_due
                    ON integration_events (delivered_at, next_attempt_at)");
    }

    // Beoordelen met niveaus en puntenschema's. Zelfde definitie als in setup/schema.sql.
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='grading_schemes'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS grading_schemes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                points_voldoende INTEGER NOT NULL,
                points_goed INTEGER NOT NULL,
                points_uitstekend INTEGER NOT NULL,
                owner_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (points_voldoende, points_goed, points_uitstekend),
                CHECK (points_voldoende > 0 AND points_voldoende < points_goed AND points_goed < points_uitstekend AND points_uitstekend <= 100),
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        $pdo->exec("INSERT OR IGNORE INTO grading_schemes (name, points_voldoende, points_goed, points_uitstekend, owner_id)
                    VALUES ('Standaard (3/4/5)', 3, 4, 5, NULL)");
    }

    // Bestaande rijen krijgen veilige defaults: toetsen en prompts blijven points,
    // alle nieuwe kolommen voor niveaus en de handmatige aanpassing blijven NULL.
    $levelColumns = [
        'exams' => [
            'grading_scale' => "TEXT NOT NULL DEFAULT 'points'",
            'grading_scheme_id' => 'INTEGER REFERENCES grading_schemes(id) ON DELETE RESTRICT',
            'show_grade_label' => 'INTEGER DEFAULT 0',
        ],
        'student_answers' => [
            'teacher_level' => 'TEXT',
        ],
        'student_exams' => [
            'grade_override' => 'REAL',
            'grade_override_label' => 'TEXT',
            'grade_override_reason' => 'TEXT',
            'grade_override_by' => 'INTEGER REFERENCES users(id) ON DELETE SET NULL',
            'grade_override_at' => 'DATETIME',
            'grade_override_basis' => 'REAL',
        ],
        'answer_assessments' => [
            'final_level' => 'TEXT',
        ],
        'prompts' => [
            'grading_scale' => "TEXT NOT NULL DEFAULT 'points'",
        ],
    ];
    foreach ($levelColumns as $table => $columns) {
        $existing = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach ($columns as $column => $definition) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        }
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
