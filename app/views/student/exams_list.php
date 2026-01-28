<?php
ob_start();
?>

<h2>Beschikbare examens</h2>

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
	<a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" class="table-btn">📝 Start examen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
 $content = ob_get_clean();
 $title = "Beschikbare examens";
 require __DIR__ . '/../layouts/main.php';
?>
