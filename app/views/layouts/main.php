<?php
$parserStatus = 'inactive';
$pingFile = __DIR__ . '/../../../database/last_api_ping.txt';

// Check if the file exists, is readable, and contains a recent timestamp
if (is_readable($pingFile)) {
    $lastPing = file_get_contents($pingFile);
    if ($lastPing !== false && is_numeric($lastPing) && (time() - (int)$lastPing) < 120) {
        $parserStatus = 'active';
    }
}
// If the file doesn't exist, is not readable, or the timestamp is old, the status remains 'inactive' (red dot).
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?? 'Openvragen kennistoetsing' ?></title>
        <link rel="stylesheet" href="style.css">
	</head>
	<body>

<header style="display: flex; justify-content: space-between; align-items: center;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <h1 style="margin: 0;">Toetsen van kennis met openvragen (onderzoek)</h1>
        <span style="padding: 2px; border: solid 1px #000; background-color: <?= $parserStatus === 'active' ? '#28a745' : '#da7680' ?>; font-size: 0.9em; color: #000;">parser <?= $parserStatus === 'active' ? '' : 'niet ' ?>actief</span>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div style="text-align: right; font-size: 0.9em;">
            <strong><?= htmlspecialchars($_SESSION['name']) ?></strong>
            <br>
            <span style="font-size: 0.85em; opacity: 0.9;"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
        </div>
    <?php endif; ?>
</header>

<nav>
  <?php if (!empty($_SESSION['user_id'])): ?>
    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'docent' || $_SESSION['role'] === 'admin')): ?>
      <a href="index.php?action=docent_dashboard">Dashboard</a>
      <a href="/?action=students">Gebruikers beheren</a>
      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
      <a href="/?action=api_keys">API-keys beheren</a>
      <?php endif; ?>
      <a href="/?action=audit_log">Audit Log</a>
      <a href="/?action=my_exams">Mijn Testpogingen</a>
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
    &copy; <?= date('Y') ?> Openvragen kennistoetsing (proof-of-concept)
</footer>

<script src="/js/modal.js"></script>
</body>
</html>
