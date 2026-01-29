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
}

?>
