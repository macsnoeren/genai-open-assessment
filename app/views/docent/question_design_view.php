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

$status = $design['status'];
$isPending = QuestionDesign::isPending($status);

// Stap in de statusbalk: 1 = analyse, 2 = voorstel, 3 = beoordeling door de docent
if ($status === QuestionDesign::STATUS_ANALYSIS_PENDING || $status === QuestionDesign::STATUS_AWAITING_ANSWERS) {
    $step = 1;
} elseif ($status === QuestionDesign::STATUS_ASSESSMENT_PENDING) {
    $step = 2;
} elseif ($status === QuestionDesign::STATUS_FAILED) {
    $step = $design['analysis'] ? 2 : 1;
} else {
    $step = 3;
}
$steps = [1 => 'Analyse', 2 => 'Voorstel en validatie', 3 => 'Jouw beoordeling'];
$statusClass = [
    QuestionDesign::STATUS_AWAITING_ANSWERS => 'bg-warning text-dark',
    QuestionDesign::STATUS_REVIEW => 'bg-primary',
    QuestionDesign::STATUS_APPROVED => 'bg-success',
    QuestionDesign::STATUS_FAILED => 'bg-danger',
][$status] ?? 'bg-secondary';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Vraagontwerp</h2>
    <a href="/?action=question_design_delete&id=<?= (int)$design['id'] ?>" class="btn btn-outline-danger btn-sm"
       data-confirm="Dit vraagontwerp verwijderen? Een al toegevoegde vraag blijft bestaan.">Ontwerp verwijderen</a>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="badge <?= e($statusClass) ?> fs-6"><?= e(QuestionDesign::statusLabel($status)) ?></span>
        <ol class="list-inline mb-0 small">
            <?php foreach ($steps as $n => $label): ?>
                <li class="list-inline-item <?= $n === $step ? 'fw-bold' : ($n < $step ? 'text-success' : 'text-muted') ?>">
                    <?= $n < $step ? '&#10003;' : (int)$n . '.' ?> <?= e($label) ?>
                    <?php if ($n < count($steps)): ?><span class="text-muted ms-2">&rarr;</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <span class="small text-muted ms-auto">Ronde <?= (int)$design['revision'] ?> · gestart <?= e($design['created_at']) ?></span>
    </div>
    <?php if ($isPending): ?>
    <div class="card-footer bg-light">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        De AI is bezig&hellip; Deze pagina ververst zichzelf.
    </div>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card-header fw-bold">Oorspronkelijke vraag en gewenst antwoord</div>
    <div class="card-body">
        <h6 class="text-muted">Vraag</h6>
        <p><?= nl2br(e($design['question_text'])) ?></p>
        <h6 class="text-muted">Gewenst antwoord</h6>
        <p class="mb-0"><?= nl2br(e($design['model_answer'])) ?></p>
    </div>
</div>

<?php if ($isPending): ?>
<script nonce="<?= e(cspNonce()) ?>">
setTimeout(function () { window.location.reload(); }, 10000);
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = 'Vraagontwerp';
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Vragen: ' . $exam['title'] => '/?action=questions&exam_id=' . (int)$exam['id'],
    'Vraagontwerp' => ''
];
require __DIR__ . '/../layouts/main.php';
