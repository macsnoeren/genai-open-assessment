<?php
ob_start();
?>

<h2>Mijn gemaakte examens</h2>

<table>
  <thead>
    <tr>
      <th>Examen ID</th>
      <th>Titel</th>
      <th>Gestart op</th>
      <th>Ingeleverd op</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($studentExams as $se): ?>
    <tr>
      <td><?= htmlspecialchars($se['unique_id']) ?></td>
      <td><?= htmlspecialchars($se['title']) ?></td>
      <td><?= $se['started_at'] ?></td>
      <td><?= $se['completed_at'] ?? 'Nog niet ingeleverd' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
 $content = ob_get_clean();
 $title = "Mijn examens";
 require __DIR__ . '/../layouts/main.php';
?>
