<?php

function requireLogin() {
  if (!isset($_SESSION['user_id'])) {
    header('Location: /?action=login');
    exit;
  }
}

function requireRole($role) {
  if ($_SESSION['role'] !== $role) {
    die('Geen toegang');
  }
}

?>
