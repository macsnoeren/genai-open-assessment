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
<div class="col-md-8">

<h2 class="mb-4"><?= htmlspecialchars($title) ?></h2>

<div class="card">
<div class="card-body">
<form action="/?action=<?= $action ?>" method="post">
    <?php if ($exam): ?>
        <input type="hidden" name="id" value="<?= $exam['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Titel</label>
        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($exam['title'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Omschrijving</label>
        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($exam['description'] ?? '') ?></textarea>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Opslaan</button>
        <a href="/?action=docent_dashboard" class="btn btn-outline-secondary">Annuleren</a>
    </div>
</form>
</div>
</div>

</div>
</div>

<?php 
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php'; 
?>