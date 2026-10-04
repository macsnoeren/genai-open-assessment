<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start(); ?>

<?php if (!isset($studentExam)): ?>
    <div class="page-header">
        <div>
            <h1 class="h3 mb-1">Mijn resultaten</h1>
            <p class="text-muted mb-0">Een overzicht van al je gemaakte toetsen.</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Toets</th>
                        <th>Ingeleverd op</th>
                        <th class="text-end">Actie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allStudentExams)): ?>
                        <tr><td colspan="3" class="text-center p-4">Geen resultaten gevonden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($allStudentExams as $se): ?>
                        <tr>
                            <td><?= e($se['exam_title'] ?? 'Toets') ?></td>
                            <td><?= e($se['completed_at']) ?></td>
                            <td class="text-end">
                                <a href="/?action=student_view_results&student_exam_id=<?= (int)$se['id'] ?>" class="btn btn-sm btn-outline-primary">Bekijken</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>

<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Resultaten: <?= e($exam['title']) ?></h1>
        <p class="text-muted mb-0">
            <?= e($studentExam['guest_name'] ?? $_SESSION['name'] ?? 'Onbekend') ?>
            &middot; ingeleverd op <?= e($studentExam['completed_at']) ?>
        </p>
    </div>
</div>

<p><?= e($exam['description']) ?></p>

<?php $isLevels = ($gradingScale ?? Grading::SCALE_POINTS) === Grading::SCALE_LEVELS; ?>
<?php if ($isLevels): ?>
    <?php if ($finalLabel !== null): ?>
    <div class="alert alert-primary">
        <strong>Eindbeoordeling van je docent:</strong> <?= e(Grading::levelLabel($finalLabel)) ?>
    </div>
    <?php elseif ($finalGrade !== null): ?>
    <div class="alert alert-primary">
        <strong>Eindcijfer van je docent:</strong> <?= e(Grading::formatGrade((float)$finalGrade)) ?>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary">
        Je docent heeft nog niet alle antwoorden beoordeeld. Je eindcijfer verschijnt zodra dat klaar is.
    </div>
    <?php endif; ?>
<?php elseif (isset($finalGrade) && $finalGrade !== null): ?>
<div class="alert alert-primary">
    <strong>Eindscore van je docent:</strong> <?= number_format((float)$finalGrade, 1) ?>
</div>
<?php endif; ?>

<?php if ($isLevels && !empty($aiGrades)): ?>
<div class="alert alert-info">
    <strong>AI-cijfers:</strong> <small>automatisch door AI, ter informatie; je cijfer komt van je docent.</small>
    <ul class="mb-0 mt-1">
    <?php foreach ($aiGrades as $source => $aiGrade): ?>
        <li><strong><?= e($source) ?>:</strong> <?= e(Grading::formatGrade($aiGrade['grade'])) ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($finalAiScores)): ?>
<div class="alert alert-info">
    <strong>AI-scores (gemiddelde):</strong> <small>automatisch door AI, ter informatie; je cijfer komt van je docent.</small>
    <ul class="mb-0 mt-1">
    <?php foreach ($finalAiScores as $model => $score): ?>
        <li><strong><?= e($model) ?>:</strong> <?= number_format($score, 1) ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php foreach ($questions as $q): ?>
    <?php $a = $answers[$q['id']] ?? null; ?>
    <div class="card">
      <div class="card-body">
        <p><strong>Vraag:</strong> <?= e($q['question_text']) ?></p>

        <p><strong>Jouw antwoord:</strong><br>
        <?= $a ? nl2br(e($a['answer'])) : '<em>Geen antwoord gegeven</em>' ?>
        </p>

        <hr>

        <?php if ($a): ?>
            <?php if (isset($a['teacher_score']) || !empty($a['teacher_level']) || !empty($a['teacher_feedback'])): ?>
            <div class="feedback-block feedback-teacher">
                <strong>Beoordeling door je docent:</strong><br>
                <?php if ($isLevels && !empty($a['teacher_level'])): ?>
                    Niveau: <strong><?= e(Grading::levelLabel($a['teacher_level'])) ?></strong><br>
                <?php elseif (!$isLevels && isset($a['teacher_score'])): ?>
                    Score: <strong><?= e($a['teacher_score']) ?></strong><br>
                <?php endif; ?>
                <?php if (!empty($a['teacher_feedback'])): ?>
                    Feedback: <?= nl2br(e($a['teacher_feedback'])) ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <div class="feedback-empty">
                    Docent heeft nog geen feedback gegeven.
                </div>
            <?php endif; ?>
            
            <hr>

            <?php $agentic = $agenticResults[(int)$a['id']] ?? null; ?>
            <?php if ($agentic): ?>
                <div class="feedback-block feedback-ai">
                    <strong class="feedback-ai-title">AI-beoordeling (agentic):</strong>
                    <small class="text-muted">automatisch door AI, niet door je docent</small><br>
                    <?php if (isset($agentic['level'])): ?>
                    Niveau: <strong><?= e(Grading::levelLabel($agentic['level'])) ?></strong><br>
                    <?php else: ?>
                    Score: <strong><?= (int)$agentic['score'] ?></strong><br>
                    <?php endif; ?>
                    <?php if ($agentic['feedback'] !== ''): ?>
                        Feedback: <?= nl2br(e($agentic['feedback'])) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($a['ai_feedback']): ?>
                <div class="feedback-block feedback-ai">
                    <strong class="feedback-ai-title">AI-feedback:</strong>
                    <small class="text-muted">automatisch door AI, niet door je docent</small><br>
                    <div class="feedback-raw">
<?= e($a['ai_feedback']) ?>
                    </div>
                </div>
            <?php elseif (!$agentic): ?>
                <div class="feedback-empty">
                    Nog geen feedback beschikbaar. Dit proces loopt op de achtergrond.
                </div>
            <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
<?php endforeach; ?>

<?php endif; ?>

<div class="mt-4">
    <?php if (empty($isGuest)): ?>
        <a href="/?action=my_exams" class="btn btn-secondary">Terug naar overzicht</a>
    <?php elseif (isset($studentExam)): ?>
        <a href="/?action=student_view_results" class="btn btn-secondary">Terug naar mijn overzicht</a>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = isset($exam) ? "Resultaten - " . $exam['title'] : "Mijn Resultaten Overzicht"; // wordt in de layout ge-escaped
$breadcrumbs = [];
if (empty($isGuest)) {
    // Een docent die een eigen testpoging bekijkt, komt uit "Mijn testpogingen".
    $isStudent = ($_SESSION['role'] ?? '') === 'student';
    $breadcrumbs = $isStudent
        ? ['Dashboard' => '/?action=student_dashboard', 'Mijn toetsen' => '/?action=my_exams']
        : ['Mijn testpogingen' => '/?action=my_exams'];
    $breadcrumbs['Resultaten'] = '';
}
require __DIR__ . '/../layouts/main.php';
?>