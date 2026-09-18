<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Class AuthController
 * Handles authentication (login, logout, registration).
 */
class AuthController {

  private function redirectByRole(string $role): void {
    if ($role === 'docent' || $role === 'admin') {
        header('Location: index.php?action=docent_dashboard');
    } elseif ($role === 'beoordelaar') {
        header('Location: index.php?action=pending_assessments');
    } else {
        header('Location: index.php?action=student_dashboard');
    }
    exit;
  }
  
  public function showLogin() {
    if (isset($_SESSION['user_id'])) {
        $this->redirectByRole($_SESSION['role'] ?? 'student');
    }
    require __DIR__ . '/../views/auth/login.php';
  }
  
  /**
   * Processes the login request.
   */
  public function login() {
    validateCsrfToken();
    $email = strtolower(trim(requestString($_POST, 'email', 254)));
    $password = requestString($_POST, 'password', 1024);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // Brute-force-bescherming: per IP en per e-mailadres
    $failuresByIp = AuditLog::countRecent('login_failed', LOGIN_LOCKOUT_MINUTES, $ip);
    $failuresByEmail = $email !== '' ? AuditLog::countRecent('login_failed', LOGIN_LOCKOUT_MINUTES, null, $email) : 0;
    if ($failuresByIp >= LOGIN_MAX_FAILURES || $failuresByEmail >= LOGIN_MAX_FAILURES) {
      AuditLog::log('login_blocked', ['email' => $email], $email !== '' ? $email : 'Unknown');
      $_SESSION['error'] = 'Te veel mislukte inlogpogingen. Probeer het over ' . LOGIN_LOCKOUT_MINUTES . ' minuten opnieuw.';
      header('Location: index.php?action=login');
      exit;
    }
    
    $user = $email !== '' ? User::findByEmail($email) : null;
    
    if (!$user || !password_verify($password, $user['password'])) {
      AuditLog::log('login_failed', ['email' => $email], $email !== '' ? $email : 'Unknown');
      $_SESSION['error'] = 'Ongeldige inloggegevens';
      header('Location: index.php?action=login');
      exit;
    }
    
    // login succesvol
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['force_password_change'] = $user['force_password_change'] ?? 0;
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
    
    AuditLog::log('login_success');
    
    if (!empty($_SESSION['force_password_change'])) {
        header('Location: index.php?action=change_password');
        exit;
    }

    $this->redirectByRole($user['role']);
  }
  
  /**
   * Logs the user out and destroys the session.
   */
  public function logout() {
    if (isset($_SESSION['user_id'])) {
        AuditLog::log('logout');
    }
    destroySession();
    header('Location: index.php?action=login');
    exit;
  }

  /**
   * Shows the registration form (only if self-registration is enabled).
   */
  public function showRegister() {
    if (!ALLOW_SELF_REGISTRATION) {
        abort(403, 'Zelfregistratie is uitgeschakeld. Vraag een beheerder om een account.');
    }
    require __DIR__ . '/../views/auth/register.php';
  }

  /**
   * Registers a new student account (only if self-registration is enabled).
   */
  public function register() {
    validateCsrfToken();
    if (!ALLOW_SELF_REGISTRATION) {
        abort(403, 'Zelfregistratie is uitgeschakeld. Vraag een beheerder om een account.');
    }

    $name = trim(requestString($_POST, 'name', MAX_NAME_LENGTH));
    $email = normalizeEmail(requestString($_POST, 'email', 254));
    $password = requestString($_POST, 'password', 1024);

    if ($name === '' || $email === null) {
        $_SESSION['error'] = 'Vul een geldige naam en e-mailadres in.';
        header('Location: /?action=register');
        exit;
    }
    if (($error = validatePasswordPolicy($password)) !== null) {
        $_SESSION['error'] = $error;
        header('Location: /?action=register');
        exit;
    }
    if (User::findByEmail($email)) {
        $_SESSION['error'] = 'Dit e-mailadres is al in gebruik.';
        header('Location: /?action=register');
        exit;
    }

    $pdo = Database::connect();
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    AuditLog::log('user_register', ['email' => $email], $email);

    header('Location: /?action=login');
    exit;
  }

  public function showChangePassword() {
    requireLogin();
    require __DIR__ . '/../views/auth/change_password.php';
  }

  public function updatePassword() {
    validateCsrfToken();
    requireLogin();

    $password = requestString($_POST, 'password', 1024);
    $confirm = requestString($_POST, 'confirm_password', 1024);

    if ($password !== $confirm) {
        $_SESSION['error'] = "Wachtwoorden komen niet overeen.";
        header('Location: /?action=change_password');
        exit;
    }
    if (($error = validatePasswordPolicy($password)) !== null) {
        $_SESSION['error'] = $error;
        header('Location: /?action=change_password');
        exit;
    }

    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $_SESSION['user_id']]);

    session_regenerate_id(true);
    $_SESSION['force_password_change'] = 0;
    $_SESSION['success_message'] = "Wachtwoord succesvol gewijzigd.";

    AuditLog::log('password_change_forced');

    $this->redirectByRole($_SESSION['role'] ?? 'student');
  }
}
