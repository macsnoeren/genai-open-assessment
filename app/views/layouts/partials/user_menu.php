<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Gebruikersmenu rechts in de topbalk: initialen, naam en rol, met Profiel en Uitloggen.
$userName = (string)($_SESSION['name'] ?? '');
$userRole = ucfirst((string)($_SESSION['role'] ?? ''));
?>
<div class="dropdown">
    <button class="btn user-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="Gebruikersmenu: <?= e($userName) ?>">
        <span class="avatar" aria-hidden="true"><?= e(userInitials($userName)) ?></span>
        <span class="user-menu-text d-none d-md-block" aria-hidden="true">
            <span class="user-menu-name"><?= e($userName) ?></span>
            <span class="user-menu-role"><?= e($userRole) ?></span>
        </span>
        <i class="bi bi-chevron-down user-menu-chevron" aria-hidden="true"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php if (empty($_SESSION['force_password_change'])): ?>
        <li><a class="dropdown-item" href="/?action=student_edit&amp;id=<?= (int)$_SESSION['user_id'] ?>"><i class="bi bi-person me-2" aria-hidden="true"></i>Profiel</a></li>
        <li><hr class="dropdown-divider"></li>
        <?php endif; ?>
        <li><a class="dropdown-item" href="/?action=logout"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Uitloggen</a></li>
    </ul>
</div>
