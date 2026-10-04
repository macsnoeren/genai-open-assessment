<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
$examScale = Grading::examScale($exam ?: null);
$selectedSchemeId = (int)($exam['grading_scheme_id'] ?? ($defaultScheme['id'] ?? 0));
$scaleLabels = [
    Grading::SCALE_POINTS => 'Scoren met punten (0–10, huidige manier)',
    Grading::SCALE_LEVELS => 'Beoordelen met niveaus',
];
ob_start(); ?>

<div class="row justify-content-center">
<div class="col-md-8">

<h2 class="mb-4"><?= htmlspecialchars($title) ?></h2>

<div class="card">
<div class="card-body">
<form action="/?action=<?= e($action) ?>" method="post">
    <?= csrfInput() ?>
    <?php if ($exam): ?>
        <input type="hidden" name="id" value="<?= (int)$exam['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Titel</label>
        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($exam['title'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Omschrijving</label>
        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($exam['description'] ?? '') ?></textarea>
    </div>

    <div class="mb-4">
        <label class="form-label d-block">Beoordeling</label>
        <?php if ($scaleLocked): ?>
            <input type="hidden" name="grading_scale" value="<?= e($examScale) ?>">
            <p class="mb-1"><strong><?= e($scaleLabels[$examScale]) ?></strong></p>
            <div class="form-text">Kan niet meer wijzigen: er zijn al resultaten.</div>
        <?php else: ?>
            <?php foreach ($scaleLabels as $scaleValue => $scaleLabel): ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="grading_scale" id="scale_<?= e($scaleValue) ?>"
                       value="<?= e($scaleValue) ?>" data-scale-choice <?= $examScale === $scaleValue ? 'checked' : '' ?>>
                <label class="form-check-label" for="scale_<?= e($scaleValue) ?>"><?= e($scaleLabel) ?></label>
            </div>
            <?php endforeach; ?>
            <div class="form-text">
                Met punten krijgt elk antwoord een score van 0 t/m 10. Met niveaus krijgt elk antwoord onvoldoende, voldoende,
                goed of uitstekend, en zet een puntenschema die niveaus om in een eindcijfer. De schaal ligt vast zodra er een poging is ingeleverd.
            </div>
        <?php endif; ?>
    </div>

    <div class="mb-4 ps-3 border-start" id="levelsSettings" data-scale-only="levels">
        <div class="mb-3">
            <label class="form-label" for="gradingSchemeSelect">Puntenschema</label>
            <select name="grading_scheme_id" class="form-select" id="gradingSchemeSelect">
                <?php foreach ($gradingSchemes as $scheme): ?>
                    <option value="<?= (int)$scheme['id'] ?>" <?= (int)$scheme['id'] === $selectedSchemeId ? 'selected' : '' ?>>
                        <?= e($scheme['name']) ?> (<?= (int)$scheme['points_voldoende'] ?>/<?= (int)$scheme['points_goed'] ?>/<?= (int)$scheme['points_uitstekend'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                Punten voor voldoende/goed/uitstekend; onvoldoende is altijd 0. Eindcijfer = 10 × punten / (aantal vragen × punten voor uitstekend).
                <a href="/?action=grading_schemes" target="_blank">Puntenschema's</a> bekijken of maken.
                <?php if ($scaleLocked): ?>Een ander schema berekent de cijfers opnieuw; er wordt niets opnieuw beoordeeld.<?php endif; ?>
            </div>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="showGradeLabel" name="show_grade_label" value="1" <?= !empty($exam['show_grade_label']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="showGradeLabel">Toon het eindcijfer als woord</label>
        </div>
        <div class="form-text">De student ziet dan onvoldoende (0–5), voldoende (6–7), goed (8–9) of uitstekend (10) in plaats van het cijfer. Jij ziet allebei.</div>
    </div>

    <div class="mb-3">
        <label class="form-label">AI Prompt</label>
        <select name="prompt_id" class="form-select" id="promptSelect" data-original="<?= e($exam['prompt_id'] ?? '') ?>">
            <option value="">-- Standaard prompt (indien geen geselecteerd) --</option>
            <?php foreach ($prompts as $prompt): ?>
                <option value="<?= (int)$prompt['id'] ?>" data-scale="<?= e($prompt['grading_scale'] ?? Grading::SCALE_POINTS) ?>" <?= ($exam && $exam['prompt_id'] == $prompt['id']) ? 'selected' : '' ?>><?= e($prompt['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Selecteer de prompt die de AI moet gebruiken om de antwoorden te beoordelen. Je ziet alleen prompts voor de gekozen schaal.</div>
    </div>

    <div class="mb-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="aiGradingEnabled" name="ai_grading_enabled" value="1" <?= ($exam && $exam['ai_grading_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="aiGradingEnabled">AI Beoordeling inschakelen</label>
        </div>
        <div class="form-text">Als dit is ingeschakeld, worden ingeleverde antwoorden automatisch opgehaald en beoordeeld door de AI-service.</div>
    </div>

    <div class="mb-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="shared" name="shared" value="1" <?= ($exam && $exam['shared']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="shared">Delen met andere docenten</label>
        </div>
        <div class="form-text">Als dit is ingeschakeld, kunnen andere docenten deze toets inzien en beoordelen. Wijzigen en verwijderen blijft voorbehouden aan de eigenaar.</div>
    </div>

    <div class="mb-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="published" name="published" value="1" <?= ($exam && !empty($exam['published'])) ? 'checked' : '' ?>>
            <label class="form-check-label" for="published">Publiceren voor ingelogde studenten</label>
        </div>
        <div class="form-text">Alleen gepubliceerde toetsen verschijnen in het studentdashboard. De gastlink werkt onafhankelijk van deze instelling.</div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Opslaan</button>
    </div>
</form>
</div>
</div>

</div>
</div>

<script nonce="<?= e(cspNonce()) ?>">
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const promptSelect = document.getElementById('promptSelect');

    // Schaal: puntenschema en woordbeoordeling alleen bij niveaus, en alleen prompts met dezelfde schaal
    const lockedScale = form.querySelector('input[type=hidden][name=grading_scale]');
    function currentScale() {
        if (lockedScale) { return lockedScale.value; }
        const checked = form.querySelector('[data-scale-choice]:checked');
        return checked ? checked.value : 'points';
    }
    function applyScale() {
        const scale = currentScale();
        document.querySelectorAll('[data-scale-only]').forEach(function (el) {
            el.hidden = el.getAttribute('data-scale-only') !== scale;
        });
        if (promptSelect) {
            promptSelect.querySelectorAll('option[data-scale]').forEach(function (opt) {
                const match = opt.getAttribute('data-scale') === scale;
                opt.hidden = !match;
                opt.disabled = !match;
                if (!match && opt.selected) { promptSelect.value = ''; }
            });
        }
    }
    form.querySelectorAll('[data-scale-choice]').forEach(function (el) {
        el.addEventListener('change', applyScale);
    });
    applyScale();
    
    if (form && promptSelect) {
        form.addEventListener('submit', function(e) {
            const original = promptSelect.getAttribute('data-original');
            const current = promptSelect.value;
            const isEdit = form.getAttribute('action').includes('exam_update');
            
            if (isEdit && original !== current && !form.dataset.confirmed) {
                e.preventDefault();
                showConfirmationModal('Je hebt de AI Prompt gewijzigd. Om de consistentie van de rapportages te waarborgen, zullen alle bestaande AI-beoordelingen voor deze toets worden verwijderd en opnieuw worden gegenereerd.\n\nWil je doorgaan?', function() {
                    form.dataset.confirmed = "true";
                    form.requestSubmit();
                });
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