<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start(); ?>

<div class="row justify-content-center">
<div class="col-lg-10">

<h2 class="mb-4"><?= htmlspecialchars($title) ?></h2>

<div class="card">
<div class="card-body">
<form action="/?action=<?= $action ?>" method="post">
    <input type="hidden" name="exam_id" value="<?= $examId ?>">
    <?php if ($question): ?>
        <input type="hidden" name="id" value="<?= $question['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Vraag</label>
        <textarea name="question_text" class="form-control" rows="3" required><?= htmlspecialchars($question['question_text'] ?? '') ?></textarea>
    </div>

    <div class="mb-4">
        <label class="form-label">Beoordelingscriteria (voor AI)</label>
        <div class="form-text mb-2">
            Beschrijf waaraan het antwoord moet voldoen voor 0, 1, 5 of 10 punten.
        </div>
        <textarea name="criteria" class="form-control font-monospace" rows="6" required><?= htmlspecialchars($question['criteria'] ?? '') ?></textarea>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Opslaan</button>
    </div>
</form>
</div>
</div>

</div>
</div>

<?php 
$content = ob_get_clean();
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Vragen' => '/?action=questions&exam_id=' . $examId,
    $title => ''
];
require __DIR__ . '/../layouts/main.php'; 
?>