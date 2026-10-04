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

<div class="auth-wrapper auth-wrapper-inline">
    <div class="card auth-card">
        <div class="card-body p-4">
            <img src="/images/logo.png" alt="<?= e(APP_NAME) ?>" class="auth-logo">
            <h1 class="h4 card-title text-center mb-3">Account aanmaken</h1>
            <p class="text-muted text-center mb-4">Maak een studentaccount aan. Daarna kun je inloggen.</p>

            <form method="POST" action="/?action=do_register">
                <?= csrfInput() ?>
                <div class="mb-3">
                    <label class="form-label" for="register-name">Naam</label>
                    <input type="text" name="name" id="register-name" class="form-control" required maxlength="<?= (int)MAX_NAME_LENGTH ?>" autocomplete="name">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="register-email">E-mail</label>
                    <input type="email" name="email" id="register-email" class="form-control" required autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="register-password">Wachtwoord</label>
                    <input type="password" name="password" id="register-password" class="form-control" required minlength="<?= (int)PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
                    <div class="form-text">Minimaal <?= (int)PASSWORD_MIN_LENGTH ?> tekens, met minimaal één letter en één cijfer.</div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Account aanmaken</button>
                </div>
            </form>
        </div>
    </div>
    <p class="auth-back"><a href="/?action=login">Heb je al een account? Inloggen</a></p>
</div>

<?php
$content = ob_get_clean();
$title = "Account aanmaken";
$breadcrumbs = [
    'Startpagina' => '/',
    'Account aanmaken' => null,
];
require __DIR__ . '/../layouts/main.php';
