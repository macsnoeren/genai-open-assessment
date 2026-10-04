<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
$isLevels = !empty($isLevels);
// Grafiek: bij niveaus de niveau-index 0-3 (onvoldoende ... uitstekend) als as, met labels
$chartRows = $comparisonData;
$chartMax = 10;
$chartLabels = null;
if ($isLevels) {
    $chartRows = array_map(fn($row) => [
        'teacher_score' => Grading::levelIndex($row['teacher_level']),
        'models' => array_map(fn($level) => Grading::levelIndex($level), $row['models']),
    ], $comparisonData);
    $chartMax = count(Grading::LEVELS) - 1;
    $chartLabels = array_map(fn($level) => Grading::levelLabel($level), Grading::LEVELS);
}
ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Vergelijk AI en docent</h1>
        <p class="text-muted mb-0"><?= e($exam['title']) ?></p>
    </div>
    <?php if (!empty($comparisonData)): ?>
        <div class="page-header-actions">
            <button type="button" id="exportPdfBtn" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Export PDF</button>
            <a href="/?action=exam_comparison_export&amp;exam_id=<?= (int)$exam['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV</a>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($comparisonData)): ?>
    <div class="alert alert-warning">
        Er zijn nog geen resultaten beschikbaar die zowel door de docent als door de AI zijn beoordeeld.
        Zorg ervoor dat de AI-service heeft gedraaid en dat u handmatige beoordelingen heeft ingevoerd.
    </div>
<?php else: ?>

    <!-- Container voor PDF generatie -->
    <div id="report-content" class="report-sheet">
        
    <!-- Titelblad / Info -->
    <div class="mb-5">
        <h1 class="display-6">Rapportage Validatie AI-Beoordeling</h1>
        <p class="text-muted">Gegenereerd op: <?= date('d-m-Y H:i') ?> op basis van toets <?= e($exam['title']) ?></p>
        <p>Dit rapport geeft een statistische vergelijking weer tussen de beoordeling van de docent en diverse AI-modellen. De analyses tonen de betrouwbaarheid, correlatie en eventuele afwijkingen van de modellen ten opzichte van de menselijke beoordelaar.</p>
    </div>

    <!-- Chart Section -->
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">
            Correlatie Visualisatie (Docent vs AI<?= $isLevels ? ', niveaus' : '' ?>)
        </div>
        <div class="card-body">
            <div class="chart-box">
                <canvas id="correlationChart"></canvas>
            </div>
            <p class="text-muted small mt-2 text-center">
                * Punten zijn licht verspreid (jitter) om overlap te voorkomen. De diagonale lijn geeft een perfecte overeenkomst aan.
            </p>
        </div>
    </div>

    <?php if ($isLevels): ?>
    <!-- Kruistabellen per model (niveaus) -->
    <?php foreach ($levelComparison['crosstabs'] as $source => $tab): ?>
    <div class="card mb-4 avoid-break">
        <div class="card-header bg-light fw-bold">
            Docentniveau tegen <?= e($source) ?> (<?= (int)$tab['n'] ?> antwoorden)
        </div>
        <div class="card-body">
            <p class="mb-2">
                Exact gelijk: <strong><?= $tab['exact_pct'] !== null ? e(number_format($tab['exact_pct'], 0)) . '%' : '-' ?></strong> ·
                Gelijk op voldoende/onvoldoende: <strong><?= $tab['pass_pct'] !== null ? e(number_format($tab['pass_pct'], 0)) . '%' : '-' ?></strong> ·
                Gemiddelde afwijking: <strong><?= $tab['mean_distance'] !== null ? e(number_format($tab['mean_distance'], 2, ',', '')) : '-' ?></strong> niveau
            </p>
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">Docent ↓ / AI →</th>
                            <?php foreach (Grading::LEVELS as $level): ?>
                                <th><?= e(Grading::levelLabel($level)) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (Grading::LEVELS as $t => $teacherLevel): ?>
                        <tr>
                            <th class="text-start table-primary"><?= e(Grading::levelLabel($teacherLevel)) ?></th>
                            <?php foreach (Grading::LEVELS as $a => $aiLevel): ?>
                                <?php $count = (int)$tab['matrix'][$t][$a]; ?>
                                <td class="<?= $t === $a ? 'table-success fw-bold' : ($count > 0 && (($t > 0) !== ($a > 0)) ? 'table-danger' : '') ?>"><?= $count ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mt-2 mb-0">Groen: exact gelijk. Rood: docent en AI verschillen op voldoende/onvoldoende.</p>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Cijfers per student (niveaus) -->
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">
            Cijfers per student (over de vergeleken antwoorden<?= $levelComparison['scheme'] ? ', puntenschema ' . e($levelComparison['scheme']['name']) : '' ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th class="text-center table-primary">Docent</th>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <th class="text-center"><?= e($model) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($levelComparison['student_grades'] as $student => $grades): ?>
                        <tr>
                            <td><?= e($student) ?></td>
                            <td class="text-center table-primary fw-bold"><?= isset($grades['Docent']) ? e(Grading::formatGrade($grades['Docent'])) : '-' ?></td>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <td class="text-center">
                                    <?php if (isset($grades[$model])): ?>
                                        <?= e(Grading::formatGrade($grades[$model])) ?>
                                        <?php if (isset($grades['Docent'])): ?>
                                            <?php $diff = $grades[$model] - $grades['Docent']; ?>
                                            <small class="<?= abs($diff) < 0.05 ? 'text-success' : ($diff > 0 ? 'text-danger' : 'text-warning') ?>">(<?= $diff > 0 ? '+' : '' ?><?= e(number_format($diff, 1, ',', '')) ?>)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detail Tabel (niveaus) -->
    <div class="card html2pdf__page-break">
        <div class="card-header bg-light fw-bold">
            Detailoverzicht per Vraag
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="col-w-15">Student</th>
                            <th class="col-w-35">Vraag</th>
                            <th class="text-center table-primary col-w-10">Docent</th>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <th class="text-center"><?= e($model) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparisonData as $row): ?>
                        <tr>
                            <td><?= e($row['student']) ?></td>
                            <td><small><?= e(mb_substr($row['question'], 0, 100, 'UTF-8')) ?>...</small></td>
                            <td class="text-center table-primary fw-bold"><?= e(Grading::levelLabel($row['teacher_level'])) ?></td>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <td class="text-center">
                                    <?php if (isset($row['models'][$model])): ?>
                                        <?php $same = $row['models'][$model] === $row['teacher_level']; ?>
                                        <span class="<?= $same ? 'text-success' : 'text-danger' ?>"><?= e(Grading::levelLabel($row['models'][$model])) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Statistieken Tabel -->
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">
            Statistische Analyse (Score 0-10)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Beoordelaar</th>
                            <th>Gemiddelde Score</th>
                            <th>Standaarddeviatie</th>
                            <th>Afwijking t.o.v. Docent (MAE)</th>
                            <th>Afwijking t.o.v. Docent (RMSE)</th>
                            <th>Correlatie met Docent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $name => $data): ?>
                        <tr <?= $name === 'Docent' ? 'class="table-primary"' : '' ?>>
                            <td class="fw-bold"><?= e($name) ?></td>
                            <td><?= isset($data['mean']) ? number_format($data['mean'], 2) : '-' ?></td>
                            <td><?= isset($data['std_dev']) ? number_format($data['std_dev'], 2) : '-' ?></td>
                            <td>
                                <?php if ($name !== 'Docent' && isset($data['mae'])): ?>
                                    <?= number_format($data['mae'], 2) ?>
                                    <small class="text-muted d-block text-xs">(lager is beter)</small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($name !== 'Docent' && isset($data['rmse'])): ?>
                                    <?= number_format($data['rmse'], 2) ?>
                                    <small class="text-muted d-block text-xs">(lager is beter)</small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($name !== 'Docent' && isset($data['correlation'])): ?>
                                    <?= number_format($data['correlation'], 2) ?>
                                    <small class="text-muted d-block text-xs">(dichter bij 1 is beter)</small>
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

    <!-- Eindscores per Student -->
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">
            Eindscores per Student (Gemiddelde)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th class="text-center table-primary">Docent</th>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <th class="text-center"><?= e($model) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentAverages as $student => $scores): ?>
                        <tr>
                            <td><?= e($student) ?></td>
                            <td class="text-center table-primary fw-bold">
                                <?= isset($scores['Docent']) ? number_format($scores['Docent'], 1) : '-' ?>
                            </td>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <td class="text-center">
                                    <?php 
                                    if (isset($scores[$model])) {
                                        echo number_format($scores[$model], 1);
                                        if (isset($scores['Docent'])) {
                                            $diff = $scores[$model] - $scores['Docent'];
                                            $color = abs($diff) < 0.1 ? 'text-success' : ($diff > 0 ? 'text-danger' : 'text-warning');
                                            echo " <small class='$color'>(" . ($diff > 0 ? '+' : '') . number_format($diff, 1) . ")</small>";
                                        }
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

    <!-- Detail Tabel -->
    <div class="card html2pdf__page-break">
        <div class="card-header bg-light fw-bold">
            Detailoverzicht per Vraag
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="col-w-15">Student</th>
                            <th class="col-w-35">Vraag</th>
                            <th class="text-center table-primary col-w-10">Docent</th>
                            <?php foreach (array_keys($modelsFound) as $model): ?>
                                <th class="text-center"><?= e($model) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparisonData as $row): ?>
                        <tr>
                            <td><?= e($row['student']) ?></td>
                            <td><small><?= e(substr($row['question'], 0, 100)) ?>...</small></td>
                            <td class="text-center table-primary fw-bold"><?= (int)$row['teacher_score'] ?></td>
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

    <!-- Toets beschrijving -->
    <div class="mb-4 html2pdf__page-break">
        <h4>Toets beschrijving</h4>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?= e($exam['title']) ?></h5>
                <p class="card-text"><?= nl2br(e($exam['description'])) ?></p>
                
                <?php if (isset($prompt) && $prompt): ?>
                    <hr>
                    <h6 class="card-subtitle mb-2 text-muted">Gebruikte AI Prompt: <?= e($prompt['title']) ?></h6>
                    <div class="p-3 bg-light border rounded font-monospace small text-pre-wrap"><?= e($prompt['prompt_text']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vragen en Criteria -->
    <div class="mb-4">
        <h4>Toetsvragen en Beoordelingscriteria</h4>
        <p class="text-muted small">Overzicht van de vragen in deze toets en de criteria waarop de AI is geïnstrueerd te beoordelen.</p>
        
        <?php foreach ($questions as $index => $q): ?>
        <div class="card mb-3 avoid-break">
            <div class="card-header bg-light">
                <strong>Vraag <?= $index + 1 ?></strong>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Vraagstelling:</strong><br><?= nl2br(e($q['question_text'])) ?></p>
                <div class="text-muted small mt-2"><strong>Criteria:</strong><br><?= nl2br(e($q['criteria'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    </div> <!-- Einde report-content -->

    <!-- Chart.js Script -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"
            integrity="sha384-Yv5O+t3uE3hunW8uyrbpPW3iw6/5/Y7HitWJBLgqfMoA36NogMmy+8wWZMpn3HWc" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
            integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
    <script nonce="<?= e(cspNonce()) ?>">
        document.addEventListener('DOMContentLoaded', function() {
            var pdfBtn = document.getElementById('exportPdfBtn');
            if (pdfBtn) { pdfBtn.addEventListener('click', generatePDF); }

            const comparisonData = <?= json_encode($chartRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const chartMax = <?= (int)$chartMax ?>;
            const chartLabels = <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const jitter = chartLabels ? 0.15 : 0.3;
            const tickLabel = function (value) {
                return chartLabels ? (chartLabels[value] !== undefined ? chartLabels[value] : '') : value;
            };
            const modelsFound = <?= json_encode(array_keys($modelsFound), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            
            // Kleuren uit de tokens in style.css (--chart-1 ... --chart-7, --chart-reference)
            const rootStyle = getComputedStyle(document.documentElement);
            const colors = [1, 2, 3, 4, 5, 6, 7].map(i => rootStyle.getPropertyValue('--chart-' + i).trim());
            const referenceColor = rootStyle.getPropertyValue('--chart-reference').trim();

            const datasets = modelsFound.map((model, index) => {
                const color = colors[index % colors.length];
                
                return {
                    label: model,
                    data: comparisonData.map(item => {
                        if (item.models[model] !== undefined) {
                            // Jitter toevoegen voor zichtbaarheid
                            const jitterX = (Math.random() - 0.5) * jitter;
                            const jitterY = (Math.random() - 0.5) * jitter;
                            return {
                                x: item.teacher_score + jitterX,
                                y: item.models[model] + jitterY,
                                originalX: item.teacher_score,
                                originalY: item.models[model]
                            };
                        }
                        return null;
                    }).filter(item => item !== null),
                    backgroundColor: color,
                    borderColor: color,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointStyle: 'circle'
                };
            });

            // Diagonale lijn (perfecte match)
            datasets.push({
                label: 'Perfecte match',
                data: [{x: 0, y: 0}, {x: chartMax, y: chartMax}],
                type: 'line',
                borderColor: referenceColor,
                borderDash: [5, 5],
                pointRadius: 0,
                fill: false,
                showLine: true,
                order: 999 // Zorg dat deze achteraan ligt
            });

            const ctx = document.getElementById('correlationChart').getContext('2d');
            new Chart(ctx, {
                type: 'scatter',
                data: { datasets: datasets },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'linear',
                            position: 'bottom',
                            title: {
                                display: true,
                                text: chartLabels ? 'Docentniveau' : 'Docent Score'
                            },
                            min: -0.5,
                            max: chartMax + 0.5,
                            ticks: {
                                stepSize: 1,
                                callback: tickLabel
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: chartLabels ? 'AI-niveau' : 'AI Score'
                            },
                            min: -0.5,
                            max: chartMax + 0.5,
                            ticks: {
                                stepSize: 1,
                                callback: tickLabel
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const raw = context.raw;
                                    // Gebruik de originele waarden zonder jitter voor de tooltip
                                    const x = raw.originalX !== undefined ? raw.originalX : Math.round(raw.x);
                                    const y = raw.originalY !== undefined ? raw.originalY : Math.round(raw.y);
                                    
                                    if (context.dataset.type === 'line') return null;
                                    
                                    return context.dataset.label + ': Docent ' + tickLabel(x) + ' vs AI ' + tickLabel(y);
                                }
                            }
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });

        function generatePDF() {
            const element = document.getElementById('report-content');

            const opt = {
                margin:       [10, 10, 10, 10], // top, left, bottom, right
                filename:     'Rapport_AI_Vergelijking_<?= preg_replace('/[^a-z0-9]/i', '_', $exam['title']) ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true }, 
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };

            html2pdf().set(opt).from(element).save();
        }
    </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = "Vergelijk AI en docent";
$breadcrumbs = [
    'Toetsen' => '/?action=docent_dashboard',
    $exam['title'] => '/?action=exam_results&exam_id=' . (int)$exam['id'],
    'Vergelijk AI en docent' => ''
];
require __DIR__ . '/../layouts/main.php';
?>