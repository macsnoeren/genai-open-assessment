<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Zijbalk voor docent, admin en beoordelaar. Onder de lg-breedte een offcanvas
// (Bootstrap offcanvas-lg), te openen met de hamburger in de topbalk.
?>
<aside class="offcanvas-lg offcanvas-start app-sidebar" id="appSidebar" tabindex="-1" aria-label="Hoofdmenu">
    <div class="app-sidebar-brand">
        <a href="/" class="app-sidebar-logo">
            <img src="/images/logo-h.png" alt="Logo" height="36">
            <span><?= e(APP_NAME) ?></span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                data-bs-target="#appSidebar" aria-label="Menu sluiten"></button>
    </div>

    <nav class="app-sidebar-nav">
        <?php foreach (navItemsForRole($_SESSION['role'] ?? '') as $group => $items): ?>
        <?php if ($group !== ''): ?>
        <div class="app-nav-group"><?= e($group) ?></div>
        <?php endif; ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($items as $item): ?>
            <?php $isActive = navIsActive($item, $currentAction); ?>
            <li>
                <a class="app-nav-link<?= $isActive ? ' active' : '' ?>" href="/?action=<?= e($item['action']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <span><?= e($item['label']) ?></span>
                    <?php $count = !empty($item['counter']) ? navCounter($item['counter']) : 0; ?>
                    <?php if ($count > 0): ?>
                    <span class="nav-counter"><?= e($count > 99 ? '99+' : (string)$count) ?></span>
                    <span class="visually-hidden">pogingen te beoordelen</span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endforeach; ?>
    </nav>

    <div class="app-sidebar-footer">
        &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; <?= e(CONTACT_NAME) ?><br>
        <a href="/?action=privacy">Privacy &amp; Cookies</a>
    </div>
</aside>
