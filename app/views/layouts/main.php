<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?? 'Openvragen kennistoetsing' ?></title>
        <link rel="stylesheet" href="style.css">
	</head>
	<body>

<header>
    <h1>Toetsen van kennis met openvragen (onderzoek)</h1>
</header>

<nav>
  <?php if (!empty($_SESSION['user_id'])): ?>
    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'docent' || $_SESSION['role'] === 'admin')): ?>
      <a href="index.php?action=docent_dashboard">Dashboard</a>
      <a href="/?action=students">Gebruikers beheren</a>
      <a href="/?action=api_keys">API-keys beheren</a>
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
