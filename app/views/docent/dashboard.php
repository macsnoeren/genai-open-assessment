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
        <h1 class="h3 mb-1">Toetsen</h1>
        <p class="text-muted mb-0">Welkom, <?= e($_SESSION['name']) ?></p>
    </div>
    <a href="/?action=exam_create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nieuwe toets
    </a>
</div>

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
                  <th>Gastlink</th>
                  <th class="text-end">Acties</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($exams as $exam): ?>
                <?php $isOwner = ($_SESSION['role'] === 'admin' || (int)$exam['docent_id'] === (int)$_SESSION['user_id']); ?>
                <tr>
                  <td class="align-middle">
                      <?= e($exam['title']) ?>
                      <?php if ($exam['ai_grading_enabled']): ?>
                          <span class="badge badge-soft-primary ms-2" title="AI beoordeling actief">AI aan</span>
                      <?php else: ?>
                          <span class="badge badge-soft-secondary ms-2" title="AI beoordeling inactief">AI uit</span>
                      <?php endif; ?>
                      <?php if ($exam['shared']): ?>
                          <span class="badge badge-soft-info ms-1" title="Gedeeld met andere docenten">Gedeeld</span>
                      <?php endif; ?>
                      <?php if (!empty($exam['published'])): ?>
                          <span class="badge badge-soft-success ms-1" title="Zichtbaar voor ingelogde studenten">Gepubliceerd</span>
                      <?php endif; ?>
                      <?php if ($exam['docent_id'] != $_SESSION['user_id']): ?>
                          <span class="badge badge-soft-warning ms-1" title="Gemaakt door een andere docent">Van collega</span>
                      <?php endif; ?>
                  </td>
                  <td class="align-middle text-nowrap"><?= e($exam['created_at']) ?></td>
                  <td class="align-middle">
                    <?php if (!empty($exam['public_token'])): ?>
                        <?php $link = appBaseUrl() . "/?action=guest&token=" . $exam['public_token']; ?>
                        <div class="input-group input-group-sm guest-link-group">
                            <input type="text" class="form-control" value="<?= e($link) ?>" readonly id="link-<?= (int)$exam['id'] ?>" aria-label="Gastlink">
                            <button class="btn btn-outline-secondary" type="button" data-copy-target="link-<?= (int)$exam['id'] ?>"
                                    aria-label="Kopieer gastlink" title="Kopieer gastlink"><i class="bi bi-copy" aria-hidden="true"></i></button>
                            <?php if ($isOwner): ?>
                            <a href="/?action=exam_public_link&amp;mode=renew&amp;id=<?= (int)$exam['id'] ?>" class="btn btn-outline-secondary"
                               aria-label="Nieuwe gastlink" title="Nieuwe gastlink"
                               data-confirm="Nieuwe gastlink maken? De huidige link werkt dan direct niet meer; lopende gastpogingen blijven werken."><i class="bi bi-arrow-repeat" aria-hidden="true"></i></a>
                            <a href="/?action=exam_public_link&amp;mode=disable&amp;id=<?= (int)$exam['id'] ?>" class="btn btn-outline-secondary"
                               aria-label="Gastlink uitzetten" title="Gastlink uitzetten"
                               data-confirm="Gastlink uitzetten? Niemand kan dan nog als gast starten; lopende gastpogingen blijven werken."><i class="bi bi-slash-circle" aria-hidden="true"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($isOwner): ?>
                        <a href="/?action=exam_public_link&amp;mode=renew&amp;id=<?= (int)$exam['id'] ?>" class="btn btn-sm btn-outline-secondary text-nowrap"
                           data-confirm="Een nieuwe gastlink maken?">Gastlink aanzetten</a>
                    <?php else: ?>
                        <span class="small text-muted">Uit</span>
                    <?php endif; ?>
                  </td>
                  <td class="align-middle text-end text-nowrap">
                    <a href="/?action=questions&amp;exam_id=<?= (int)$exam['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-list-check me-1" aria-hidden="true"></i>Vragen
                    </a>
                    <a href="/?action=exam_results&amp;exam_id=<?= (int)$exam['id'] ?>" class="btn btn-sm btn-outline-secondary"
                       aria-label="Resultaten bekijken" title="Resultaten bekijken"><i class="bi bi-bar-chart" aria-hidden="true"></i></a>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                data-bs-popper-config='{"strategy":"fixed"}'
                                aria-label="Meer acties" title="Meer acties"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/?action=exam_comparison&amp;exam_id=<?= (int)$exam['id'] ?>"><i class="bi bi-graph-up me-2" aria-hidden="true"></i>Vergelijk AI en docent</a></li>
                            <li><a class="dropdown-item" href="/?action=start_exam&amp;exam_id=<?= (int)$exam['id'] ?>" data-confirm="Weet je zeker dat je deze toets wilt testen?"><i class="bi bi-play me-2" aria-hidden="true"></i>Testen</a></li>
                            <?php if ($isOwner): ?>
                            <li><a class="dropdown-item" href="/?action=exam_duplicate&amp;id=<?= (int)$exam['id'] ?>" data-confirm="Weet je zeker dat je deze toets wilt dupliceren inclusief alle antwoorden? De AI-feedback wordt gewist, docent-feedback blijft behouden."><i class="bi bi-files me-2" aria-hidden="true"></i>Dupliceren</a></li>
                            <li><a class="dropdown-item" href="/?action=exam_edit&amp;id=<?= (int)$exam['id'] ?>"><i class="bi bi-pencil me-2" aria-hidden="true"></i>Bewerken</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/?action=exam_delete&amp;id=<?= (int)$exam['id'] ?>" data-confirm="Weet je zeker dat je deze toets wilt verwijderen?"><i class="bi bi-trash me-2" aria-hidden="true"></i>Verwijderen</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($exams)): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">
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
$title = "Toetsen";
$breadcrumbs = ['Toetsen' => null];
require __DIR__ . '/../layouts/main.php';
?>
