<?php

require_once __DIR__ . '/../../config/database.php';

class Exam {
  
  public static function allByDocent($docentId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE docent_id = ? OR shared = 1 ORDER BY created_at DESC");
    $stmt->execute([$docentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public static function create($title, $description, $docentId, $promptId = null, $aiGradingEnabled = 0, $shared = 0) {
    $pdo = Database::connect();
    // Genereer een unieke publieke token
    $publicToken = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("
			  INSERT INTO exams (title, description, docent_id, public_token, prompt_id, ai_grading_enabled, shared)
			  VALUES (?, ?, ?, ?, ?, ?, ?)
			  ");
    $stmt->execute([$title, $description, $docentId, $publicToken, $promptId, $aiGradingEnabled, $shared]);
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

    public static function update($id, $title, $description, $promptId = null, $aiGradingEnabled = 0, $shared = 0) {
      $pdo = Database::connect();
      $stmt = $pdo->prepare("
			                UPDATE exams
			    SET title = ?, description = ?, prompt_id = ?, ai_grading_enabled = ?, shared = ?, updated_at = CURRENT_TIMESTAMP
			                WHERE id = ?
			            ");
      $stmt->execute([$title, $description, $promptId, $aiGradingEnabled, $shared, $id]);
    }

    public static function duplicate($id) {
      $pdo = Database::connect();
      
      try {
        $pdo->beginTransaction();

        // 1. Haal origineel examen op
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
        $stmt->execute([$id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            throw new Exception("Exam not found");
        }

        // 2. Maak nieuw examen aan
        $newTitle = $exam['title'] . ' (Kopie)';
        $publicToken = bin2hex(random_bytes(16));
        
        $stmt = $pdo->prepare("
            INSERT INTO exams (title, description, docent_id, public_token, prompt_id, ai_grading_enabled, shared)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$newTitle, $exam['description'], $exam['docent_id'], $publicToken, $exam['prompt_id'], $exam['ai_grading_enabled'], 0]); // Kopie is standaard niet gedeeld
        $newExamId = $pdo->lastInsertId();

        // 3. Kopieer vragen
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
        $stmt->execute([$id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $questionMap = []; // map old_id -> new_id
        
        $insertQ = $pdo->prepare("INSERT INTO questions (exam_id, question_text, criteria) VALUES (?, ?, ?)");
        
        foreach ($questions as $q) {
            $insertQ->execute([$newExamId, $q['question_text'], $q['criteria']]);
            $questionMap[$q['id']] = $pdo->lastInsertId();
        }

        // 4. Kopieer student pogingen (student_exams)
        $stmt = $pdo->prepare("SELECT * FROM student_exams WHERE exam_id = ?");
        $stmt->execute([$id]);
        $studentExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $insertSE = $pdo->prepare("
            INSERT INTO student_exams (student_id, guest_name, exam_id, unique_id, access_token, started_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        // 5. Kopieer antwoorden (student_answers) - ZONDER AI feedback, MET docent feedback
        $insertSA = $pdo->prepare("
            INSERT INTO student_answers (student_exam_id, question_id, answer, teacher_score, teacher_feedback)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($studentExams as $se) {
            // Nieuwe unieke tokens genereren
            $newUniqueId = uniqid('COPY-');
            $newAccessToken = bin2hex(random_bytes(32));

            $insertSE->execute([
                $se['student_id'],
                $se['guest_name'],
                $newExamId,
                $newUniqueId,
                $newAccessToken,
                $se['started_at'],
                $se['completed_at']
            ]);
            $newStudentExamId = $pdo->lastInsertId();

            // Haal antwoorden op voor deze poging
            $stmtAnswers = $pdo->prepare("SELECT * FROM student_answers WHERE student_exam_id = ?");
            $stmtAnswers->execute([$se['id']]);
            $answers = $stmtAnswers->fetchAll(PDO::FETCH_ASSOC);

            foreach ($answers as $ans) {
                if (isset($questionMap[$ans['question_id']])) {
                    $insertSA->execute([
                        $newStudentExamId,
                        $questionMap[$ans['question_id']],
                        $ans['answer'],
                        $ans['teacher_score'],
                        $ans['teacher_feedback']
                    ]);
                }
            }
        }

        $pdo->commit();
        return $newExamId;

      } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
      }
    }

      public static function delete($id) {
	$pdo = Database::connect();
	$stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
	$stmt->execute([$id]);
      }
    }
  
 ?>
