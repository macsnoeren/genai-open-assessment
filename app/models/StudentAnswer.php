<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AnswerAssessment.php';
require_once __DIR__ . '/Grading.php';

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

  /** Antwoord inclusief toetspoging, toets-id en schaal van de toets (voor autorisatie en validatie). */
  public static function findWithExam($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT sa.*, se.exam_id, se.completed_at, e.grading_scale
        FROM student_answers sa
        JOIN student_exams se ON sa.student_exam_id = se.id
        JOIN exams e ON se.exam_id = e.id
        WHERE sa.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * Antwoord met alles wat agentic beoordelen nodig heeft: toetspoging
   * (completed_at), toets (exam_id, ai_grading_enabled, titel), vraag
   * (question_text, criteria) en de naam van de student.
   */
  public static function findForAssessment($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT sa.id, sa.student_exam_id, sa.question_id, sa.answer, sa.teacher_score, sa.teacher_feedback,
               se.completed_at, se.exam_id, e.ai_grading_enabled, e.title AS exam_title,
               q.question_text, q.criteria,
               COALESCE(u.name, se.guest_name, 'Gast') AS student_name
        FROM student_answers sa
        JOIN student_exams se ON sa.student_exam_id = se.id
        JOIN exams e ON se.exam_id = e.id
        JOIN questions q ON sa.question_id = q.id
        LEFT JOIN users u ON se.student_id = u.id
        WHERE sa.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * AI-scores van één antwoord per bron: de modellen uit ai_feedback (contract 1:
   * blokken met "Model:" en "Aantal punten:") plus de agentic beoordeling als
   * bron AnswerAssessment::AI_SOURCE. De docentscore hoort hier nooit bij.
   * Dit is de enige plek waar de scores uit ai_feedback worden gelezen.
   *
   * @param string|null $aiFeedback   student_answers.ai_feedback
   * @param int|string|null $agenticScore  score van de actuele agentic beoordeling (zie AnswerAssessment::agenticScoreSql())
   * @return array bron => score
   */
  public static function aiScores(?string $aiFeedback, $agenticScore = null): array {
    $scores = [];
    if ($aiFeedback !== null && $aiFeedback !== '') {
      preg_match_all('/Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)/is', $aiFeedback, $matches, PREG_SET_ORDER);
      foreach ($matches as $match) {
        $scores[trim($match[1])] = (int)$match[2];
      }
    }
    if ($agenticScore !== null && $agenticScore !== '') {
      $scores[AnswerAssessment::AI_SOURCE] = (int)$agenticScore;
    }
    return $scores;
  }

  /**
   * AI-niveaus van één antwoord per bron, bij een toets met grading_scale levels:
   * de modellen uit ai_feedback (contract 1: blokken met "Model:" en "Niveau:")
   * plus de agentic beoordeling als bron AnswerAssessment::AI_SOURCE. Het
   * tegenhanger van aiScores(); de enige plek waar de niveaus uit ai_feedback
   * worden gelezen.
   *
   * @param string|null $aiFeedback    student_answers.ai_feedback
   * @param string|null $agenticLevel  niveau van de actuele agentic beoordeling (zie AnswerAssessment::agenticLevelSql())
   * @return array bron => niveau
   */
  public static function aiLevels(?string $aiFeedback, ?string $agenticLevel = null): array {
    $levels = [];
    if ($aiFeedback !== null && $aiFeedback !== '') {
      preg_match_all('/Model:\s+(.+?)\s+.*?Niveau:\s+(onvoldoende|voldoende|goed|uitstekend)/is', $aiFeedback, $matches, PREG_SET_ORDER);
      foreach ($matches as $match) {
        $levels[trim($match[1])] = strtolower($match[2]);
      }
    }
    if ($agenticLevel !== null && $agenticLevel !== '' && in_array($agenticLevel, Grading::LEVELS, true)) {
      $levels[AnswerAssessment::AI_SOURCE] = $agenticLevel;
    }
    return $levels;
  }

  /**
   * True als de worker het antwoord als mogelijke prompt injection markeerde:
   * ai_feedback begint dan met "WAARSCHUWING:" (vóór de Model:-blokken).
   * Leest net als aiScores() het tekstformaat van contract 1.
   */
  public static function hasInjectionWarning(?string $aiFeedback): bool {
    return $aiFeedback !== null && str_starts_with(ltrim($aiFeedback), 'WAARSCHUWING:');
  }

  public static function updateTeacherGrade($id, $score, $feedback) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE student_answers SET teacher_score = ?, teacher_feedback = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$score, $feedback, $id]);
  }

  /**
   * Docentbeoordeling bij een toets met grading_scale levels: het niveau (of null
   * voor "nog niet beoordeeld") en de feedback. teacher_score blijft NULL.
   */
  public static function updateTeacherLevel($id, ?string $level, $feedback) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE student_answers SET teacher_level = ?, teacher_score = NULL, teacher_feedback = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$level, $feedback, $id]);
  }

  public static function allByStudentExam($studentExamId) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM student_answers WHERE student_exam_id = ?");
    $stmt->execute([$studentExamId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Wachtrij van process_ai_feedback.py. Antwoorden die bij agentic beoordelen
   * horen (een actieve run, of automatisch agentic te beoordelen) vallen erbuiten;
   * zie AnswerAssessment::excludeFromAiGradingSql().
   */
  public static function getPendingAiGrading($limit = null) {
    $pdo = Database::connect();
    [$excludeSql, $params] = AnswerAssessment::excludeFromAiGradingSql('sa', 'q');
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
        $excludeSql
        ORDER BY sa.id ASC
    ";

    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function updateAiFeedback($id, $feedback) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE student_answers SET ai_feedback = ?, ai_updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$feedback, $id]);
    return $stmt->rowCount() > 0;
  }

  /** Id's (ints) van de antwoorden van een toetspoging. */
  public static function answerIdsByStudentExam($studentExamId): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT id FROM student_answers WHERE student_exam_id = ? ORDER BY id ASC");
    $stmt->execute([$studentExamId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
  }

  /**
   * AI-scores per antwoord (id => aiScores()), voor de auditregel van een
   * reset van de AI-resultaten: alleen de scores, niet de hele tekst.
   */
  public static function aiScoresByIds(array $ids): array {
    $ids = array_values(array_map('intval', $ids));
    if (!$ids) {
      return [];
    }
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT sa.id, sa.ai_feedback, " . AnswerAssessment::agenticScoreSql('sa') . " AS agentic_score
        FROM student_answers sa
        WHERE sa.id IN ($placeholders)
        ORDER BY sa.id ASC
    ");
    $stmt->execute($ids);
    $scores = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $scores[(int)$row['id']] = self::aiScores($row['ai_feedback'], $row['agentic_score']);
    }
    return $scores;
  }

  /**
   * Reset van de AI-resultaten: zet de antwoorden terug in de toestand van "net
   * ingeleverd". ai_feedback en ai_updated_at worden leeg en de open en afgeronde
   * agentic runs superseded, in één transactie. Daarna pakken de bestaande
   * mechanismen ze weer op (AI-wachtrij, AnswerAssessment::createAutomaticRuns()).
   * De docentbeoordeling (teacher_score, teacher_feedback) blijft onaangeroerd.
   *
   * @return array ['ai_feedback' => gewiste AI-feedbacks, 'agentic_runs' => vervangen runs]
   */
  public static function resetAiResults(array $studentAnswerIds): array {
    $ids = array_values(array_map('intval', $studentAnswerIds));
    if (!$ids) {
      return ['ai_feedback' => 0, 'agentic_runs' => 0];
    }
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $pdo = Database::connect();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("
          UPDATE student_answers
          SET ai_feedback = NULL, ai_updated_at = NULL
          WHERE id IN ($placeholders) AND ai_feedback IS NOT NULL AND ai_feedback != ''
      ");
      $stmt->execute($ids);
      $feedback = $stmt->rowCount();
      $runs = AnswerAssessment::supersedeActiveRuns($pdo, $ids);
      $pdo->commit();
      return ['ai_feedback' => $feedback, 'agentic_runs' => $runs];
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
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
