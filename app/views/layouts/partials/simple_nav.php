<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Eenvoudige witte topnavigatie voor studenten, uitgelogde bezoekers en wie
// eerst het wachtwoord moet wijzigen (dan zonder menu-items). Geen workerstatus.
$isLoggedIn = !empty($_SESSION['user_id']);
$simpleItems = [];
if ($isLoggedIn && empty($_SESSION['force_password_change'])) {
    $simpleItems = navItemsForRole('student')[''] ?? [];
    if (($_SESSION['role'] ?? '') !== 'student') {
        $simpleItems = [];
    }
}
?>
<nav class="navbar navbar-expand-md app-topbar app-topbar-simple">
    <div class="container">
        <a class="navbar-brand" href="/">
            <img src="/images/logo-h.png" alt="Logo" height="36">
            <span class="visually-hidden"><?= e(APP_NAME) ?></span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto order-md-last">
            <?php if ($isLoggedIn): ?>
                <?php require __DIR__ . '/user_menu.php'; ?>
            <?php elseif (empty($isGuest)): ?>
                <a href="/?action=login" class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Inloggen</a>
            <?php endif; ?>
            <?php if ($simpleItems): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#simpleNav"
                    aria-controls="simpleNav" aria-expanded="false" aria-label="Menu openen">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
        </div>
        <?php if ($simpleItems): ?>
        <div class="collapse navbar-collapse" id="simpleNav">
            <ul class="navbar-nav mx-md-auto">
                <?php foreach ($simpleItems as $item): ?>
                <?php $isActive = navIsActive($item, $currentAction); ?>
                <li class="nav-item">
                    <a class="nav-link<?= $isActive ? ' active' : '' ?>" href="/?action=<?= e($item['action']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                        <i class="bi <?= e($item['icon']) ?> me-1" aria-hidden="true"></i><?= e($item['label']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</nav>
