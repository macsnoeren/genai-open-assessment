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
    <div class="auth-card auth-card-wide">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 card-title text-center mb-4">Wachtwoord wijzigen</h1>
                
                <div class="alert alert-warning">
                    Je moet je wachtwoord wijzigen voordat je verder kunt gaan.
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= e($_SESSION['error']) ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php?action=do_change_password">
                    <?= csrfInput() ?>
                    <div class="mb-3">
                        <label class="form-label" for="new-password">Nieuw wachtwoord</label>
                        <input type="password" name="password" id="new-password" class="form-control" required autofocus minlength="<?= (int)PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
                        <div class="form-text">Minimaal <?= (int)PASSWORD_MIN_LENGTH ?> tekens, met minimaal één letter en één cijfer.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm-password">Bevestig wachtwoord</label>
                        <input type="password" name="confirm_password" id="confirm-password" class="form-control" required minlength="<?= (int)PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Wachtwoord opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Wachtwoord wijzigen";
$breadcrumbs = ['Wachtwoord wijzigen' => null];
require __DIR__ . '/../layouts/main.php';
?>