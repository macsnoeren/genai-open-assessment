<?php
 ob_start();
 ?>

<h2>Resultaten examen</h2>

<table>
  <thead>
    <tr>
      <th>Student</th>
      <th>Examen ID</th>
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
	<a href="/?action=view_student_answers&student_exam_id=<?= $se['student_exam_id'] ?>">📝 Bekijken</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
 $content = ob_get_clean();
 $title = "Examen resultaten";
 require __DIR__ . '/../layouts/main.php';
?>
