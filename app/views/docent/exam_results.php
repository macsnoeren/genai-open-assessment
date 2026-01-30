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

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
  <thead class="table-light">
    <tr>
      <th>Student</th>
      <th>Toets ID</th>
      <th>Gestart op</th>
      <th>Ingeleverd op</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($studentExams as $se): ?>
    <tr>
      <td><?= htmlspecialchars($se['name']) ?></td>
      <td class="font-monospace"><?= htmlspecialchars($se['unique_id']) ?></td>
      <td><?= $se['started_at'] ?></td>
      <td><?= $se['completed_at'] ?? 'Nog niet ingeleverd' ?></td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
            <a href="/?action=view_student_answers&student_exam_id=<?= $se['student_exam_id'] ?>" class="btn btn-outline-secondary">Bekijken</a>
            <a href="/?action=grade_student_exam&student_exam_id=<?= $se['student_exam_id'] ?>" class="btn btn-outline-primary">Beoordelen (Blind)</a>
        </div>
    <a href="/?action=delete_student_exam&student_exam_id=<?= $se['student_exam_id'] ?>" 
       onclick="return confirm('Weet je zeker dat je dit resultaat wilt verwijderen? Alle antwoorden en feedback gaan verloren.')" class="btn btn-sm btn-outline-danger ms-1">Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<?php
 $content = ob_get_clean();
 $title = "Toets resultaten";
 $breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Resultaten' => ''
 ];
 require __DIR__ . '/../layouts/main.php';
?>
