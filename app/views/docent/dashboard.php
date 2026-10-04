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

<?php
$ownerOptions = ['all' => 'Alle toetsen', 'mine' => 'Mijn toetsen', 'colleagues' => 'Van collega\'s'];
$statusOptions = [
    'all' => 'Elke status',
    'published' => 'Gepubliceerd',
    'unpublished' => 'Niet gepubliceerd',
    'ai_on' => 'AI aan',
    'ai_off' => 'AI uit',
    'shared' => 'Gedeeld',
];
?>
<form method="get" action="/" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="action" value="docent_dashboard">
    <input type="hidden" name="filter" value="1">
    <div class="col-md-5">
        <label for="filter-q" class="form-label small text-muted mb-1">Zoek op naam</label>
        <input type="search" class="form-control" id="filter-q" name="q" maxlength="100"
               value="<?= e($filter['q']) ?>" placeholder="Bijvoorbeeld: Biologie">
    </div>
    <div class="col-6 col-md-2">
        <label for="filter-owner" class="form-label small text-muted mb-1">Eigenaar</label>
        <select class="form-select" id="filter-owner" name="owner">
            <?php foreach ($ownerOptions as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $filter['owner'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label for="filter-status" class="form-label small text-muted mb-1">Status</label>
        <select class="form-select" id="filter-status" name="status">
            <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $filter['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filteren</button>
        <?php if ($filterActive): ?>
        <a href="/?action=docent_dashboard&amp;reset_filter=1" class="btn btn-outline-secondary">Wis filter</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($filterActive): ?>
<p class="small text-muted mb-2"><?= count($exams) ?> van <?= (int)$totalExams ?> toetsen zichtbaar door het filter.</p>
<?php endif; ?>

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
                  <td class="align-middle">
                      <?= htmlspecialchars($exam['title']) ?>
                      <?php if ($exam['ai_grading_enabled']): ?>
                          <span class="badge bg-success ms-2" title="AI beoordeling actief">AI Aan</span>
                      <?php else: ?>
                          <span class="badge bg-secondary ms-2" title="AI beoordeling inactief">AI Uit</span>
                      <?php endif; ?>
                      <?php if ($exam['shared']): ?>
                          <span class="badge bg-info text-dark ms-1" title="Gedeeld met andere docenten">Gedeeld</span>
                      <?php endif; ?>
                      <?php if (!empty($exam['published'])): ?>
                          <span class="badge bg-primary ms-1" title="Zichtbaar voor ingelogde studenten">Gepubliceerd</span>
                      <?php endif; ?>
                      <?php if ($exam['docent_id'] != $_SESSION['user_id']): ?>
                          <span class="badge bg-warning text-dark ms-1" title="Gemaakt door een andere docent">Van collega</span>
                      <?php endif; ?>
                  </td>
                  <td class="align-middle"><?= e($exam['created_at']) ?></td>
                  <td class="text-end">
                    <?php if (!empty($exam['public_token'])): ?>
                        <?php 
                            $link = appBaseUrl() . "/?action=guest&token=" . $exam['public_token'];
                            $isOwner = ($_SESSION['role'] === 'admin' || (int)$exam['docent_id'] === (int)$_SESSION['user_id']);
                        ?>
                        <div class="input-group input-group-sm mb-2" style="max-width: 300px; margin-left: auto;">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($link) ?>" readonly id="link-<?= $exam['id'] ?>">
                            <button class="btn btn-outline-secondary" type="button" data-copy-target="link-<?= (int)$exam['id'] ?>" title="Kopieer link">📋</button>
                            <?php if ($isOwner): ?>
                            <a href="/?action=exam_public_link&mode=renew&id=<?= (int)$exam['id'] ?>" class="btn btn-outline-secondary" title="Nieuwe gastlink"
                               data-confirm="Nieuwe gastlink maken? De huidige link werkt dan direct niet meer; lopende gastpogingen blijven werken.">🔄</a>
                            <a href="/?action=exam_public_link&mode=disable&id=<?= (int)$exam['id'] ?>" class="btn btn-outline-secondary" title="Gastlink uitzetten"
                               data-confirm="Gastlink uitzetten? Niemand kan dan nog als gast starten; lopende gastpogingen blijven werken.">⛔</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php $isOwner = ($_SESSION['role'] === 'admin' || (int)$exam['docent_id'] === (int)$_SESSION['user_id']); ?>
                        <?php if ($isOwner): ?>
                        <div class="mb-2">
                            <span class="small text-muted me-1">Gastlink uit</span>
                            <a href="/?action=exam_public_link&mode=renew&id=<?= (int)$exam['id'] ?>" class="btn btn-sm btn-outline-secondary"
                               data-confirm="Een nieuwe gastlink maken?">Gastlink aanzetten</a>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="btn-group btn-group-sm">
                        <a href="/?action=questions&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary" title="Vragen beheren">📝</a>
                        <a href="/?action=exam_results&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary" title="Resultaten bekijken">📊</a>
                        <a href="/?action=exam_comparison&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary" title="Vergelijk AI met Docent">📈</a>
                        <a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary" data-confirm="Weet je zeker dat je deze toets wilt testen?" title="Testen">▶️</a>
                        <?php if ($isOwner): ?>
                        <a href="/?action=exam_duplicate&id=<?= $exam['id'] ?>" class="btn btn-outline-warning" data-confirm="Weet je zeker dat je deze toets wilt dupliceren inclusief alle antwoorden? De AI-feedback wordt gewist, docent-feedback blijft behouden." title="Dupliceren">📋</a>
                        <a href="/?action=exam_edit&id=<?= $exam['id'] ?>" class="btn btn-outline-primary" title="Bewerken">✏️</a>
                        <a href="/?action=exam_delete&id=<?= $exam['id'] ?>" class="btn btn-outline-danger" data-confirm="Weet je zeker dat je deze toets wilt verwijderen?" title="Verwijderen">🗑️</a>
                        <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($exams)): ?>
                <tr>
                  <td colspan="3" class="text-center text-muted py-4">
                      <?= $filterActive ? 'Geen toetsen gevonden met dit filter.' : 'Je hebt nog geen toetsen.' ?>
                  </td>
                </tr>
                <?php endif; ?>
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
