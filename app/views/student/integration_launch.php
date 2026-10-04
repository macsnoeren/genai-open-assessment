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
    <div class="card auth-card auth-card-wide">
        <div class="card-body p-4">
            <img src="/images/logo.png" alt="<?= e(APP_NAME) ?>" class="auth-logo auth-logo-small">
            <h1 class="h4 card-title text-center mb-3"><?= e($attempt['exam_title']) ?></h1>
            <p class="text-center text-muted mb-4">
                Je bent hier via <strong><?= e($attempt['integration_name']) ?></strong>.
                Welkom, <strong><?= e($attempt['guest_name']) ?></strong>.
                Deze toets heeft <?= (int)$questionCount ?> <?= $questionCount === 1 ? 'open vraag' : 'open vragen' ?>.
            </p>
            <ul class="small text-muted mb-4">
                <li>Je kunt tussentijds opslaan. Na <em>Definitief inleveren</em> kun je niets meer wijzigen.</li>
                <li>Je antwoorden worden automatisch nagekeken door AI; soms kijkt een docent nog mee.</li>
                <li>Na het inleveren ga je automatisch terug naar <?= e($attempt['integration_name']) ?>.</li>
            </ul>

            <form method="POST" action="/?action=integration_launch_start">
                <?= csrfInput() ?>
                <input type="hidden" name="token" value="<?= e($launchToken) ?>">
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Start de toets</button>
                </div>
            </form>
            <p class="small text-muted text-center mt-3 mb-0">
                Deze link werkt één keer. <a href="/?action=privacy" class="text-muted">Privacy</a>
            </p>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Toets starten';
$hideHeaderFooter = true;
require __DIR__ . '/../layouts/main.php';
