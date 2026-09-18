<?php

require_once __DIR__ . '/../../config/database.php';

class StudentAnswer {
  
  public static function save($studentExamId, $questionId, $answer) {
    $pdo = Database::connect();
    
    // Controleer of antwoord al bestaat
    $stmt = $pdo->prepare("SELECT id FROM student_answers WHERE student_exam_id = ? AND question_id = ?");
    $stmt->execute([$studentExamId, $questionId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
      $stmt = $pdo->prepare("UPDATE student_answers SET answer = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
      $stmt->execute([$answer, $existing['id']]);
    } else {
      $stmt = $pdo->prepare("INSERT INTO student_answers (student_exam_id, question_id, answer) VALUES (?, ?, ?)");
      $stmt->execute([$studentExamId, $questionId, $answer]);
    }
  }
  
  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM student_answers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  /** Antwoord inclusief toetspoging en toets-id (voor autorisatiecontroles). */
  public static function findWithExam($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT sa.*, se.exam_id, se.completed_at
        FROM student_answers sa
        JOIN student_exams se ON sa.student_exam_id = se.id
        WHERE sa.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function updateTeacherGrade($id, $score, $feedback) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE student_answers SET teacher_score = ?, teacher_feedback = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$score, $feedback, $id]);
  }

  public static function allByStudentExam($studentExamId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM student_answers WHERE student_exam_id = ?");
    $stmt->execute([$studentExamId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function getPendingAiGrading($limit = null) {
    $pdo = Database::connect();
    $sql = "
        SELECT sa.id as student_answer_id, sa.answer, q.question_text, q.criteria, p.prompt_text
        FROM student_answers sa
        INNER JOIN questions q ON sa.question_id = q.id
        INNER JOIN student_exams se ON sa.student_exam_id = se.id
        INNER JOIN exams e ON se.exam_id = e.id
        LEFT JOIN prompts p ON e.prompt_id = p.id
        WHERE (sa.ai_feedback IS NULL OR sa.ai_feedback = '')
        AND se.completed_at IS NOT NULL
        AND e.ai_grading_enabled = 1
        ORDER BY sa.id ASC
    ";

    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function updateAiFeedback($id, $feedback) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE student_answers SET ai_feedback = ?, ai_updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$feedback, $id]);
    return $stmt->rowCount() > 0;
  }

  public static function clearAiFeedbackByExam($examId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        UPDATE student_answers 
        SET ai_feedback = NULL, ai_updated_at = NULL
        WHERE student_exam_id IN (
            SELECT id FROM student_exams WHERE exam_id = ?
        )
    ");
    $stmt->execute([$examId]);
  }
}
?>
