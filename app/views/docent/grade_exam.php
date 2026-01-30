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

<h2>Beoordelen (Blind)</h2>
<p>Hier beoordeel je de antwoorden zonder invloed van de AI-feedback.</p>

<?php foreach ($answers as $a): ?>
<div class="card mb-4" id="answer-<?= $a['id'] ?>">
  <div class="card-header bg-light">
      <strong>Vraag:</strong> <?= htmlspecialchars($a['question_text']) ?>
  </div>
  <div class="card-body">
      <div class="mb-3">
          <h6 class="text-muted">Student antwoord:</h6>
          <div class="p-3 bg-white border rounded"><?= nl2br(htmlspecialchars($a['answer'])) ?></div>
      </div>
      
      <hr class="my-4">
      
      <form method="POST" action="/?action=save_teacher_feedback">
      <input type="hidden" name="student_answer_id" value="<?= $a['id'] ?>">
      <input type="hidden" name="student_exam_id" value="<?= $studentExam['id'] ?>">
      <input type="hidden" name="redirect_action" value="grade_student_exam">
      
      <h5 class="mb-3">Docent Beoordeling</h5>
      <div class="mb-3">
          <label class="form-label">Score (0-10)</label>
          <input type="number" name="teacher_score" class="form-control" min="0" max="10" value="<?= htmlspecialchars($a['teacher_score'] ?? '') ?>">
      </div>
      <div class="mb-3">
          <label class="form-label">Feedback</label>
          <textarea name="teacher_feedback" class="form-control" placeholder="Schrijf hier uw feedback..." rows="3"><?= htmlspecialchars($a['teacher_feedback'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Opslaan</button>
  </form>
  </div>
</div>
<?php endforeach; ?>

<?php
 $content = ob_get_clean();
 $title = "Beoordelen (Blind)";
 $breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Resultaten' => '/?action=exam_results&exam_id=' . $studentExam['exam_id'],
    'Beoordelen' => ''
 ];
 require __DIR__ . '/../layouts/main.php';
?>