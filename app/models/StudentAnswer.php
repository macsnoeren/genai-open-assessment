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
    $stmt = $pdo->prepare("UPDATE student_answers SET ai_feedback = ? WHERE id = ?");
    $stmt->execute([$feedback, $id]);
  }
}
?>
