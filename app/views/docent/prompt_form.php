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
<form action="/?action=<?= e($action) ?>" method="post">
    <?= csrfInput() ?>
    <?php if ($prompt): ?>
        <input type="hidden" name="id" value="<?= (int)$prompt['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Titel</label>
        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($prompt['title'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Beschrijving</label>
        <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($prompt['description'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label d-block">Schaal</label>
        <?php $promptScale = $prompt['grading_scale'] ?? Grading::SCALE_POINTS; ?>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="grading_scale" id="promptScalePoints" value="points" <?= $promptScale === 'points' ? 'checked' : '' ?>>
            <label class="form-check-label" for="promptScalePoints">Punten (score 0, 1, 5 of 10)</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="grading_scale" id="promptScaleLevels" value="levels" <?= $promptScale === 'levels' ? 'checked' : '' ?>>
            <label class="form-check-label" for="promptScaleLevels">Niveaus (onvoldoende, voldoende, goed, uitstekend)</label>
        </div>
        <div class="form-text">Een toets kan alleen een prompt met dezelfde schaal kiezen. Bij niveaus vraagt de prompt om een <code>level</code> in plaats van een <code>score</code>.</div>
    </div>

    <div class="mb-4">
        <label class="form-label">Prompt Tekst</label>
        <div class="form-text mb-2">
            Gebruik variabelen zoals {{question_text}}, {{criteria}} en {{student_answer}}. <a href="/?action=prompt_help" target="_blank">Bekijk de hulp</a> voor meer info.
        </div>
        <textarea name="prompt_text" class="form-control font-monospace" rows="15" required><?= htmlspecialchars($prompt['prompt_text'] ?? '') ?></textarea>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Opslaan</button>
        <a href="/?action=prompts" class="btn btn-outline-secondary">Annuleren</a>
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
    'Prompts' => '/?action=prompts',
    $title => ''
];
require __DIR__ . '/../layouts/main.php'; 
?>