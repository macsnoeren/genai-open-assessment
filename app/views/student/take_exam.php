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

$studentDisplayName = $isGuest ? ($studentExam['guest_name'] ?? 'Gast') : ($_SESSION['name'] ?? 'Student');
$backToAppUrl = $isGuest ? '/' : '/?action=student_dashboard';
?>

<div class="exam-topbar">
  <div class="container exam-topbar-inner">
    <div class="exam-topbar-brand">
        <img src="/images/logo-h.png" alt="Logo" class="exam-topbar-logo">
        <span class="exam-topbar-name"><?= htmlspecialchars($studentDisplayName) ?></span>
    </div>
    <?php if (!$studentExam['completed_at']): ?>
    <div class="exam-topbar-actions">
        <button type="submit" form="examForm" name="action_type" value="save" class="btn btn-sm btn-outline-light">Tussentijds opslaan</button>
        <button type="submit" form="examForm" name="action_type" value="submit" class="btn btn-sm btn-light fw-bold" data-confirm="Weet je zeker dat je de toets definitief wilt inleveren? Hierna kun je geen wijzigingen meer maken.">Definitief inleveren</button>
    </div>
    <?php endif; ?>
  </div>
</div>
<div class="exam-topbar-spacer"></div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success mb-4">
        <?= $_SESSION['success_message'] ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if ($studentExam['completed_at']): ?>
    <div class="alert alert-info mb-4">
        <h4>Toets ingeleverd</h4>
        <p>Je hebt deze toets ingeleverd op <?= $studentExam['completed_at'] ?>. Je kunt je antwoorden niet meer wijzigen.</p>
    </div>
    <a href="<?= $backToAppUrl ?>" class="btn btn-primary">Terug naar hoofdapplicatie</a>
<?php else: ?>

<h2 class="mb-4">Toets maken</h2>
<p class="text-muted mb-4">ID: <?= htmlspecialchars($studentExam['unique_id']) ?></p>

<form id="examForm" method="POST" action="/?action=submit_exam">
  <?= csrfInput() ?>
  <input type="hidden" name="student_exam_id" value="<?= $studentExam['id'] ?>">

  <?php foreach ($questions as $q): ?>
  <div class="card mb-4">
    <div class="card-body">
        <label class="form-label fw-bold"><?= htmlspecialchars($q['question_text']) ?></label>
        <textarea name="answers[<?= $q['id'] ?>]" class="form-control" rows="6" placeholder="Typ hier je antwoord..."><?= htmlspecialchars($answers[$q['id']]['answer'] ?? '') ?></textarea>
    </div>
  </div>
  <?php endforeach; ?>
</form>

<?php endif; ?>

<?php
$content = ob_get_clean();
$title = "Toets maken";
$hideHeaderFooter = true;
$breadcrumbs = [];
require __DIR__ . '/../layouts/main.php';
?>
