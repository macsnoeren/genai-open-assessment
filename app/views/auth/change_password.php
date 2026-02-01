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
                <h3 class="card-title text-center mb-4">Wachtwoord Wijzigen</h3>
                
                <div class="alert alert-warning">
                    Je moet je wachtwoord wijzigen voordat je verder kunt gaan.
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php?action=do_change_password">
                    <div class="mb-3">
                        <label class="form-label">Nieuw Wachtwoord</label>
                        <input type="password" name="password" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bevestig Wachtwoord</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Wachtwoord Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Wachtwoord Wijzigen";
require __DIR__ . '/../layouts/main.php';
?>