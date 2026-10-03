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

<?php
// Tijdens een nieuwe ronde staat de uitvoer van de vorige ronde er nog; markeer die.
$previousRound = $isPending ? ' <span class="badge bg-light text-muted border ms-2">vorige ronde</span>' : '';
?>

<?php if (!empty($design['teacher_feedback'])): ?>
<div class="card mb-4">
    <div class="card-header fw-bold">Jouw laatste bijsturing</div>
    <div class="card-body"><?= nl2br(e($design['teacher_feedback'])) ?></div>
</div>
<?php endif; ?>

<?php if ($design['assessment']): ?>
<div class="card mb-4">
    <div class="card-header fw-bold">Assessmentvoorstel<?= $previousRound ?></div>
    <div class="card-body">
        <?php $rubric = $design['assessment']['rubric']; require __DIR__ . '/question_design_rubric.php'; ?>
        <?php if ($design['assessment']['explanation'] !== ''): ?>
            <h6>Uitleg</h6>
            <p class="mb-0"><?= e($design['assessment']['explanation']) ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php $validation = $design['validation']; ?>
<?php if ($validation): ?>
<div class="card mb-4">
    <div class="card-header fw-bold">Validatie<?= $previousRound ?></div>
    <div class="card-body">
        <h6>Controles</h6>
        <ul class="list-unstyled">
            <?php foreach ($validation['checks'] as $check): ?>
            <li class="mb-2">
                <?php if ($check['ok']): ?>
                    <span class="text-success fw-bold" aria-label="in orde">&#10003;</span>
                <?php else: ?>
                    <span class="text-danger fw-bold" aria-label="niet in orde">&#10007;</span>
                <?php endif; ?>
                <span class="fw-semibold"><?= e(QuestionDesign::checkLabel($check['check'])) ?></span>
                <?php if ($check['comment'] !== ''): ?><br><small class="text-muted ms-4"><?= e($check['comment']) ?></small><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <h6>Belangrijkste wijzigingen</h6>
        <?php if (empty($validation['changes'])): ?>
            <p class="text-muted">Geen wijzigingen ten opzichte van het voorstel.</p>
        <?php else: ?>
        <ul>
            <?php foreach ($validation['changes'] as $change): ?>
            <li><?= e($change['change']) ?>
                <?php if ($change['why'] !== ''): ?><br><small class="text-muted">Waarom: <?= e($change['why']) ?></small><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <h6 class="mt-4">Verbeterde rubric</h6>
        <?php $rubric = $validation['rubric']; require __DIR__ . '/question_design_rubric.php'; ?>

        <?php if ($validation['explanation'] !== ''): ?>
            <h6>Uitleg</h6>
            <p><?= e($validation['explanation']) ?></p>
        <?php endif; ?>
        <?php if ($validation['suggested_question_text'] !== ''): ?>
            <h6>Voorgestelde vraagtekst</h6>
            <p class="mb-0"><?= nl2br(e($validation['suggested_question_text'])) ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($status === QuestionDesign::STATUS_REVIEW && $validation): ?>
<div class="card mb-4 border-primary">
    <div class="card-header fw-bold">Aanpassen en goedkeuren</div>
    <div class="card-body">
        <p class="text-muted">
            Pas de vraag en de beoordelingscriteria naar wens aan. Pas na goedkeuring komt de vraag in de toets;
            je kunt hem daarna nog gewoon bewerken.
        </p>
        <form action="/?action=question_design_approve" method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= (int)$design['id'] ?>">
            <input type="hidden" name="revision" value="<?= (int)$design['revision'] ?>">
            <div class="mb-3">
                <label class="form-label" for="approveQuestion">Vraag</label>
                <?php if ($validation['suggested_question_text'] !== ''): ?>
                <div class="alert alert-info py-2 small mb-2">
                    <strong>Suggestie van de AI:</strong> <?= nl2br(e($validation['suggested_question_text'])) ?><br>
                    Neem deze zelf over als je hem wilt gebruiken.
                </div>
                <?php endif; ?>
                <textarea name="question_text" id="approveQuestion" class="form-control" rows="3" required
                          maxlength="<?= (int)MAX_DESIGN_TEXT_LENGTH ?>"><?= e($design['question_text']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="approveCriteria">Beoordelingscriteria</label>
                <div class="form-text mt-0 mb-1">Opgebouwd uit de gevalideerde rubric. Dit is de tekst die de AI bij het beoordelen gebruikt.</div>
                <textarea name="criteria" id="approveCriteria" class="form-control font-monospace" rows="14" required><?=
                    e(QuestionDesign::rubricToCriteriaText($design['model_answer'], $validation['rubric'])) ?></textarea>
            </div>
            <button type="submit" class="btn btn-success"
                    data-confirm="De vraag met deze criteria toevoegen aan de toets?">Goedkeuren en vraag toevoegen</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-bold">Bijsturen</div>
    <div class="card-body">
    <?php if ((int)$design['revision'] >= DESIGN_MAX_REVISIONS): ?>
        <p class="text-muted mb-0">Maximaal aantal rondes bereikt; pas de rubric zelf aan in het formulier hierboven.</p>
    <?php else: ?>
        <p class="text-muted">
            Niet tevreden? Beschrijf wat er anders moet. De AI maakt dan een nieuw voorstel en valideert dat opnieuw.
        </p>
        <form action="/?action=question_design_feedback" method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= (int)$design['id'] ?>">
            <input type="hidden" name="revision" value="<?= (int)$design['revision'] ?>">
            <div class="mb-3">
                <label class="form-label" for="designFeedback">Feedback voor de AI</label>
                <textarea name="feedback" id="designFeedback" class="form-control" rows="3" required
                          maxlength="<?= (int)MAX_DESIGN_INPUT_LENGTH ?>"></textarea>
            </div>
            <button type="submit" class="btn btn-outline-primary">Opnieuw laten uitwerken</button>
        </form>
    <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($status === QuestionDesign::STATUS_APPROVED): ?>
<div class="alert alert-success">
    Goedgekeurd op <?= e($design['approved_at']) ?>.
    <?php if ($design['question_id']): ?>
        De vraag staat in de toets: <a href="/?action=questions&exam_id=<?= (int)$exam['id'] ?>" class="alert-link">naar de vragen</a>.
    <?php else: ?>
        De vraag die uit dit ontwerp is gemaakt, is inmiddels uit de toets verwijderd.
        <a href="/?action=questions&exam_id=<?= (int)$exam['id'] ?>" class="alert-link">Naar de vragen</a>.
    <?php endif; ?>
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
