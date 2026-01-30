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

<h2><?= htmlspecialchars($title) ?></h2>

<form action="/?action=<?= $action ?>" method="post" style="max-width: 600px;">
    <?php if ($student): ?>
        <input type="hidden" name="id" value="<?= $student['id'] ?>">
    <?php endif; ?>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Naam:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($student['name'] ?? '') ?>" required style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Rol:</label>
        <select name="role" style="width: 100%; padding: 8px; box-sizing: border-box;">
            <option value="student" <?= ($student['role'] ?? 'student') === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="docent" <?= ($student['role'] ?? 'student') === 'docent' ? 'selected' : '' ?>>Docent</option>
            <option value="beoordelaar" <?= ($student['role'] ?? 'student') === 'beoordelaar' ? 'selected' : '' ?>>Beoordelaar</option>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <option value="admin" <?= ($student['role'] ?? 'student') === 'admin' ? 'selected' : '' ?>>Admin</option>
            <?php endif; ?>
        </select>
    </div>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Wachtwoord <?= $student ? '(laat leeg om niet te wijzigen)' : '' ?>:</label>
        <input type="password" name="password" <?= $student ? '' : 'required' ?> style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>

    <div style="margin-top: 20px;">
        <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px;">Opslaan</button>
        <a href="/?action=students" style="margin-left: 15px; color: #666; text-decoration: none;">Annuleren</a>
    </div>
</form>

<?php 
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php'; 
?>