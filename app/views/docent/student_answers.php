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

<a href="/?action=exam_results&exam_id=<?= $studentExam['exam_id'] ?>">← Terug naar resultaten</a>

<h2>Student antwoorden</h2>

<?php foreach ($answers as $a): ?>
<div class="card" id="answer-<?= $a['id'] ?>">
  <p><strong>Vraag:</strong> <?= htmlspecialchars($a['question_text']) ?></p>
  <p><strong>Student antwoord:</strong> <?= nl2br(htmlspecialchars($a['answer'])) ?></p>
  <p><strong>Model antwoord:</strong> <?= nl2br(htmlspecialchars($a['model_answer'])) ?></p>
  <p><strong>Criteria:</strong> <?= nl2br(htmlspecialchars($a['criteria'])) ?></p>
  <?php if ($a['ai_feedback']): ?>
  <p><strong>AI feedback:</strong><br>
    <?= nl2br(htmlspecialchars($a['ai_feedback'])) ?>
  </p>
  <?php endif; ?>

  <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
  
  <p><strong>Docent score:</strong> <?= isset($a['teacher_score']) ? htmlspecialchars($a['teacher_score']) : '-' ?></p>
  
  <p><strong>Docent feedback:</strong><br>
    <?php if (!empty($a['teacher_feedback'])): ?>
        <?= nl2br(htmlspecialchars($a['teacher_feedback'])) ?>
    <?php else: ?>
        <em>Nog geen feedback gegeven.</em>
    <?php endif; ?>
  </p>
</div>
<?php endforeach; ?>

<?php
 $content = ob_get_clean();
 $title = "Student antwoorden";
 require __DIR__ . '/../layouts/main.php';
?>
