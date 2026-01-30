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

<h2>Vragen voor examen: <?= htmlspecialchars($exam['title']) ?></h2>

<a href="index.php?action=docent_dashboard">← Terug naar examens</a> |
<a href="index.php?action=question_create&exam_id=<?= $exam['id'] ?>">➕ Nieuwe vraag</a><hr>

<table>
  <thead>
    <tr>
      <th>Vraag</th>
      <th>Modelantwoord</th>
      <th>Criteria</th>
      <th>Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($questions as $q): ?>
    <tr>
      <td><?= htmlspecialchars($q['question_text']) ?></td>
      <td><?= htmlspecialchars($q['model_answer']) ?></td>
      <td><?= htmlspecialchars($q['criteria']) ?></td>
      <td>
	<a href="index.php?action=question_edit&id=<?= $q['id'] ?>">✏ Bewerken</a> |
	<a href="index.php?action=question_delete&id=<?= $q['id'] ?>"
	   onclick="return confirm('Weet je het zeker?')">🗑 Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
$content = ob_get_clean();
$title = "Vragen beheren";
require __DIR__ . '/../layouts/main.php';
?>
