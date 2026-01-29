<?php
$parserStatus = 'inactive';
$pingFile = __DIR__ . '/../../../database/last_api_ping.txt';
if (file_exists($pingFile)) {
    $lastPing = (int)@file_get_contents($pingFile);
    if ((time() - $lastPing) < 120) { // 2 minuten
        $parserStatus = 'active';
    }
}
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
        <span style="background-color: #CCC; font-size: 0.9em; color: <?= $parserStatus === 'active' ? '#28a745' : '#dc3545' ?>;">● Parser actief</span>
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
