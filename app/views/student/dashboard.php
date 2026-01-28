<?php
ob_start();
?>

<h2 style="color: var(--primary-color);">Welkom <?= htmlspecialchars($_SESSION['name']) ?></h2>

<div class="dashboard-section">
  <h3>Beschikbare examens</h3>
  <?php foreach ($exams as $exam): ?>
  <div class="card">
    <strong><?= htmlspecialchars($exam['title']) ?></strong><br>
    <a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" class="table-btn">📝 Start examen</a>
  </div>
  <?php endforeach; ?>
</div>

<div class="dashboard-section">
  <h3>Mijn examens</h3>
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
</div>

<?php
 $content = ob_get_clean();
 $title = "Student Dashboard";
 require __DIR__ . '/../layouts/main.php';
 ?>
