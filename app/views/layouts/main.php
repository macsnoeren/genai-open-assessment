<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Security headers worden centraal gezet in htdocs/index.php (sendSecurityHeaders()).

$parserStatus = 'inactive';
$pingFile = __DIR__ . '/../../../database/last_api_ping.txt';

// Check if the file exists, is readable, and contains a recent timestamp
if (file_exists($pingFile) && is_readable($pingFile)) {
    $lastPing = file_get_contents($pingFile);
    if ($lastPing !== false && is_numeric($lastPing) && (time() - (int)$lastPing) < 120) {
        $parserStatus = 'active';
    }
}

$flashError = $_SESSION['error'] ?? null;
$flashSuccess = $_SESSION['success_message'] ?? null;
unset($_SESSION['error'], $_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Openvragen kennistoetsing') ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/style.css">
    <link rel="icon" type="image/png" href="/images/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg" />
    <link rel="shortcut icon" href="/images/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
</head>
<body class="d-flex flex-column min-vh-100">

<?php if (!isset($hideHeaderFooter) || !$hideHeaderFooter): ?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/">
        <img src="/images/logo-h.png" alt="Logo" height="40" class="d-inline-block align-text-top me-2">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <?php if (!empty($_SESSION['user_id'])): ?>
            <?php if (empty($_SESSION['force_password_change'])): ?>
            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'docent' || $_SESSION['role'] === 'admin')): ?>
                <li class="nav-item"><a class="nav-link" href="index.php?action=docent_dashboard">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/?action=pending_assessments">Beoordelen</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="/?action=students">Gebruikers</a></li>
                    <li class="nav-item"><a class="nav-link" href="/?action=api_keys">API Keys</a></li>
                    <li class="nav-item"><a class="nav-link" href="/?action=prompts">Prompts</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="/?action=audit_log">Audit Log</a></li>
                <li class="nav-item"><a class="nav-link" href="/?action=my_exams">Mijn Toetsen</a></li>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'beoordelaar'): ?>
                <li class="nav-item"><a class="nav-link" href="/?action=pending_assessments">Beoordelen</a></li>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <li class="nav-item"><a class="nav-link" href="index.php?action=student_dashboard">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/?action=my_exams">Mijn Toetsen</a></li>
            <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
      </ul>
      
      <div class="d-flex align-items-center gap-3">
        <span class="badge <?= $parserStatus === 'active' ? 'badge-status-active' : 'badge-status-inactive' ?>">
            Parser <?= $parserStatus === 'active' ? 'Actief' : 'Inactief' ?>
        </span>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="text-white text-end lh-1 d-none d-lg-block">
                <small class="d-block fw-bold"><?= e($_SESSION['name'] ?? '') ?></small>
                <small class="opacity-75" style="font-size: 0.75rem;"><?= e(ucfirst($_SESSION['role'] ?? '')) ?></small>
            </div>
            <?php if (empty($_SESSION['force_password_change'])): ?>
            <a href="/?action=student_edit&id=<?= (int)$_SESSION['user_id'] ?>" class="btn btn-sm btn-outline-light ms-2">Profiel</a>
            <?php endif; ?>
            <a href="index.php?action=logout" class="btn btn-sm btn-outline-light ms-2">Uitloggen</a>
        <?php else: ?>
            <?php if (empty($isGuest)): ?>
                <a href="index.php?action=login" class="btn btn-sm btn-light">Login</a>
            <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<?php endif; ?>

<main class="container my-4 flex-grow-1">
<?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <?php foreach ($breadcrumbs as $label => $url): ?>
                <?php if ($url): ?>
                    <li class="breadcrumb-item"><a href="<?= e($url) ?>" class="text-decoration-none"><?= e($label) ?></a></li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($label) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </span>
    <?php 
    // Zoek de laatste URL om als terug-knop te gebruiken
    $backUrl = null;
    $urls = array_filter(array_values($breadcrumbs));
    if (!empty($urls)) {
        $backUrl = end($urls);
    }
    ?>
    <?php if ($backUrl): ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary btn-sm">
            &larr; Terug
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?= $content ?? '' ?>
</main>

<?php if (!isset($hideHeaderFooter) || !$hideHeaderFooter): ?>
<footer class="bg-light py-4 mt-auto border-top">
    <div class="container text-center text-muted">
        &copy; <?= date('Y') ?> Openvragen kennistoetsing (proof-of-concept) - powered by JMNL Innovation<br>
        <small><a href="/?action=privacy" class="text-decoration-none text-muted">Privacy & Cookies</a></small>
    </div>
</footer>
<?php endif; ?>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bevestiging</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmationMessage">
        Weet je het zeker?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
        <button type="button" class="btn btn-primary" id="confirmActionBtn">Bevestigen</button>
      </div>
    </div>
  </div>
</div>

<!-- Cookie Banner -->
<div id="cookieBanner" class="fixed-bottom p-3 bg-dark text-white shadow-lg" style="display: none; z-index: 1050;">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-2 mb-md-0">
                <strong>Cookie melding:</strong> Wij gebruiken functionele cookies om deze applicatie goed te laten werken en voor onderzoeksdoeleinden in het onderwijs. 
                <a href="/?action=privacy" class="text-info text-decoration-underline">Lees meer</a>.
            </div>
            <button id="acceptCookiesBtn" class="btn btn-primary btn-sm text-nowrap">Ik begrijp het</button>
        </div>
    </div>
</div>

<script nonce="<?= e(cspNonce()) ?>">
// Global function to show confirmation modal
window.showConfirmationModal = function(message, onConfirm) {
    var modalEl = document.getElementById('confirmationModal');
    var messageBody = document.getElementById('confirmationMessage');
    var confirmBtn = document.getElementById('confirmActionBtn');
    
    messageBody.textContent = message;
    
    // Clone button to remove old listeners
    var newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
    
    newBtn.addEventListener('click', function() {
        var modalInstance = bootstrap.Modal.getInstance(modalEl);
        modalInstance.hide();
        onConfirm();
    });
    
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
};

// Kopieert de waarde van een input naar het klembord (data-copy-target="<id>")
window.copyLink = function(elementId) {
    var copyText = document.getElementById(elementId);
    if (!copyText) { return; }
    copyText.select();
    copyText.setSelectionRange(0, 99999); // Voor mobiele apparaten
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(copyText.value);
    } else {
        document.execCommand('copy');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('click', function(e) {
        var copyBtn = e.target.closest('[data-copy-target]');
        if (copyBtn) {
            e.preventDefault();
            window.copyLink(copyBtn.getAttribute('data-copy-target'));
            var label = copyBtn.getAttribute('data-copy-feedback');
            if (label) { alert(label); }
            return;
        }
        var selectEl = e.target.closest('[data-select-on-click]');
        if (selectEl && typeof selectEl.select === 'function') {
            selectEl.select();
            return;
        }

        var target = e.target.closest('[data-confirm]');
        if (target) {
            e.preventDefault();
            var message = target.getAttribute('data-confirm');
            
            window.showConfirmationModal(message, function() {
                if (target.tagName === 'A') {
                    // Zet GET link om naar POST formulier met CSRF token
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = target.href;
                    
                    var csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = <?= json_encode(generateCsrfToken()) ?>;
                    
                    form.appendChild(csrfInput);
                    document.body.appendChild(form);
                    form.submit();
                } else if (target.tagName === 'BUTTON' && target.type === 'submit') {
                    if (target.form.requestSubmit) {
                        target.form.requestSubmit(target);
                    } else {
                        // Fallback for older browsers
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = target.name;
                        input.value = target.value;
                        target.form.appendChild(input);
                        target.form.submit();
                    }
                }
            });
        }
    });

    // Cookie Banner Logic
    var cookieBanner = document.getElementById('cookieBanner');
    var acceptBtn = document.getElementById('acceptCookiesBtn');
    
    // Check if cookie exists
    if (document.cookie.indexOf('cookie_consent=1') === -1) {
        cookieBanner.style.display = 'block';
    }

    acceptBtn.addEventListener('click', function() {
        // Set cookie for 1 year
        var date = new Date();
        date.setTime(date.getTime() + (365*24*60*60*1000));
        document.cookie = "cookie_consent=1; expires=" + date.toUTCString() + "; path=/; SameSite=Strict";
        cookieBanner.style.display = 'none';
    });
});
</script>
</body>
</html>
