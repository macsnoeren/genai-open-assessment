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

<?php $analysis = $design['analysis']; ?>
<?php if ($analysis): ?>
<div class="card mb-4">
    <div class="card-header fw-bold">Analyse</div>
    <div class="card-body">
        <p><?= e($analysis['summary']) ?></p>
        <p>
            <span class="badge <?= $analysis['question_clear'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                Vraag duidelijk: <?= $analysis['question_clear'] ? 'ja' : 'nee' ?>
            </span>
            <span class="badge <?= $analysis['answer_matches_question'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                Gewenst antwoord past bij de vraag: <?= $analysis['answer_matches_question'] ? 'ja' : 'nee' ?>
            </span>
        </p>

        <h6>Essentiële elementen</h6>
        <ul>
            <?php foreach ($analysis['essential_elements'] as $item): ?>
            <li><?= e($item['element']) ?>
                <?php if ($item['why'] !== ''): ?><br><small class="text-muted">Waarom: <?= e($item['why']) ?></small><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <h6>Beoordelingsproblemen</h6>
        <?php if (empty($analysis['issues'])): ?>
            <p class="text-muted mb-0">Geen problemen gesignaleerd.</p>
        <?php else: ?>
        <ul class="mb-0">
            <?php foreach ($analysis['issues'] as $item): ?>
            <li><?= e($item['issue']) ?>
                <?php if ($item['why'] !== ''): ?><br><small class="text-muted">Waarom: <?= e($item['why']) ?></small><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-bold">Verduidelijkende vragen</div>
    <div class="card-body">
    <?php if ($status === QuestionDesign::STATUS_AWAITING_ANSWERS): ?>
        <p class="text-muted">
            De AI heeft meer informatie nodig om een goede rubric te maken. Een antwoord mag leeg blijven;
            de AI maakt dan zelf een redelijke keuze.
        </p>
        <form action="/?action=question_design_answer" method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= (int)$design['id'] ?>">
            <input type="hidden" name="revision" value="<?= (int)$design['revision'] ?>">
            <?php foreach ($analysis['clarifying_questions'] as $i => $q): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="answer_<?= (int)$i ?>"><?= (int)$i + 1 ?>. <?= e($q['question']) ?></label>
                <?php if ($q['why'] !== ''): ?>
                    <div class="form-text mt-0 mb-1"><em>Waarom: <?= e($q['why']) ?></em></div>
                <?php endif; ?>
                <textarea name="answer_<?= (int)$i ?>" id="answer_<?= (int)$i ?>" class="form-control" rows="2"
                          maxlength="<?= (int)MAX_DESIGN_INPUT_LENGTH ?>"></textarea>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">Antwoorden versturen</button>
        </form>
    <?php elseif (!empty($design['teacher_answers'])): ?>
        <?php foreach ($design['teacher_answers'] as $i => $a): ?>
        <div class="mb-3">
            <div class="fw-semibold"><?= (int)$i + 1 ?>. <?= e($a['question'] ?? '') ?></div>
            <?php if (($a['why'] ?? '') !== ''): ?>
                <div class="small text-muted"><em>Waarom: <?= e($a['why']) ?></em></div>
            <?php endif; ?>
            <div class="mt-1">
                <?php if (($a['answer'] ?? '') === ''): ?>
                    <span class="text-muted">(niet beantwoord)</span>
                <?php else: ?>
                    <?= nl2br(e($a['answer'])) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted mb-0">De AI had geen verduidelijkende vragen nodig.</p>
    <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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
