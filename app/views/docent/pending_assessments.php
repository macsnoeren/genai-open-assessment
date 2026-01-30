<?php ob_start(); ?>

<h2>Openstaande beoordelingen</h2>
<p>Hieronder staan de toetsen die zijn ingeleverd maar nog niet volledig zijn beoordeeld.</p>

<table>
    <thead>
        <tr>
            <th>Student</th>
            <th>Toets</th>
            <th>Ingeleverd op</th>
            <th>Voortgang</th>
            <th>Actie</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pendingExams)): ?>
            <tr><td colspan="5">Geen openstaande beoordelingen. U bent helemaal bij! 🎉</td></tr>
        <?php else: ?>
            <?php foreach ($pendingExams as $exam): ?>
            <tr>
                <td><?= htmlspecialchars($exam['student_name']) ?></td>
                <td><?= htmlspecialchars($exam['exam_title']) ?></td>
                <td><?= htmlspecialchars($exam['completed_at']) ?></td>
                <td><?= $exam['graded_answers'] ?> / <?= $exam['total_answers'] ?> beoordeeld</td>
                <td>
                    <a href="/?action=grade_student_exam&student_exam_id=<?= $exam['id'] ?>">⚖️ Beoordelen</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
$title = "Docent beoordelingen";
require __DIR__ . '/../layouts/main.php';
?>
