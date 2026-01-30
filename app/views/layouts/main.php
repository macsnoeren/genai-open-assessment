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
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="header-left">
        <h1>Toetsen van kennis met openvragen (onderzoek)</h1>
        <span class="status-badge <?= $parserStatus === 'active' ? 'status-active' : 'status-inactive' ?>">parser <?= $parserStatus === 'active' ? '' : 'niet ' ?>actief</span>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['name']) ?></strong>
            <br>
            <span class="user-role"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
        </div>
    <?php endif; ?>
</header>

<nav>
  <?php if (!empty($_SESSION['user_id'])): ?>
    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'docent' || $_SESSION['role'] === 'admin')): ?>
      <a href="index.php?action=docent_dashboard">Dashboard</a>
      <a href="/?action=pending_assessments">Docent beoordelingen</a>
      <a href="/?action=students">Gebruikers beheren</a>
      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
      <a href="/?action=api_keys">API-keys beheren</a>
      <?php endif; ?>
      <a href="/?action=audit_log">Audit Log</a>
      <a href="/?action=my_exams">Mijn Testpogingen</a>
    <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'beoordelaar'): ?>
      <a href="/?action=pending_assessments">Docent beoordelingen</a>
    <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
      <a href="index.php?action=student_dashboard">Dashboard</a>
    <?php endif; ?>
  <a href="index.php?action=logout">Uitloggen</a>
  <?php else: ?>
  <a href="index.php?action=login">Login</a>
  <?php endif; ?>
</nav>

<main>
<?= $content ?? '' ?>
</main>

<footer>
    &copy; <?= date('Y') ?> Openvragen kennistoetsing (proof-of-concept) - powered by JMNL Innovation
</footer>

</body>
</html>
