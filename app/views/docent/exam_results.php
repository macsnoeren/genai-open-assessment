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

<h2>Resultaten toets</h2>

<table>
  <thead>
    <tr>
      <th>Student</th>
      <th>Toets ID</th>
      <th>Gestart op</th>
      <th>Ingeleverd op</th>
      <th>Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($studentExams as $se): ?>
    <tr>
      <td><?= htmlspecialchars($se['name']) ?></td>
      <td><?= htmlspecialchars($se['unique_id']) ?></td>
      <td><?= $se['started_at'] ?></td>
      <td><?= $se['completed_at'] ?? 'Nog niet ingeleverd' ?></td>
      <td>
	<a href="/?action=view_student_answers&student_exam_id=<?= $se['student_exam_id'] ?>">📝 Bekijken</a> |
    <a href="/?action=grade_student_exam&student_exam_id=<?= $se['student_exam_id'] ?>">⚖️ Beoordelen (Blind)</a> |
    <a href="/?action=delete_student_exam&student_exam_id=<?= $se['student_exam_id'] ?>" 
       onclick="return confirm('Weet je zeker dat je dit resultaat wilt verwijderen? Alle antwoorden en feedback gaan verloren.')" style="color: red;">🗑 Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
 $content = ob_get_clean();
 $title = "Toets resultaten";
 require __DIR__ . '/../layouts/main.php';
?>
