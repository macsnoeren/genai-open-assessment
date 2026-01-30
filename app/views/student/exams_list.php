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

<h2>Beschikbare toetsen</h2>

<table>
  <thead>
    <tr>
      <th>Titel</th>
      <th>Actie</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($exams as $exam): ?>
    <tr>
      <td><?= htmlspecialchars($exam['title']) ?></td>
      <td>
	<a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" class="table-btn">Start toets</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
 $content = ob_get_clean();
 $title = "Beschikbare toetsen";
 require __DIR__ . '/../layouts/main.php';
?>
