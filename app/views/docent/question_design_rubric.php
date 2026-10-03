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
                <th style="width: 20%">Criterium</th>
                <th style="width: 40%">Omschrijving</th>
                <th style="width: 10%">Gewicht</th>
                <th style="width: 30%">Waarom</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rubric['criteria'] as $criterion): ?>
            <tr>
                <td class="fw-semibold"><?= e($criterion['name']) ?></td>
                <td><?= e($criterion['description']) ?></td>
                <td>
                    <span class="badge <?= $criterion['weight'] === 'essentieel' ? 'bg-danger' : 'bg-secondary' ?>">
                        <?= e($criterion['weight']) ?>
                    </span>
                </td>
                <td class="small text-muted"><?= e($criterion['why']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h6>Puntentoekenning</h6>
<dl class="row mb-3">
    <?php foreach (['level_10' => '10 punten', 'level_5' => '5 punten', 'level_1' => '1 punt', 'level_0' => '0 punten'] as $key => $label): ?>
        <dt class="col-sm-2"><?= e($label) ?></dt>
        <dd class="col-sm-10"><?= e($rubric[$key]) ?></dd>
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
