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
 * Partial (zonder ob_start en layout): toont een rubric.
 * Verwacht $rubric (genormaliseerd, zie QuestionDesign::normalizeRubric()).
 */
?>
<div class="table-responsive">
    <table class="table table-sm align-top">
        <thead class="table-light">
            <tr>
                <th class="col-w-20">Criterium</th>
                <th class="col-w-40">Omschrijving</th>
                <th class="col-w-10">Gewicht</th>
                <th class="col-w-30">Waarom</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rubric['criteria'] as $criterion): ?>
            <tr>
                <td class="fw-semibold"><?= e($criterion['name']) ?></td>
                <td><?= e($criterion['description']) ?></td>
                <td>
                    <span class="badge <?= $criterion['weight'] === 'essentieel' ? 'badge-soft-danger' : 'badge-soft-secondary' ?>">
                        <?= e($criterion['weight']) ?>
                    </span>
                </td>
                <td class="small text-muted"><?= e($criterion['why']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php $rubricScale = QuestionDesign::rubricScale($rubric); ?>
<h6><?= $rubricScale === 'levels' ? 'Niveaus' : 'Puntentoekenning' ?></h6>
<?php if ($rubricScale === 'levels'): ?>
<p class="small text-muted mb-1">Het niveau volgt uit de criteria: alle essentiële voldaan is voldoende; met aanvullende criteria erbij goed of uitstekend.</p>
<?php endif; ?>
<dl class="row mb-3">
    <?php foreach (QuestionDesign::LEVEL_KEYS[$rubricScale] as $key): ?>
        <dt class="col-sm-2"><?= e(QuestionDesign::LEVEL_LABELS[$key]) ?></dt>
        <dd class="col-sm-10"><?= e($rubric[$key] ?? '') ?></dd>
    <?php endforeach; ?>
</dl>

<h6>Alternatieve correcte antwoorden</h6>
<?php if (empty($rubric['alternative_answers'])): ?>
    <p class="text-muted">Geen.</p>
<?php else: ?>
<ul>
    <?php foreach ($rubric['alternative_answers'] as $alt): ?>
        <li><?= e($alt) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
