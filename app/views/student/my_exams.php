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

<h2>Mijn Toetsen</h2>

<?php if (empty($studentExams)): ?>
    <p>Je hebt nog geen toetsen gemaakt.</p>
<?php else: ?>
    <div class="card">
    <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Toets</th>
                <th>Gestart op</th>
                <th>Status</th>
                <th class="text-end">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($studentExams as $se): ?>
                <tr>
                    <td><?= htmlspecialchars($se['title']) ?></td>
                    <td><?= $se['started_at'] ?></td>
                    <td>
                        <?php if($se['completed_at']): ?>
                            <span class="badge bg-success">Ingeleverd</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Bezig</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($se['completed_at']): ?>
                            <a href="/?action=student_view_results&student_exam_id=<?= $se['id'] ?>" class="btn btn-sm btn-outline-secondary">Bekijk resultaten</a>
                        <?php else: ?>
                            <a href="/?action=take_exam&student_exam_id=<?= $se['id'] ?>" class="btn btn-sm btn-primary">Verder gaan</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
<?php endif; ?>

<?php 
$content = ob_get_clean();
$title = "Mijn Toetsen";
$breadcrumbs = [
    'Dashboard' => '/?action=student_dashboard',
    'Mijn Toetsen' => ''
];
if (file_exists(__DIR__ . '/../layouts/main.php')) {
    require __DIR__ . '/../layouts/main.php';
} else {
    echo $content;
}
?>