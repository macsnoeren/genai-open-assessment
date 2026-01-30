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

<?php if (isset($_SESSION['success_message'])): ?>
    <div style="padding: 15px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
        <?= $_SESSION['success_message'] ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<h2>Toets: <?= htmlspecialchars($studentExam['unique_id']) ?></h2>

<form method="POST" action="/?action=submit_exam">
  <input type="hidden" name="student_exam_id" value="<?= $studentExam['id'] ?>">
  
  <?php foreach ($questions as $q): ?>
  <div style="margin-bottom: 25px; padding: 15px; border: 1px solid #eee; border-radius: 5px;">
    <label><strong>Vraag:</strong> <?= htmlspecialchars($q['question_text']) ?></label><br>
    <textarea name="answers[<?= $q['id'] ?>]" style="width: 100%; min-height: 150px; margin-top: 10px; padding: 8px; box-sizing: border-box;"><?= htmlspecialchars($answers[$q['id']]['answer'] ?? '') ?></textarea>
  </div>
  <?php endforeach; ?>
  
  <div style="margin-top: 20px;">
      <button type="submit" name="action_type" value="save" style="padding: 10px 15px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Tussentijds opslaan</button>
      <button type="submit" name="action_type" value="submit" onclick="return confirm('Weet je zeker dat je de toets definitief wilt inleveren? Hierna kun je geen wijzigingen meer maken.')" style="padding: 10px 15px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; float: right;">Definitief inleveren</button>
  </div>
</form>

<?php
$content = ob_get_clean();
$title = "Toets maken";
require __DIR__ . '/../layouts/main.php';
?>
