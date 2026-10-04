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

<div class="auth-wrapper">
    <div class="card auth-card">
        <div class="card-body p-4">
            <img src="/images/logo.png" alt="<?= e(APP_NAME) ?>" class="auth-logo">
            <h1 class="h4 card-title text-center mb-3">Toets starten</h1>
            <p class="text-center text-muted mb-4">
                Je staat op het punt om de toets <strong><?= e($exam['title']) ?></strong> te starten.
                Vul je voornaam en de eerste letter van je achternaam in om te beginnen.
            </p>

            <form method="POST" action="index.php?action=guest_start">
                <?= csrfInput() ?>
                <input type="hidden" name="token" value="<?= e(requestString($_GET, 'token', 128)) ?>">

                <div class="mb-3">
                    <label class="form-label" for="guest-name">Voornaam én eerste letter achternaam</label>
                    <input type="text" name="name" id="guest-name" class="form-control" required autofocus placeholder="Bijv. Jan J">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Start toets</button>
                </div>
            </form>
        </div>
    </div>
    <p class="auth-back"><a href="/"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Terug naar de startpagina</a></p>
</div>

<?php
$content = ob_get_clean();
$title = "Toets Starten";
$hideHeaderFooter = true;
require __DIR__ . '/../layouts/main.php';
?>