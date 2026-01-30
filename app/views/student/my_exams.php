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
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <thead>
            <tr style="text-align: left;">
                <th style="padding: 10px; border-bottom: 2px solid #ddd;">Toets</th>
                <th style="padding: 10px; border-bottom: 2px solid #ddd;">Gestart op</th>
                <th style="padding: 10px; border-bottom: 2px solid #ddd;">Status</th>
                <th style="padding: 10px; border-bottom: 2px solid #ddd;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($studentExams as $se): ?>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($se['title']) ?></td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= $se['started_at'] ?></td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                        <?= $se['completed_at'] ? 'Ingeleverd' : 'Bezig' ?>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                        <?php if ($se['completed_at']): ?>
                            <a href="/?action=student_view_results&student_exam_id=<?= $se['id'] ?>">Bekijk resultaten</a>
                        <?php else: ?>
                            <a href="/?action=take_exam&student_exam_id=<?= $se['id'] ?>">Verder gaan</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php 
$content = ob_get_clean();
$title = "Mijn Toetsen";
if (file_exists(__DIR__ . '/../layouts/main.php')) {
    require __DIR__ . '/../layouts/main.php';
} else {
    echo $content;
}
?>