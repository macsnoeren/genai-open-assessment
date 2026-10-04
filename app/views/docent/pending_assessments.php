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

<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Beoordelen</h1>
        <p class="text-muted mb-0">Ingeleverde toetsen die nog niet volledig zijn beoordeeld.</p>
    </div>
</div>

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
    <thead class="table-light">
        <tr>
            <th>Student</th>
            <th>Toets</th>
            <th>Ingeleverd op</th>
            <th>Voortgang</th>
            <th class="text-end">Actie</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pendingExams)): ?>
            <tr><td colspan="5">Geen openstaande beoordelingen. U bent helemaal bij!</td></tr>
        <?php else: ?>
            <?php foreach ($pendingExams as $exam): ?>
            <tr>
                <td><?= e($exam['student_name']) ?></td>
                <td><?= e($exam['exam_title']) ?></td>
                <td><?= e($exam['completed_at']) ?></td>
                <td><?= (int)$exam['graded_answers'] ?> / <?= (int)$exam['total_answers'] ?> beoordeeld</td>
                <td class="text-end">
                    <a href="/?action=grade_student_exam&student_exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-primary">Beoordelen</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
</div>

<?php
$content = ob_get_clean();
$title = "Beoordelen";
$breadcrumbs = ['Beoordelen' => null];
require __DIR__ . '/../layouts/main.php';
?>
