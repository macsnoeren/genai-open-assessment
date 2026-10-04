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
$confidenceLabels = [
    'hoog' => 'hoog: alles wat niet hoog is, laat een mens nakijken (standaard)',
    'middel' => 'middel: alleen lage confidence laat een mens nakijken',
    'laag' => 'laag: alleen harde signalen (modellen oneens, AI vraagt controle)',
];
$currentConfidence = $integration['min_confidence'] ?? 'hoog';
?>

<div class="row justify-content-center">
<div class="col-md-9">

<h2 class="mb-4"><?= e($title) ?></h2>

<div class="card">
<div class="card-body">
<form action="/?action=<?= e($action) ?>" method="post">
    <?= csrfInput() ?>
    <?php if ($integration): ?>
        <input type="hidden" name="id" value="<?= (int)$integration['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label" for="integrationName">Naam</label>
        <input type="text" id="integrationName" name="name" class="form-control" maxlength="<?= (int)MAX_NAME_LENGTH ?>"
               value="<?= e($integration['name'] ?? '') ?>" required placeholder="bv. Leeromgeving Techniek">
        <div class="form-text">De naam van de externe website. Docenten zien deze naam bij de pogingen.</div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="returnOrigin">Origin van de terugkeer-URL</label>
        <input type="text" id="returnOrigin" name="return_origin" class="form-control font-monospace"
               value="<?= e($integration['return_origin'] ?? '') ?>" required placeholder="https://leeromgeving.example">
        <div class="form-text">Alleen schema, host en eventueel poort, zonder pad. Na het inleveren gaat de deelnemer alleen terug naar een URL op precies deze origin.</div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="webhookUrl">Webhook-URL (optioneel)</label>
        <input type="text" id="webhookUrl" name="webhook_url" class="form-control font-monospace"
               value="<?= e($integration['webhook_url'] ?? '') ?>" placeholder="https://leeromgeving.example/hooks/toetsen">
        <div class="form-text">Hier stuurt de applicatie ondertekende seintjes bij ingeleverd, nagekeken en handmatig beoordeeld. Alleen https. Leeg = geen webhooks; de externe website kan dan alleen de API bevragen.</div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="minConfidence">Drempel voor menselijke controle</label>
        <select id="minConfidence" name="min_confidence" class="form-select">
            <?php foreach (Integration::CONFIDENCES as $confidence): ?>
            <option value="<?= e($confidence) ?>" <?= $currentConfidence === $confidence ? 'selected' : '' ?>><?= e($confidenceLabels[$confidence]) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Ligt de confidence van de AI onder deze drempel, dan meldt de API <code>review_needed = true</code> en kijkt een persoon bij de externe website de poging na.</div>
    </div>

    <div class="mb-4">
        <label class="form-label">Toetsen die deze koppeling mag gebruiken</label>
        <?php if (empty($exams)): ?>
            <p class="text-muted small">Er zijn nog geen toetsen.</p>
        <?php endif; ?>
        <?php foreach ($exams as $exam): ?>
            <?php
            $linked = in_array((int)$exam['id'], $selectedExamIds, true);
            $aiOn = !empty($exam['ai_grading_enabled']);
            ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="exam_ids[]" value="<?= (int)$exam['id'] ?>"
                       id="exam<?= (int)$exam['id'] ?>" <?= $linked ? 'checked' : '' ?> <?= (!$aiOn && !$linked) ? 'disabled' : '' ?>>
                <label class="form-check-label" for="exam<?= (int)$exam['id'] ?>"><?= e($exam['title']) ?></label>
                <?php if (!$aiOn && $linked): ?>
                    <span class="badge badge-soft-warning ms-1">AI-beoordeling staat uit</span>
                    <div class="form-text text-warning-emphasis">Deze toets is gekoppeld maar heeft geen AI-beoordeling meer: nieuwe pogingen kunnen niet starten, en lopende pogingen blijven op "grading" staan. Zet AI-beoordeling weer aan of ontkoppel de toets.</div>
                <?php elseif (!$aiOn): ?>
                    <span class="form-text ms-1">Zet eerst AI-beoordeling aan</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$integration): ?>
    <div class="alert alert-info small">
        Na het opslaan krijg je <strong>één keer</strong> de API-key en het webhookgeheim te zien. Geef ze veilig door aan de beheerder van de externe website.
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Opslaan</button>
        <a href="<?= $integration ? '/?action=integration_view&id=' . (int)$integration['id'] : '/?action=integrations' ?>" class="btn btn-outline-secondary">Annuleren</a>
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
    'Koppelingen' => '/?action=integrations',
    $title => ''
];
require __DIR__ . '/../layouts/main.php';
