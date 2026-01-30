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
    <?php if ($exam): ?>
        <input type="hidden" name="id" value="<?= $exam['id'] ?>">
    <?php endif; ?>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Titel:</label>
        <input type="text" name="title" value="<?= htmlspecialchars($exam['title'] ?? '') ?>" required style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>

    <div style="margin-bottom: 15px;">
        <label style="display:block; margin-bottom: 5px;">Omschrijving:</label>
        <textarea name="description" rows="4" style="width: 100%; padding: 8px; box-sizing: border-box;"><?= htmlspecialchars($exam['description'] ?? '') ?></textarea>
    </div>

    <div style="margin-top: 20px;">
        <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px;">Opslaan</button>
        <a href="/?action=docent_dashboard" style="margin-left: 15px; color: #666; text-decoration: none;">Annuleren</a>
    </div>
</form>

<?php 
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php'; 
?>