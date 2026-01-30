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

<h2>Nieuwe vraag toevoegen</h2>

<form method="POST" action="index.php?action=question_store">
    <input type="hidden" name="exam_id" value="<?= $_GET['exam_id'] ?>">

    <label>Vraag</label><br>
        <textarea name="question_text" required></textarea><br><br>

    <label>Modelantwoord</label><br>
        <textarea name="model_answer"></textarea><br><br>

    <label>Beoordelingscriteria</label><br>
        <textarea name="criteria"></textarea><br><br>

    <button type="submit">Opslaan</button>
    </form>

<a href="index.php?action=questions&exam_id=<?= $_GET['exam_id'] ?>">← Terug</a>

<?php
$content = ob_get_clean();
$title = "Nieuwe vraag";
require __DIR__ . '/../layouts/main.php';
?>