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

<div class="row justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <h3 class="card-title text-center mb-4">Toets Starten</h3>
                <p class="text-center text-muted mb-4">
                    Je staat op het punt om de toets <strong><?= htmlspecialchars($exam['title']) ?></strong> te starten.
                    Vul je naam in om te beginnen.
                </p>

                <form method="POST" action="index.php?action=guest_start">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Volledige Naam</label>
                        <input type="text" name="name" class="form-control" required autofocus placeholder="Bijv. Jan Jansen">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Start Toets</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Toets Starten";
require __DIR__ . '/../layouts/main.php';
?>