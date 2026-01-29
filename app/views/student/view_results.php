<?php
ob_start();
?>

<div style="margin-bottom: 20px;">
    <a href="/?action=my_exams" style="text-decoration: none;">&larr; Terug naar mijn examens</a>
</div>

<h2>Resultaten: <?= htmlspecialchars($exam['title']) ?></h2>
<p><?= htmlspecialchars($exam['description']) ?></p>

<div class="exam-results">
    <?php foreach ($questions as $index => $question): ?>
        <?php 
            $answerData = $answers[$question['id']] ?? null;
            $studentAnswer = $answerData['answer'] ?? '';
            $aiFeedback = $answerData['ai_feedback'] ?? '';
        ?>
        <div class="card" style="background: #fff; margin-bottom: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0;">Vraag <?= $index + 1 ?></h3>
            <div style="font-size: 1.1em; margin-bottom: 15px; color: #333;">
                <?= nl2br(htmlspecialchars($question['question_text'])) ?>
            </div>

            <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #ccc; margin-bottom: 15px;">
                <strong>Jouw antwoord:</strong><br>
                <div style="margin-top: 5px;">
                    <?= $studentAnswer ? nl2br(htmlspecialchars($studentAnswer)) : '<em style="color: #999;">Geen antwoord gegeven</em>' ?>
                </div>
            </div>

            <?php if ($aiFeedback): ?>
                <div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3;">
                    <strong style="color: #1565c0;">AI Feedback:</strong><br>
                    <div style="margin-top: 5px; white-space: pre-wrap; font-family: monospace, sans-serif; font-size: 0.95em;">
<?= htmlspecialchars($aiFeedback) ?>
                    </div>
                </div>
            <?php else: ?>
                <div style="color: #666; font-style: italic; padding: 10px; background: #f5f5f5; border-radius: 4px;">
                    Nog geen feedback beschikbaar. Dit proces loopt op de achtergrond.
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
$title = "Resultaten: " . htmlspecialchars($exam['title']);

if (file_exists(__DIR__ . '/../layouts/main.php')) {
    require __DIR__ . '/../layouts/main.php';
} else {
    echo $content;
}
?>