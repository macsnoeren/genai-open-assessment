<?php ob_start(); ?>

<h2><?= htmlspecialchars($title) ?></h2>

<form action="/?action=<?= $action ?>" method="post" style="max-width: 800px;">
    <input type="hidden" name="exam_id" value="<?= $examId ?>">
    <?php if ($question): ?>
        <input type="hidden" name="id" value="<?= $question['id'] ?>">
    <?php endif; ?>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Vraag:</label>
        <textarea name="question_text" rows="3" required style="width: 100%; padding: 8px; box-sizing: border-box;"><?= htmlspecialchars($question['question_text'] ?? '') ?></textarea>
    </div>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Modelantwoord (optioneel, voor referentie):</label>
        <textarea name="model_answer" rows="3" style="width: 100%; padding: 8px; box-sizing: border-box;"><?= htmlspecialchars($question['model_answer'] ?? '') ?></textarea>
    </div>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Beoordelingscriteria (voor AI):</label>
        <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;">
            Beschrijf waaraan het antwoord moet voldoen voor 0, 1, 5 of 10 punten.
        </div>
        <textarea name="criteria" rows="6" required style="width: 100%; padding: 8px; box-sizing: border-box; font-family: monospace;"><?= htmlspecialchars($question['criteria'] ?? '') ?></textarea>
    </div>

    <div style="margin-top: 20px;">
        <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px;">Opslaan</button>
        <a href="/?action=questions&exam_id=<?= $examId ?>" style="margin-left: 15px; color: #666; text-decoration: none;">Annuleren</a>
    </div>
</form>

<?php 
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php'; 
?>