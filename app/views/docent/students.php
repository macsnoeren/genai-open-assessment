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

<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Gebruikers</h1>
        <p class="text-muted mb-0">Studenten, docenten, beoordelaars en beheerders.</p>
    </div>
    <a href="/?action=student_create" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nieuwe gebruiker</a>
</div>

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
  <thead class="table-light">
    <tr>
      <th>Naam</th>
      <th>Email</th>
      <th>Rol</th>
      <th>Aangemaakt</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($students as $s): ?>
    <tr>
      <td><?= e($s['name']) ?></td>
      <td><?= e($s['email']) ?></td>
      <td>
          <span class="badge <?= $s['role'] == 'admin' ? 'badge-soft-danger' : ($s['role'] == 'docent' ? 'badge-soft-primary' : 'badge-soft-secondary') ?>">
            <?= e($s['role']) ?>
          </span>
      </td>
      <td><?= e($s['created_at']) ?></td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
            <a href="/?action=student_edit&id=<?= $s['id'] ?>" class="btn btn-outline-primary">Bewerken</a>
            <a href="/?action=student_delete&id=<?= $s['id'] ?>" class="btn btn-outline-danger" data-confirm="Weet je het zeker?">Verwijderen</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<?php
 $content = ob_get_clean();
 $title = "Studenten beheren";
 $breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Gebruikers' => ''
 ];
 require __DIR__ . '/../layouts/main.php';
?>
