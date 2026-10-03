<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * De uitvoer van de Validation Agent in één ronde (zonder layout).
 *
 * Verwacht:
 * - $validation     genormaliseerde validatie {checks, validated, issues, corrections, final_assessment, confidence, explanation}
 * - $criterionNames array nr => naam van het criterium
 */
?>
<p>
    <span class="badge <?= $validation['validated'] ? 'bg-success' : 'bg-warning text-dark' ?>">
        <?= $validation['validated'] ? 'Beoordeling bevestigd' : 'Beoordeling niet bevestigd' ?>
    </span>
    <span class="badge <?= e(AnswerAssessment::confidenceClass($validation['confidence'])) ?>">confidence <?= e($validation['confidence']) ?></span>
    <span class="badge bg-light text-dark border">eindscore validatie: <?= (int)$validation['final_assessment']['score'] ?></span>
</p>

<h6>Controles</h6>
<ul class="list-unstyled">
    <?php foreach ($validation['checks'] as $check): ?>
    <li class="mb-2">
        <?php if ($check['ok']): ?>
            <span class="text-success fw-bold" aria-label="in orde">&#10003;</span>
        <?php else: ?>
            <span class="text-danger fw-bold" aria-label="niet in orde">&#10007;</span>
        <?php endif; ?>
        <span class="fw-semibold"><?= e(AnswerAssessment::checkLabel($check['check'])) ?></span>
        <?php if ($check['comment'] !== ''): ?><br><small class="text-muted ms-4"><?= e($check['comment']) ?></small><?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>

<h6>Problemen</h6>
<?php if (empty($validation['issues'])): ?>
    <p class="text-muted">Geen problemen gesignaleerd.</p>
<?php else: ?>
<ul>
    <?php foreach ($validation['issues'] as $issue): ?>
    <li>
        <span class="fw-semibold"><?= $issue['nr'] === 0 ? 'Algemeen' : 'Criterium ' . (int)$issue['nr'] . ' (' . e($criterionNames[$issue['nr']] ?? '?') . ')' ?>:</span>
        <?= e($issue['issue']) ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<h6>Correcties</h6>
<?php if (empty($validation['corrections'])): ?>
    <p class="text-muted">Geen correcties op de voorlopige beoordeling.</p>
<?php else: ?>
<ul>
    <?php foreach ($validation['corrections'] as $correction): ?>
    <li>
        Criterium <?= (int)$correction['nr'] ?> (<?= e($criterionNames[$correction['nr']] ?? '?') ?>):
        <?= e($correction['from']) ?> &rarr; <?= e($correction['to']) ?><?php if ($correction['why'] !== ''): ?>: <?= e($correction['why']) ?><?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ($validation['explanation'] !== ''): ?>
    <h6>Uitleg</h6>
    <p class="mb-0"><?= e($validation['explanation']) ?></p>
<?php endif; ?>
