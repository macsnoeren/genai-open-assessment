<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Topbalk voor docent, admin en beoordelaar: hamburger en breadcrumbs links,
// de status van de AI-worker (alleen docent en admin) en het gebruikersmenu rechts.
$showWorkerStatus = in_array($_SESSION['role'] ?? '', ['docent', 'admin'], true);
?>
<header class="app-topbar">
    <div class="app-topbar-start">
        <button class="btn app-topbar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                aria-controls="appSidebar" aria-label="Menu openen" title="Menu openen">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <?php $breadcrumbsCompact = true; require __DIR__ . '/breadcrumbs.php'; ?>
    </div>
    <div class="app-topbar-end">
        <?php if ($showWorkerStatus): ?>
        <?php $workerActive = workerStatus() === 'active'; ?>
        <?php $workerText = $workerActive ? 'AI-worker actief' : 'AI-worker reageert niet'; ?>
        <span class="worker-status" title="<?= e($workerText) ?>">
            <span class="status-dot <?= $workerActive ? 'status-dot-active' : 'status-dot-inactive' ?>" aria-hidden="true"></span>
            <span class="d-none d-xl-inline"><?= e($workerText) ?></span>
            <span class="visually-hidden d-xl-none"><?= e($workerText) ?></span>
        </span>
        <?php endif; ?>
        <?php require __DIR__ . '/user_menu.php'; ?>
    </div>
</header>
