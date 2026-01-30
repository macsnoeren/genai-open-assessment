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

<h2 class="mb-4">Student antwoorden</h2>

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
      
      <div class="mb-3">
              <small class="text-muted d-block">Criteria:</small>
              <div class="small text-secondary"><?= nl2br(htmlspecialchars($a['criteria'])) ?></div>
      </div>

      <?php if ($a['ai_feedback']): ?>
      <div class="alert alert-info">
          <strong>AI feedback:</strong><br>
          <?= nl2br(htmlspecialchars($a['ai_feedback'])) ?>
      </div>
      <?php endif; ?>

      <div class="mt-3 pt-3 border-top">
          <div class="d-flex justify-content-between align-items-start">
              <div>
                  <strong>Docent feedback:</strong><br>
                  <?php if (!empty($a['teacher_feedback'])): ?>
                      <?= nl2br(htmlspecialchars($a['teacher_feedback'])) ?>
                  <?php else: ?>
                      <em class="text-muted">Nog geen feedback gegeven.</em>
                  <?php endif; ?>
              </div>
              <div class="text-end">
                  <span class="badge bg-primary fs-6">Score: <?= isset($a['teacher_score']) ? htmlspecialchars($a['teacher_score']) : '-' ?></span>
              </div>
          </div>
      </div>
  </div>
</div>
<?php endforeach; ?>

<?php
 $content = ob_get_clean();
 $title = "Student antwoorden";
 $breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Resultaten' => '/?action=exam_results&exam_id=' . $studentExam['exam_id'],
    'Student antwoorden' => ''
 ];
 require __DIR__ . '/../layouts/main.php';
?>
