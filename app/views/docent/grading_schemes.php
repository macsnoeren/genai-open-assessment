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

<h2>Puntenschema's</h2>
<p>Een puntenschema zet de niveaus van een toets met <em>Beoordelen met niveaus</em> om in punten: een aantal punten voor onvoldoende (meestal 0), voldoende, goed en uitstekend.
Het eindcijfer is 10 × de som van de punten / (aantal vragen × de punten voor uitstekend). Je kunt elk schema kiezen bij een toets;
alleen de maker kan een eigen schema wijzigen of verwijderen.</p>

<div class="mb-3">
    <a href="/?action=create_grading_scheme" class="btn btn-primary">Nieuw puntenschema</a>
</div>

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0 align-middle">
  <thead class="table-light">
    <tr>
      <th>Naam</th>
      <th class="text-center" title="Onvoldoende / Voldoende / Goed / Uitstekend">Punten (O/V/G/U)</th>
      <th>Eigenaar</th>
      <th class="text-center">Toetsen</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($schemes as $scheme): ?>
    <tr>
      <td><?= e($scheme['name']) ?></td>
      <td class="text-center font-monospace"><?= e(str_replace('/', ' / ', GradingScheme::pointsLabel($scheme))) ?></td>
      <td>
        <?php if ($scheme['owner_id'] === null): ?>
          <span class="badge bg-light text-dark border">Systeem</span>
        <?php else: ?>
          <?= e($scheme['owner_name'] ?? 'Verwijderde gebruiker') ?>
        <?php endif; ?>
      </td>
      <td class="text-center"><?= (int)$scheme['exam_count'] ?></td>
      <td class="text-end">
        <?php if (GradingScheme::canManage($scheme)): ?>
        <div class="btn-group btn-group-sm">
            <a href="/?action=edit_grading_scheme&id=<?= (int)$scheme['id'] ?>" class="btn btn-outline-primary">Wijzigen</a>
            <a href="/?action=delete_grading_scheme&id=<?= (int)$scheme['id'] ?>" class="btn btn-outline-danger"
               data-confirm="Weet je zeker dat je het puntenschema &quot;<?= e($scheme['name']) ?>&quot; wilt verwijderen?">Verwijderen</a>
        </div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<?php
$content = ob_get_clean();
$title = "Puntenschema's";
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    "Puntenschema's" => ''
];
require __DIR__ . '/../layouts/main.php';
?>
