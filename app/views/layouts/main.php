<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

$parserStatus = 'inactive';
$pingFile = __DIR__ . '/../../../database/last_api_ping.txt';

// Check if the file exists, is readable, and contains a recent timestamp
if (file_exists($pingFile) && is_readable($pingFile)) {
    $lastPing = file_get_contents($pingFile);
    if ($lastPing !== false && is_numeric($lastPing) && (time() - (int)$lastPing) < 120) {
        $parserStatus = 'active';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Openvragen kennistoetsing' ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="d-flex flex-column min-vh-100">

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
            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'docent' || $_SESSION['role'] === 'admin')): ?>
                <li class="nav-item"><a class="nav-link" href="index.php?action=docent_dashboard">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/?action=pending_assessments">Beoordelen</a></li>
                <li class="nav-item"><a class="nav-link" href="/?action=students">Gebruikers</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="/?action=api_keys">API Keys</a></li>
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
      </ul>
      
      <div class="d-flex align-items-center gap-3">
        <span class="badge <?= $parserStatus === 'active' ? 'badge-status-active' : 'badge-status-inactive' ?>">
            Parser <?= $parserStatus === 'active' ? 'Actief' : 'Inactief' ?>
        </span>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="text-white text-end lh-1 d-none d-lg-block">
                <small class="d-block fw-bold"><?= htmlspecialchars($_SESSION['name']) ?></small>
                <small class="opacity-75" style="font-size: 0.75rem;"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></small>
            </div>
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

<main class="container my-4 flex-grow-1">
<?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <?php foreach ($breadcrumbs as $label => $url): ?>
                <?php if ($url): ?>
                    <li class="breadcrumb-item"><a href="<?= $url ?>" class="text-decoration-none"><?= htmlspecialchars($label) ?></a></li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($label) ?></li>
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
        <a href="<?= $backUrl ?>" class="btn btn-outline-secondary btn-sm">
            &larr; Terug
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?= $content ?? '' ?>
</main>

<footer class="bg-light py-4 mt-auto border-top">
    <div class="container text-center text-muted">
    &copy; <?= date('Y') ?> Openvragen kennistoetsing (proof-of-concept) - powered by JMNL Innovation
    </div>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
