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

<h2>Gebruikers beheren</h2>

<a href="/?action=student_create" class="table-btn">Nieuwe gebruiker</a>

<table>
  <thead>
    <tr>
      <th>Naam</th>
      <th>Email</th>
      <th>Rol</th>
      <th>Aangemaakt</th>
      <th>Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($students as $s): ?>
    <tr>
      <td><?= htmlspecialchars($s['name']) ?></td>
      <td><?= htmlspecialchars($s['email']) ?></td>
      <td>
          <span style="padding: 2px 6px; border-radius: 4px; background: <?= $s['role'] == 'admin' ? '#d63384' : ($s['role'] == 'docent' ? '#0d6efd' : '#6c757d') ?>; color: white; font-size: 0.8em;">
            <?= htmlspecialchars($s['role']) ?>
          </span>
      </td>
      <td><?= $s['created_at'] ?></td>
      <td>
	<a href="/?action=student_edit&id=<?= $s['id'] ?>">Bewerken</a> |
	<a href="/?action=student_delete&id=<?= $s['id'] ?>" onclick="return confirm('Weet je het zeker?')">Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
 $content = ob_get_clean();
 $title = "Studenten beheren";
 $breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Gebruikers' => ''
 ];
 require __DIR__ . '/../layouts/main.php';
?>
