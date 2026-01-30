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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Docent Dashboard</h2>
    <a href="/?action=exam_create" class="btn btn-primary">
        Nieuwe toets
    </a>
</div>

<p class="lead">Welkom <?= htmlspecialchars($_SESSION['name']) ?></p>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Titel</th>
                  <th>Aangemaakt</th>
                  <th class="text-end">Acties</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($exams as $exam): ?>
                <tr>
                  <td class="align-middle"><?= htmlspecialchars($exam['title']) ?></td>
                  <td class="align-middle"><?= $exam['created_at'] ?></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="/?action=questions&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary">Vragen</a>
                        <a href="/?action=exam_results&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary">Resultaten</a>
                        <a href="/?action=exam_comparison&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary" title="Vergelijk AI met Docent">Vergelijk AI</a>
                        <a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary" onclick="return confirm('Weet je zeker dat je deze toets wilt testen?')" title="Testen">Testen</a>
                        <a href="/?action=exam_edit&id=<?= $exam['id'] ?>" class="btn btn-outline-primary">Bewerken</a>
                        <a href="/?action=exam_delete&id=<?= $exam['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Weet je zeker dat je deze toets wilt verwijderen?')">Verwijderen</a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Docent Dashboard";
require __DIR__ . '/../layouts/main.php';
?>
