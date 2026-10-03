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

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="mb-0">Resultaten toets</h2>
    <?php if (!empty($canEdit) && !empty($aiResettableCount)): ?>
    <form action="/?action=ai_results_reset_exam" method="post" class="mb-0">
        <?= csrfInput() ?>
        <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
        <button type="submit" class="btn btn-sm btn-outline-warning"
                data-confirm="De AI-resultaten van alle <?= (int)$aiResettableCount ?> ingeleverde pogingen van deze toets verwijderen en opnieuw laten uitvoeren? Pogingen via een externe koppeling worden overgeslagen. De docentbeoordelingen blijven staan.">AI-resultaten van alle pogingen opnieuw laten uitvoeren</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
  <thead class="table-light">
    <tr>
      <th>Student</th>
      <th>Toets ID</th>
      <th>Gestart op</th>
      <th>Ingeleverd op</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($studentExams as $se): ?>
    <tr>
      <td>
        <?= htmlspecialchars($se['name']) ?>
        <?php if (!empty($se['integration_name'])): ?>
        <span class="badge bg-info text-dark ms-1" title="Ref: <?= e($se['external_ref']) ?>">Koppeling: <?= e($se['integration_name']) ?></span>
        <?php endif; ?>
      </td>
      <td class="font-monospace"><?= htmlspecialchars($se['unique_id']) ?></td>
      <td><?= e($se['started_at']) ?></td>
      <td><?= e($se['completed_at'] ?? 'Nog niet ingeleverd') ?></td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
            <a href="/?action=view_student_answers&student_exam_id=<?= $se['student_exam_id'] ?>" class="btn btn-outline-secondary">Bekijken</a>
            <a href="/?action=grade_student_exam&student_exam_id=<?= $se['student_exam_id'] ?>" class="btn btn-outline-primary">Beoordelen (Blind)</a>
        </div>
    <?php if (!empty($canEdit)): ?>
    <a href="/?action=delete_student_exam&student_exam_id=<?= (int)$se['student_exam_id'] ?>" 
       data-confirm="Weet je zeker dat je dit resultaat wilt verwijderen? Alle antwoorden en feedback gaan verloren." class="btn btn-sm btn-outline-danger ms-1">Verwijderen</a>
    <?php endif; ?>
    <?php if (!empty($se['can_reset_ai'])): ?>
    <form action="/?action=ai_results_reset_attempt" method="post" class="d-inline">
        <?= csrfInput() ?>
        <input type="hidden" name="student_exam_id" value="<?= (int)$se['student_exam_id'] ?>">
        <input type="hidden" name="return" value="exam_results">
        <button type="submit" class="btn btn-sm btn-outline-warning ms-1"
                data-confirm="Alle AI-resultaten van <?= e($se['name']) ?> bij deze toets verwijderen en opnieuw laten uitvoeren? De docentbeoordeling blijft staan.">AI opnieuw</button>
    </form>
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
 $title = "Toets resultaten";
 $breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Resultaten' => ''
 ];
 require __DIR__ . '/../layouts/main.php';
?>
