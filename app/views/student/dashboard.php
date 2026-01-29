<?php ob_start(); ?>

<h2>Student Dashboard</h2>

<div style="margin-bottom: 30px;">
    <h3>Beschikbare examens</h3>
    <?php if (empty($exams)): ?>
        <p>Er zijn momenteel geen examens beschikbaar.</p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($exams as $exam): ?>
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #fff;">
                    <h4 style="margin-top: 0;"><?= htmlspecialchars($exam['title']) ?></h4>
                    <p><?= htmlspecialchars($exam['description']) ?></p>
                    <a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" style="display: inline-block; padding: 8px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Start examen</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div>
    <h3>Mijn gemaakte examens</h3>
    <?php if (empty($studentExams)): ?>
        <p>Je hebt nog geen examens gemaakt.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #f8f9fa; text-align: left;">
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Examen</th>
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