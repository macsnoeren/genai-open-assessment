<?php
ob_start();
?>

<h2>Studenten beheren</h2>

<a href="/?action=student_create" class="table-btn">➕ Nieuwe student</a>

<table>
  <thead>
    <tr>
      <th>Naam</th>
      <th>Email</th>
      <th>Aangemaakt</th>
      <th>Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($students as $s): ?>
    <tr>
      <td><?= htmlspecialchars($s['name']) ?></td>
      <td><?= htmlspecialchars($s['email']) ?></td>
      <td><?= $s['created_at'] ?></td>
      <td>
	<a href="/?action=student_edit&id=<?= $s['id'] ?>">✏ Bewerken</a> |
	<a href="/?action=student_delete&id=<?= $s['id'] ?>" onclick="return confirm('Weet je het zeker?')">🗑 Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
 $content = ob_get_clean();
 $title = "Studenten beheren";
 require __DIR__ . '/../layouts/main.php';
?>
