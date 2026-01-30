<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start();
?>

<a href="/?action=exam_results&exam_id=<?= $studentExam['exam_id'] ?>">Terug naar resultaten</a>

<h2>Beoordelen (Blind)</h2>
<p>Hier beoordeel je de antwoorden zonder invloed van de AI-feedback.</p>

<?php foreach ($answers as $a): ?>
<div class="card" id="answer-<?= $a['id'] ?>">
  <p><strong>Vraag:</strong> <?= htmlspecialchars($a['question_text']) ?></p>
  <p><strong>Student antwoord:</strong> <?= nl2br(htmlspecialchars($a['answer'])) ?></p>

  <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
  
  <form method="POST" action="/?action=save_teacher_feedback" style="background: #f9f9f9; padding: 10px; border-radius: 5px;">
      <input type="hidden" name="student_answer_id" value="<?= $a['id'] ?>">
      <input type="hidden" name="student_exam_id" value="<?= $studentExam['id'] ?>">
      <input type="hidden" name="redirect_action" value="grade_student_exam">
      
      <h4 style="margin-top: 0;">Docent Beoordeling</h4>
      <div style="margin-bottom: 10px;">
          <label>Score (0-10):</label>
          <input type="number" name="teacher_score" min="0" max="10" value="<?= htmlspecialchars($a['teacher_score'] ?? '') ?>" style="width: 60px;">
      </div>
      <textarea name="teacher_feedback" placeholder="Schrijf hier uw feedback..." rows="3" style="width: 100%; box-sizing: border-box;"><?= htmlspecialchars($a['teacher_feedback'] ?? '') ?></textarea>
      <button type="submit" style="margin-top: 5px; font-size: 0.9em;">Opslaan</button>
  </form>
</div>
<?php endforeach; ?>

<?php
 $content = ob_get_clean();
 $title = "Beoordelen (Blind)";
 require __DIR__ . '/../layouts/main.php';
?>