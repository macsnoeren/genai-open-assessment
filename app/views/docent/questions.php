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
    <h2 class="mb-0">Vragen: <?= htmlspecialchars($exam['title']) ?></h2>
    <div>
        <a href="index.php?action=docent_dashboard" class="btn btn-outline-secondary me-2">Terug</a>
        <a href="index.php?action=question_create&exam_id=<?= $exam['id'] ?>" class="btn btn-primary">Nieuwe vraag</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 30%">Vraag</th>
              <th style="width: 30%">Modelantwoord</th>
              <th style="width: 25%">Criteria</th>
              <th style="width: 15%" class="text-end">Acties</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($questions as $q): ?>
            <tr>
              <td><?= htmlspecialchars($q['question_text']) ?></td>
              <td><small class="text-muted"><?= htmlspecialchars($q['model_answer']) ?></small></td>
              <td><small class="text-muted"><?= htmlspecialchars($q['criteria']) ?></small></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <a href="index.php?action=question_edit&id=<?= $q['id'] ?>" class="btn btn-outline-primary">Bewerken</a>
                    <a href="index.php?action=question_delete&id=<?= $q['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Weet je het zeker?')">Verwijderen</a>
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
$title = "Vragen beheren";
require __DIR__ . '/../layouts/main.php';
?>
