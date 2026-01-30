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

<h2 class="mb-4">Student Dashboard</h2>

<div class="mb-5">
    <h3 class="h4 mb-3 border-bottom pb-2">Beschikbare toetsen</h3>
    <?php if (empty($exams)): ?>
        <div class="alert alert-info">Er zijn momenteel geen toetsen beschikbaar.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($exams as $exam): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($exam['title']) ?></h5>
                            <p class="card-text text-muted flex-grow-1"><?= htmlspecialchars($exam['description']) ?></p>
                            <a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" class="btn btn-primary mt-3">Start toets</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div>
    <h3 class="h4 mb-3 border-bottom pb-2">Mijn gemaakte toetsen</h3>
    <?php if (empty($studentExams)): ?>
        <p class="text-muted">Je hebt nog geen toetsen gemaakt.</p>
    <?php else: ?>
        <div class="card">
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Toets</th>
                    <th>Gestart op</th>
                    <th>Status</th>
                    <th>Acties</th>
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
                        <td>
                            <?php if ($se['completed_at']): ?>
                                <a href="/?action=student_view_results&student_exam_id=<?= $se['id'] ?>" class="btn btn-sm btn-outline-secondary">Resultaten</a>
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
</div>

<?php 
$content = ob_get_clean();
$title = "Student Dashboard";
if (file_exists(__DIR__ . '/../layouts/main.php')) {
    require __DIR__ . '/../layouts/main.php';
} else {
    echo $content;
}
?>