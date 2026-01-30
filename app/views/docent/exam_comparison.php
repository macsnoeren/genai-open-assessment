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
?>

<h2 class="mb-4">AI Model Vergelijking: <?= htmlspecialchars($exam['title']) ?></h2>

<?php if (empty($comparisonData)): ?>
    <div class="alert alert-warning">
        Er zijn nog geen resultaten beschikbaar die zowel door de docent als door de AI zijn beoordeeld.
        Zorg ervoor dat de AI-service heeft gedraaid en dat u handmatige beoordelingen heeft ingevoerd.
    </div>
<?php else: ?>

    <!-- Statistieken Tabel -->
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">
            Statistische Analyse (Score 0-10)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Beoordelaar</th>
                            <th>Gemiddelde Score</th>
                            <th>Standaarddeviatie</th>
                            <th>Afwijking t.o.v. Docent (MAE)</th>
                            <th>Correlatie met Docent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $name => $data): ?>
                        <tr <?= $name === 'Docent' ? 'class="table-primary"' : '' ?>>
                            <td class="fw-bold"><?= htmlspecialchars($name) ?></td>
                            <td><?= isset($data['mean']) ? number_format($data['mean'], 2) : '-' ?></td>
                            <td><?= isset($data['std_dev']) ? number_format($data['std_dev'], 2) : '-' ?></td>
                            <td>
                                <?php if ($name !== 'Docent' && isset($data['mae'])): ?>
                                    <?= number_format($data['mae'], 2) ?>
                                    <small class="text-muted d-block" style="font-size: 0.75em;">(lager is beter)</small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($name !== 'Docent' && isset($data['correlation'])): ?>
                                    <?= number_format($data['correlation'], 2) ?>
                                    <small class="text-muted d-block" style="font-size: 0.75em;">(dichter bij 1 is beter)</small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detail Tabel -->
    <div class="card">
        <div class="card-header bg-light fw-bold">
            Detailoverzicht per Vraag
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 15%;">Student</th>
                            <th style="width: 35%;">Vraag</th>
                            <th class="text-center table-primary" style="width: 10%;">Docent</th>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <th class="text-center"><?= htmlspecialchars($model) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparisonData as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['student']) ?></td>
                            <td><small><?= htmlspecialchars(substr($row['question'], 0, 100)) ?>...</small></td>
                            <td class="text-center table-primary fw-bold"><?= $row['teacher_score'] ?></td>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <td class="text-center">
                                    <?php 
                                    if (isset($row['models'][$model])) {
                                        $score = $row['models'][$model];
                                        $diff = $score - $row['teacher_score'];
                                        $color = $diff == 0 ? 'text-success' : ($diff > 0 ? 'text-danger' : 'text-warning');
                                        echo "$score <small class='$color'>(" . ($diff > 0 ? '+' : '') . "$diff)</small>";
                                    } else {
                                        echo "-";
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = "Vergelijk Resultaten";
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Vergelijk' => ''
];
require __DIR__ . '/../layouts/main.php';
?>