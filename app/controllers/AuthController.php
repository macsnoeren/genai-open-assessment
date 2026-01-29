<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

class AuthController {
  
  public function showLogin() {
    require __DIR__ . '/../views/auth/login.php';
  }
  
  public function login() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $user = User::findByEmail($email);
    
    if (!$user || !password_verify($password, $user['password'])) {
      $_SESSION['error'] = 'Ongeldige inloggegevens';
      header('Location: index.php?action=login');
      exit;
    }
    
    // login succesvol
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];
    
    AuditLog::log('login_success');
    
    // redirect op rol
    if ($user['role'] === 'docent') {
      header('Location: index.php?action=docent_dashboard');
    } else {
      header('Location: index.php?action=student_dashboard');
    }
    exit;
  }
  
  public function logout() {
    AuditLog::log('logout');
    session_destroy();
    header('Location: index.php?action=login');
    exit;
  }

  public function showRegister() {
    // Check of er al gebruikers zijn
    $pdo = Database::connect();
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    if ($count > 0) {
        die("Registratie is gesloten. Er zijn al gebruikers in het systeem.");
    }
    
    require __DIR__ . '/../views/auth/register.php';
  }

  public function registerFirstAdmin() {
    $pdo = Database::connect();
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    if ($count > 0) {
        die("Registratie is gesloten.");
    }

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$name, $email, $password]);

    header('Location: /?action=login');
    exit;
  }
}

?>
