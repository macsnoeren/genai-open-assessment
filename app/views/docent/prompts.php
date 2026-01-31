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

<h2>Prompts beheren</h2>
<p>Beheer hier de systeem-prompts die gebruikt worden door de AI modellen.</p>

<div class="mb-3">
    <a href="/?action=prompt_create" class="btn btn-primary">Nieuwe prompt</a>
    <a href="/?action=prompt_help" class="btn btn-outline-info ms-2">Hulp bij prompts</a>
</div>

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
  <thead class="table-light">
    <tr>
      <th>Titel</th>
      <th>Beschrijving</th>
      <th>Laatst gewijzigd</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($prompts as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['title']) ?></td>
      <td><?= htmlspecialchars($p['description']) ?></td>
      <td><?= $p['updated_at'] ?></td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
            <a href="/?action=prompt_edit&id=<?= $p['id'] ?>" class="btn btn-outline-primary">Bewerken</a>
            <a href="/?action=prompt_delete&id=<?= $p['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Weet je het zeker?')">Verwijderen</a>
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
$title = "Prompts beheren";
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Prompts' => ''
];
require __DIR__ . '/../layouts/main.php';
?>