<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Class AuthController
 * Handles authentication (login, logout, registration).
 */
class AuthController {
  
  public function showLogin() {
    if (isset($_SESSION['user_id'])) {
        $role = $_SESSION['role'] ?? 'student';
        if ($role === 'docent' || $role === 'admin') {
            header('Location: index.php?action=docent_dashboard');
        } elseif ($role === 'beoordelaar') {
            header('Location: index.php?action=pending_assessments');
        } else {
            header('Location: index.php?action=student_dashboard');
        }
        exit;
    }
    require __DIR__ . '/../views/auth/login.php';
  }
  
  /**
   * Processes the login request.
   */
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
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];
    
    AuditLog::log('login_success');
    
    // redirect op rol
    if ($user['role'] === 'docent' || $user['role'] === 'admin') {
      header('Location: index.php?action=docent_dashboard');
    } elseif ($user['role'] === 'beoordelaar') {
      header('Location: index.php?action=pending_assessments');
    } else {
      header('Location: index.php?action=student_dashboard');
    }
    exit;
  }
  
  /**
   * Logs the user out and destroys the session.
   */
  public function logout() {
    AuditLog::log('logout');
    session_destroy();
    header('Location: index.php?action=login');
    exit;
  }

  /**
   * Shows the registration form (only if no users exist).
   */
  public function showRegister() {
    require __DIR__ . '/../views/auth/register.php';
  }

  /**
   * Registers a new user. First user becomes admin, others become student.
   */
  public function register() {
    $pdo = Database::connect();
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Eerste gebruiker is admin, daarna student
    $role = ($count == 0) ? 'admin' : 'student';

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $password, $role]);

    header('Location: /?action=login');
    exit;
  }
}

?>
