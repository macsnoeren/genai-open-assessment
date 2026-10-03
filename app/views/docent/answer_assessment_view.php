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

$status = $run['status'];
$isPending = $status === AnswerAssessment::STATUS_PENDING;
$hasResult = $run['rubric'] && $run['evidence'] && $run['rounds'] && $run['decision'];
$rubric = $run['rubric'];
$decision = $run['decision'];
$rounds = $run['rounds'] ?? [];
$lastRound = $rounds ? $rounds[count($rounds) - 1] : null;
$runLog = $run['run_log'] ?? [];
$answerText = (string)$run['answer_snapshot'];

$criterionNames = [];
$evidenceByNr = [];
$decisionByNr = [];
if ($hasResult) {
    foreach ($rubric['criteria'] as $c) {
        $criterionNames[$c['nr']] = $c['name'];
    }
    foreach ($run['evidence']['criteria'] as $item) {
        $evidenceByNr[$item['nr']] = $item;
    }
    foreach ($decision['criteria'] as $item) {
        $decisionByNr[$item['nr']] = $item;
    }
}

/** Toont alle criteria van één ronde met de partial; $withDecision alleen bij de laatste ronde. */
$renderRound = function (array $round, bool $withDecision) use ($rubric, $evidenceByNr, $decisionByNr, $answerText) {
    $assessByNr = array_column($round['assessment']['criteria'], null, 'nr');
    $finalByNr = array_column($round['validation']['final_assessment']['criteria'], 'status', 'nr');
    foreach ($rubric['criteria'] as $criterion) {
        $nr = $criterion['nr'];
        $evidenceItem = $evidenceByNr[$nr] ?? null;
        $assessItem = $assessByNr[$nr];
        $finalStatus = $finalByNr[$nr];
        $decisionItem = $withDecision ? ($decisionByNr[$nr] ?? null) : null;
        $corrections = array_values(array_filter($round['validation']['corrections'], fn($c) => $c['nr'] === $nr));
        require __DIR__ . '/answer_assessment_criterion.php';
    }
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Agentic beoordeling</h2>
    <h4 class="text-muted mb-0"><?= e($answer['student_name']) ?></h4>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="badge <?= e(AnswerAssessment::statusClass($status)) ?> fs-6"><?= e(AnswerAssessment::statusLabel($status)) ?></span>
        <span class="small text-muted">
            Run <?= (int)$run['id'] ?> · gestart <?= e($run['created_at']) ?>
            <?php if (!empty($run['requested_by_name'])): ?> door <?= e($run['requested_by_name']) ?><?php endif; ?>
        </span>
        <?php if ($isLatest): ?>
        <form action="/?action=answer_assessment_start" method="post" class="ms-auto mb-0">
            <?= csrfInput() ?>
            <input type="hidden" name="student_answer_id" value="<?= (int)$answer['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $status === AnswerAssessment::STATUS_FAILED ? 'btn-primary' : 'btn-outline-secondary' ?>"
                    data-confirm="<?= $status === AnswerAssessment::STATUS_APPROVED
                        ? 'Dit antwoord opnieuw agentic laten beoordelen? De goedgekeurde beoordeling en de docentscore blijven staan tot je een nieuwe beoordeling goedkeurt.'
                        : 'Dit antwoord opnieuw agentic laten beoordelen? Deze beoordeling wordt dan vervangen.' ?>">Opnieuw beoordelen</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($isPending): ?>
    <div class="card-footer bg-light">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        De AI-agents zijn bezig&hellip; Deze pagina ververst zichzelf.
        <?php if (!$workerActive): ?>
        <div class="text-danger small mt-1">
            De AI-beoordelingsagents zijn op dit moment niet actief; de beoordeling start zodra die weer draaien.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($status === AnswerAssessment::STATUS_FAILED): ?>
    <div class="card-footer bg-light">
        <p class="text-danger mb-0">
            De AI-agents konden dit antwoord niet beoordelen<?= $run['error_message'] ? ': ' . e($run['error_message']) : '.' ?>
        </p>
    </div>
    <?php endif; ?>
    <?php if ($status === AnswerAssessment::STATUS_SUPERSEDED): ?>
    <div class="card-footer bg-light small">
        Deze beoordeling is vervangen door een nieuwere run. Hieronder staat wat deze run opleverde, ter vergelijking.
    </div>
    <?php endif; ?>
</div>

<?php if ($criteriaChanged): ?>
<div class="alert alert-warning">
    De beoordelingscriteria zijn gewijzigd na deze beoordeling. Start opnieuw om met de huidige criteria te beoordelen.
</div>
<?php endif; ?>

<?php if ($hasResult): ?>
<div class="card mb-4 <?= $decision['human_review_needed'] ? 'border-warning border-2' : '' ?>">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <div>
                <div class="small text-muted">Voorgestelde score</div>
                <div class="fs-2 fw-bold"><?= (int)$decision['score'] ?> <span class="fs-6 text-muted">/ 10</span></div>
            </div>
            <div>
                <div class="small text-muted">Confidence</div>
                <span class="badge <?= e(AnswerAssessment::confidenceClass($decision['confidence'])) ?> fs-6"><?= e($decision['confidence']) ?></span>
            </div>
            <?php if ($decision['extra_rounds'] > 0): ?>
            <div>
                <div class="small text-muted">Extra rondes</div>
                <span class="fw-semibold"><?= (int)$decision['extra_rounds'] ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($decision['score_capped']): ?>
            <p class="small text-muted">De score is van 10 naar 5 verlaagd: niet alle essentiële criteria zijn volledig voldaan.</p>
        <?php endif; ?>

        <?php if ($decision['human_review_needed']): ?>
        <div class="alert alert-warning mb-0">
            <strong>Menselijke beoordeling nodig: Ja</strong>
            <?php if ($decision['reasons']): ?>
            <ul class="mb-0 mt-1">
                <?php foreach ($decision['reasons'] as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-success mb-0">
            <strong>Menselijke beoordeling nodig: Nee</strong><br>
            Voorstel van de AI; jij keurt de beoordeling goed.
            <?php if ($decision['reasons']): ?>
            <ul class="mb-0 mt-1">
                <?php foreach ($decision['reasons'] as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header fw-bold">Vraag</div>
    <div class="card-body"><?= nl2br(e($run['question_snapshot'])) ?></div>
</div>

<div class="card mb-4">
    <div class="card-header fw-bold">Studentantwoord</div>
    <div class="card-body"><?= nl2br(e($answerText)) ?></div>
</div>

<?php if ($hasResult): ?>
<h4 class="mb-3">Beoordeling per criterium</h4>
<?php if ($run['evidence']['summary'] !== ''): ?>
    <p class="text-muted"><span class="fw-semibold">Evidence Agent:</span> <?= e($run['evidence']['summary']) ?></p>
<?php endif; ?>
<?php $renderRound($lastRound, true); ?>

<div class="card mb-4">
    <div class="card-header fw-bold">Validatie<?= count($rounds) > 1 ? ' (ronde ' . count($rounds) . ')' : '' ?></div>
    <div class="card-body">
        <?php $validation = $lastRound['validation']; require __DIR__ . '/answer_assessment_validation.php'; ?>
    </div>
</div>

<?php foreach (array_slice($rounds, 0, -1) as $i => $round): ?>
<details class="card mb-4">
    <summary class="card-header fw-bold">Eerdere ronde <?= (int)$i + 1 ?> (voor de extra ronde)</summary>
    <div class="card-body">
        <p class="small text-muted">
            Voorlopige score in deze ronde: Assessment <?= (int)$round['assessment']['score'] ?>,
            Validation <?= (int)$round['validation']['final_assessment']['score'] ?>.
        </p>
        <?php $renderRound($round, false); ?>
        <h5 class="mt-3">Validatie in ronde <?= (int)$i + 1 ?></h5>
        <?php $validation = $round['validation']; require __DIR__ . '/answer_assessment_validation.php'; ?>
    </div>
</details>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($hasResult): ?>
<details class="card mb-4">
    <summary class="card-header fw-bold">Details van de run</summary>
    <div class="card-body">
        <dl class="row small mb-3">
            <dt class="col-sm-3">Modellen</dt>
            <dd class="col-sm-9">
                Evidence: <?= e($runLog['models']['evidence'] ?? '') ?> ·
                Assessment: <?= e($runLog['models']['assessment'] ?? '') ?> ·
                Validation: <?= e($runLog['models']['validation'] ?? '') ?>
            </dd>
            <dt class="col-sm-3">Tijdsduren</dt>
            <dd class="col-sm-9">
                Evidence: <?= e($runLog['durations']['evidence'] ?? '-') ?>s
                <?php foreach ($runLog['durations']['rounds'] ?? [] as $i => $d): ?>
                    · ronde <?= (int)$i + 1 ?>: assessment <?= e($d['assessment'] ?? '-') ?>s, validation <?= e($d['validation'] ?? '-') ?>s
                <?php endforeach; ?>
            </dd>
            <dt class="col-sm-3">Prompt injection</dt>
            <dd class="col-sm-9"><?= !empty($runLog['injection_suspected']) ? 'vermoed' : 'niet vermoed (of niet gecontroleerd)' ?></dd>
            <dt class="col-sm-3">Gestart / klaar</dt>
            <dd class="col-sm-9"><?= e($runLog['started_at'] ?? '') ?> / <?= e($runLog['finished_at'] ?? '') ?></dd>
        </dl>

        <h6>Gebruikte rubric</h6>
        <?php if ($rubric['model_answer'] !== ''): ?>
            <p class="small"><span class="fw-semibold">Modelantwoord:</span> <?= nl2br(e($rubric['model_answer'])) ?></p>
        <?php endif; ?>
        <ol class="small">
            <?php foreach ($rubric['criteria'] as $c): ?>
                <li><span class="badge bg-light text-dark border"><?= e($c['weight']) ?></span> <span class="fw-semibold"><?= e($c['name']) ?>:</span> <?= e($c['description']) ?></li>
            <?php endforeach; ?>
        </ol>
        <ul class="small list-unstyled">
            <?php foreach (AnswerAssessment::LEVELS as $level): ?>
                <li><span class="fw-semibold"><?= e($level) ?> <?= $level === '1' ? 'punt' : 'punten' ?>:</span> <?= e($rubric['levels'][$level] ?? '') ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($rubric['alternatives']): ?>
            <p class="small fw-semibold mb-1">Ook correct:</p>
            <ul class="small mb-0">
                <?php foreach ($rubric['alternatives'] as $alt): ?><li><?= e($alt) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</details>
<?php endif; ?>

<?php if (count($history) > 1): ?>
<div class="card mb-4">
    <div class="card-header fw-bold">Alle agentic beoordelingen van dit antwoord</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($history as $h): ?>
        <li class="list-group-item d-flex flex-wrap align-items-center gap-2 small">
            <span class="badge <?= e(AnswerAssessment::statusClass($h['status'])) ?>"><?= e(AnswerAssessment::statusLabel($h['status'])) ?></span>
            <span>Run <?= (int)$h['id'] ?> · <?= e($h['created_at']) ?></span>
            <?php if ($h['final_score'] !== null): ?><span class="text-muted">voorstel <?= (int)$h['final_score'] ?></span><?php endif; ?>
            <?php if ($h['teacher_score'] !== null): ?><span class="text-muted">docentscore <?= (int)$h['teacher_score'] ?></span><?php endif; ?>
            <?php if ((int)$h['id'] === (int)$run['id']): ?>
                <span class="ms-auto text-muted">deze pagina</span>
            <?php else: ?>
                <a href="/?action=answer_assessment_view&id=<?= (int)$h['id'] ?>" class="ms-auto">Bekijken</a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($isPending): ?>
<script nonce="<?= e(cspNonce()) ?>">
setTimeout(function () { window.location.reload(); }, 10000);
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = 'Agentic beoordeling';
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Resultaten: ' . $answer['exam_title'] => '/?action=exam_results&exam_id=' . (int)$answer['exam_id'],
    'Antwoorden: ' . $answer['student_name'] => '/?action=view_student_answers&student_exam_id=' . (int)$answer['student_exam_id'] . '#answer-' . (int)$answer['id'],
    'Agentic beoordeling' => ''
];
require __DIR__ . '/../layouts/main.php';
