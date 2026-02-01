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
<div class="col-md-8">

<h2 class="mb-4"><?= htmlspecialchars($title) ?></h2>

<div class="card">
<div class="card-body">
<form action="/?action=<?= $action ?>" method="post">
    <?php if ($exam): ?>
        <input type="hidden" name="id" value="<?= $exam['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Titel</label>
        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($exam['title'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Omschrijving</label>
        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($exam['description'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">AI Prompt</label>
        <select name="prompt_id" class="form-select" id="promptSelect" data-original="<?= $exam['prompt_id'] ?? '' ?>">
            <option value="">-- Standaard prompt (indien geen geselecteerd) --</option>
            <?php foreach ($prompts as $prompt): ?>
                <option value="<?= $prompt['id'] ?>" <?= ($exam && $exam['prompt_id'] == $prompt['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prompt['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Selecteer de prompt die de AI moet gebruiken om de antwoorden te beoordelen.</div>
    </div>

    <div class="mb-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="aiGradingEnabled" name="ai_grading_enabled" value="1" <?= ($exam && $exam['ai_grading_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="aiGradingEnabled">AI Beoordeling inschakelen</label>
        </div>
        <div class="form-text">Als dit is ingeschakeld, worden ingeleverde antwoorden automatisch opgehaald en beoordeeld door de AI-service.</div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Opslaan</button>
    </div>
</form>
</div>
</div>

</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const promptSelect = document.getElementById('promptSelect');
    
    if (form && promptSelect) {
        form.addEventListener('submit', function(e) {
            const original = promptSelect.getAttribute('data-original');
            const current = promptSelect.value;
            const isEdit = form.getAttribute('action').includes('exam_update');
            
            if (isEdit && original !== current) {
                if (!confirm('Je hebt de AI Prompt gewijzigd. Om de consistentie van de rapportages te waarborgen, zullen alle bestaande AI-beoordelingen voor deze toets worden verwijderd en opnieuw worden gegenereerd.\n\nWil je doorgaan?')) {
                    e.preventDefault();
                }
            }
        });
    }
});
</script>

<?php 
$content = ob_get_clean();
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    $title => ''
];
require __DIR__ . '/../layouts/main.php'; 
?>