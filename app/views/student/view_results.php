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

<h2>Resultaten: <?= htmlspecialchars($exam['title']) ?></h2>
<p><?= htmlspecialchars($exam['description']) ?></p>

<?php if (isset($finalScore) && $finalScore !== null): ?>
<div class="alert alert-primary">
    <strong>Eindscore (Gemiddelde):</strong> <?= number_format($finalScore, 1) ?>
</div>
<?php endif; ?>

<?php if (!empty($finalAiScores)): ?>
<div class="alert alert-info">
    <strong>AI Model Scores (Gemiddelde):</strong>
    <ul class="mb-0 mt-1">
    <?php foreach ($finalAiScores as $model => $score): ?>
        <li><strong><?= htmlspecialchars($model) ?>:</strong> <?= number_format($score, 1) ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php foreach ($questions as $q): ?>
    <?php $a = $answers[$q['id']] ?? null; ?>
    <div class="card">
        <p><strong>Vraag:</strong> <?= htmlspecialchars($q['question_text']) ?></p>
        
        <p><strong>Jouw antwoord:</strong><br>
        <?= $a ? nl2br(htmlspecialchars($a['answer'])) : '<em>Geen antwoord gegeven</em>' ?>
        </p>

        <hr>

        <?php if ($a): ?>
            <?php if (isset($a['teacher_score']) || !empty($a['teacher_feedback'])): ?>
            <div style="margin-top: 10px; padding: 10px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
                <strong>Docent beoordeling:</strong><br>
                <?php if (isset($a['teacher_score'])): ?>
                    Score: <strong><?= htmlspecialchars($a['teacher_score']) ?></strong><br>
                <?php endif; ?>
                <?php if (!empty($a['teacher_feedback'])): ?>
                    Feedback: <?= nl2br(htmlspecialchars($a['teacher_feedback'])) ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <div style="color: #666; font-style: italic; padding: 10px; background: #f5f5f5; border-radius: 4px;">
                    Docent heeft nog geen feedback gegeven.
                </div>
            <?php endif; ?>
            
            <hr>

            <?php if ($a['ai_feedback']): ?>
                <div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3;">
                    <strong style="color: #1565c0;">AI Feedback:</strong><br>
                    <div style="margin-top: 5px; white-space: pre-wrap; font-family: monospace, sans-serif; font-size: 0.95em;">
<?= htmlspecialchars($a['ai_feedback']) ?>
                    </div>
                </div>
            <?php else: ?>
                <div style="color: #666; font-style: italic; padding: 10px; background: #f5f5f5; border-radius: 4px;">
                    Nog geen feedback beschikbaar. Dit proces loopt op de achtergrond.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if (empty($isGuest)): ?>
<div class="mt-4">
    <a href="/?action=my_exams" class="btn btn-secondary">Terug naar overzicht</a>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = "Resultaten - " . $exam['title'];
$breadcrumbs = [];
if (empty($isGuest)) {
    $breadcrumbs = [
        'Dashboard' => '/?action=student_dashboard',
        'Mijn toetsen' => '/?action=my_exams',
        'Resultaten' => ''
    ];
}
require __DIR__ . '/../layouts/main.php';
?>