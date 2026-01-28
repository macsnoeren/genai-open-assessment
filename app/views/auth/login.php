<?php
ob_start();
?>

<div class="login-container">
  <h2>Inloggen</h2>

  <?php if (!empty($_SESSION['error'])): ?>
  <p class="error"><?= $_SESSION['error'] ?></p>
  <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <form method="POST" action="index.php?action=do_login">
    <label for="email">Email</label>
    <input type="email" name="email" id="email" placeholder="voorbeeld@school.nl" required>

    <label for="password">Wachtwoord</label>
    <input type="password" name="password" id="password" placeholder="••••••••" required>

    <button type="submit">Login</button>
  </form>
</div>

<?php
$content = ob_get_clean();
$title = "Login";
require __DIR__ . '/../layouts/main.php';
?>
