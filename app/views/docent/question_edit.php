<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start();
?>

<h2>Vraag bewerken</h2>

<form method="POST" action="index.php?action=question_update">
    <input type="hidden" name="id" value="<?= $question['id'] ?>">
        <input type="hidden" name="exam_id" value="<?= $question['exam_id'] ?>">

    <label>Vraag</label><br>
        <textarea name="question_text" required><?= htmlspecialchars($question['question_text']) ?></textarea><br><br>

    <label>Modelantwoord</label><br>
        <textarea name="model_answer"><?= htmlspecialchars($question['model_answer']) ?></textarea><br><br>

    <label>Beoordelingscriteria</label><br>
        <textarea name="criteria"><?= htmlspecialchars($question['criteria']) ?></textarea><br><br>

    <button type="submit">Opslaan</button>
    </form>

<a href="index.php?action=questions&exam_id=<?= $question['exam_id'] ?>">← Terug</a>

<?php
$content = ob_get_clean();
$title = "Vraag bewerken";
require __DIR__ . '/../layouts/main.php';
?>