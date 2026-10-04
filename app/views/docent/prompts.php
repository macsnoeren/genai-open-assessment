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
        <h1 class="h3 mb-1">Prompts</h1>
        <p class="text-muted mb-0">De systeemprompts die de AI-modellen gebruiken.</p>
    </div>
    <div class="page-header-actions">
        <a href="/?action=prompt_help" class="btn btn-outline-secondary"><i class="bi bi-question-circle me-1" aria-hidden="true"></i>Hulp bij prompts</a>
        <a href="/?action=prompt_create" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nieuwe prompt</a>
    </div>
</div>

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
  <thead class="table-light">
    <tr>
      <th>Titel</th>
      <th>Beschrijving</th>
      <th>Schaal</th>
      <th>Laatst gewijzigd</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($prompts as $p): ?>
    <tr>
      <td><?= e($p['title']) ?></td>
      <td><?= e($p['description']) ?></td>
      <td><?= ($p['grading_scale'] ?? 'points') === 'levels' ? 'Niveaus' : 'Punten' ?></td>
      <td><?= e($p['updated_at']) ?></td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
            <a href="/?action=prompt_edit&id=<?= $p['id'] ?>" class="btn btn-outline-primary">Bewerken</a>
            <a href="/?action=prompt_delete&id=<?= $p['id'] ?>" class="btn btn-outline-danger" data-confirm="Weet je het zeker?">Verwijderen</a>
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