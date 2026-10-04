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
            <h1 class="h4 card-title text-center mb-4">Inloggen</h1>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= e($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php?action=do_login">
                <?= csrfInput() ?>
                <div class="mb-3">
                    <label class="form-label" for="login-email">E-mail</label>
                    <input type="email" name="email" id="login-email" class="form-control" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="login-password">Wachtwoord</label>
                    <input type="password" name="password" id="login-password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Inloggen</button>
                </div>
            </form>
        </div>
    </div>
    <p class="auth-back"><a href="/"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Terug naar de startpagina</a></p>
</div>

<?php
$content = ob_get_clean();
$title = "Login";
$hideHeaderFooter = true;
require __DIR__ . '/../layouts/main.php';
?>