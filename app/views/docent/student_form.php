<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start(); ?>

<div class="row justify-content-center">
<div class="col-md-6">

<h2 class="mb-4"><?= htmlspecialchars($title) ?></h2>

<div class="card">
<div class="card-body">
<form action="/?action=<?= $action ?>" method="post">
    <?php if ($student): ?>
        <input type="hidden" name="id" value="<?= $student['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Naam</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($student['name'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Rol</label>
        <select name="role" class="form-select">
            <option value="student" <?= ($student['role'] ?? 'student') === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="docent" <?= ($student['role'] ?? 'student') === 'docent' ? 'selected' : '' ?>>Docent</option>
            <option value="beoordelaar" <?= ($student['role'] ?? 'student') === 'beoordelaar' ? 'selected' : '' ?>>Beoordelaar</option>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <option value="admin" <?= ($student['role'] ?? 'student') === 'admin' ? 'selected' : '' ?>>Admin</option>
            <?php endif; ?>
        </select>
    </div>

    <div class="mb-4">
        <label class="form-label">Wachtwoord <?= $student ? '<span class="text-muted fw-normal">(laat leeg om niet te wijzigen)</span>' : '' ?></label>
        <input type="password" name="password" class="form-control" <?= $student ? '' : 'required' ?>>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Opslaan</button>
    </div>
</form>
</div>
</div>

</div>
</div>

<?php 
$content = ob_get_clean();
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Gebruikers' => '/?action=students',
    $title => ''
];
require __DIR__ . '/../layouts/main.php'; 
?>