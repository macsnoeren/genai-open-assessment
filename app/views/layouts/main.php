<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?? 'Openvragen kennistoetsing' ?></title>
        <link rel="stylesheet" href="style.css">
	</head>
	<body>

<header>
    <h1>Openvragen kennistoetsing (proof-of-concept) - formatief</h1>
</header>

<nav>
  <?php if (!empty($_SESSION['user_id'])): ?>
    <?php if ($_SESSION['role'] == 'docent'): ?>
      <a href="index.php?action=docent_dashboard">Dashboard</a>
    <?php else: ?>
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
