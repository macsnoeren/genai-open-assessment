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
        <h1 class="h3 mb-1">Resultaten</h1>
        <p class="text-muted mb-0"><?= e($exam['title']) ?></p>
    </div>
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
      <th>Eindcijfer</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($studentExams as $se): ?>
    <tr>
      <td>
        <?= e($se['name']) ?>
        <?php if (!empty($se['integration_name'])): ?>
        <span class="badge badge-soft-info ms-1" title="Ref: <?= e($se['external_ref']) ?>">Koppeling: <?= e($se['integration_name']) ?></span>
        <?php endif; ?>
      </td>
      <td class="font-monospace"><?= e($se['unique_id']) ?></td>
      <td><?= e($se['started_at']) ?></td>
      <td><?= e($se['completed_at'] ?? 'Nog niet ingeleverd') ?></td>
      <td class="text-nowrap">
        <?php $result = $se['result']; ?>
        <?php if ($result === null): ?>
          <span class="text-muted">-</span>
        <?php elseif ($result['final'] === null): ?>
          <span class="text-muted small"><?= (int)$result['graded'] ?> van <?= (int)$result['total'] ?> beoordeeld</span>
        <?php else: ?>
          <?php if (!is_string($result['final'])): ?>
            <strong><?= e(Grading::formatGrade((float)$result['final'])) ?></strong>
          <?php endif; ?>
          <?php if ($result['label'] !== null): ?>
            <span class="badge badge-soft-<?= e(Grading::levelClass($result['label'])) ?>"><?= e(Grading::levelLabel($result['label'])) ?></span>
          <?php endif; ?>
          <?php if ($result['override'] !== null): ?>
            <span class="badge badge-soft-secondary" title="Handmatig aangepast: <?= e($result['override_reason']) ?>">aangepast</span>
            <?php if ($result['override_outdated']): ?>
              <span class="badge badge-soft-warning" title="Het berekende cijfer is gewijzigd sinds de aanpassing">!</span>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </td>
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
