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
    <div class="alert alert-success mb-4">
        <?= $_SESSION['success_message'] ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<h2 class="mb-4">Toets maken</h2>
<p class="text-muted mb-4">ID: <?= htmlspecialchars($studentExam['unique_id']) ?></p>

<form method="POST" action="/?action=submit_exam">
  <input type="hidden" name="student_exam_id" value="<?= $studentExam['id'] ?>">
  
  <?php foreach ($questions as $q): ?>
  <div class="card mb-4">
    <div class="card-body">
        <label class="form-label fw-bold"><?= htmlspecialchars($q['question_text']) ?></label>
        <textarea name="answers[<?= $q['id'] ?>]" class="form-control" rows="6" placeholder="Typ hier je antwoord..."><?= htmlspecialchars($answers[$q['id']]['answer'] ?? '') ?></textarea>
    </div>
  </div>
  <?php endforeach; ?>
  
  <div class="d-flex justify-content-between mt-4 mb-5">
      <button type="submit" name="action_type" value="save" class="btn btn-secondary">Tussentijds opslaan</button>
      <button type="submit" name="action_type" value="submit" class="btn btn-success btn-lg" onclick="return confirm('Weet je zeker dat je de toets definitief wilt inleveren? Hierna kun je geen wijzigingen meer maken.')">Definitief inleveren</button>
  </div>
</form>

<?php
$content = ob_get_clean();
$title = "Toets maken";
require __DIR__ . '/../layouts/main.php';
?>
