<?php ob_start(); ?>

<a href="/?action=my_exams">← Terug naar mijn toetsen</a>

<h2>Resultaten: <?= htmlspecialchars($exam['title']) ?></h2>
<p><?= htmlspecialchars($exam['description']) ?></p>

<?php foreach ($questions as $q): ?>
    <?php $a = $answers[$q['id']] ?? null; ?>
    <div class="card">
        <p><strong>Vraag:</strong> <?= htmlspecialchars($q['question_text']) ?></p>
        
        <p><strong>Jouw antwoord:</strong><br>
        <?= $a ? nl2br(htmlspecialchars($a['answer'])) : '<em>Geen antwoord gegeven</em>' ?>
        </p>

        <?php if ($a): ?>
            <?php if (isset($a['teacher_score']) || !empty($a['teacher_feedback'])): ?>
            <div style="margin-top: 10px; padding: 10px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
                <strong>👨‍🏫 Docent beoordeling:</strong><br>
                <?php if (isset($a['teacher_score'])): ?>
                    Score: <strong><?= htmlspecialchars($a['teacher_score']) ?></strong><br>
                <?php endif; ?>
                <?php if (!empty($a['teacher_feedback'])): ?>
                    Feedback: <?= nl2br(htmlspecialchars($a['teacher_feedback'])) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($a['ai_feedback'])): ?>
            <p><strong>AI feedback:</strong><br>
                <?= nl2br(htmlspecialchars($a['ai_feedback'])) ?>
            </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
$title = "Resultaten - " . $exam['title'];
require __DIR__ . '/../layouts/main.php';
?>