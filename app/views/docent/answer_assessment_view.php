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
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Agentic beoordeling</h2>
    <h4 class="text-muted mb-0"><?= e($answer['student_name']) ?></h4>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="badge <?= e(AnswerAssessment::statusClass($status)) ?> fs-6"><?= e(AnswerAssessment::statusLabel($status)) ?></span>
        <span class="small text-muted ms-auto">
            Run <?= (int)$run['id'] ?> · gestart <?= e($run['created_at']) ?>
            <?php if (!empty($run['requested_by_name'])): ?> door <?= e($run['requested_by_name']) ?><?php endif; ?>
        </span>
    </div>
    <?php if ($isPending): ?>
    <div class="card-footer bg-light">
        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
        De AI-agents zijn bezig&hellip; Deze pagina ververst zichzelf.
    </div>
    <?php endif; ?>
    <?php if ($status === AnswerAssessment::STATUS_FAILED): ?>
    <div class="card-footer bg-light">
        <p class="text-danger mb-0">
            De AI-agents konden dit antwoord niet beoordelen<?= $run['error_message'] ? ': ' . e($run['error_message']) : '.' ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card-header fw-bold">Vraag</div>
    <div class="card-body"><?= nl2br(e($run['question_snapshot'])) ?></div>
</div>

<div class="card mb-4">
    <div class="card-header fw-bold">Studentantwoord</div>
    <div class="card-body"><?= nl2br(e($run['answer_snapshot'])) ?></div>
</div>

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
