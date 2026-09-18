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
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../helpers/auth.php';

/**
 * Class StudentController
 * Handles user management (CRUD for students/users).
 */
class StudentController {

  private function redirectAfterUpdate(): void {
    $role = $_SESSION['role'] ?? 'student';
    if ($role === 'admin') {
        header('Location: /?action=students');
    } elseif ($role === 'docent') {
        header('Location: /?action=docent_dashboard');
    } elseif ($role === 'beoordelaar') {
        header('Location: /?action=pending_assessments');
    } else {
        header('Location: /?action=student_dashboard');
    }
    exit;
  }
  
  /**
   * Lists all users.
   */
  public function index() {
    requireRole('admin');
    
    // Haal ALLE gebruikers op (niet alleen studenten); nooit de wachtwoordhash naar de view
    $pdo = Database::connect();
    $stmt = $pdo->query("SELECT id, name, email, role, force_password_change, created_at, updated_at FROM users ORDER BY role, name");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    require __DIR__ . '/../views/docent/students.php';
  }
  
  /**
   * Shows the form to create a new user.
   */
  public function create() {
    requireRole('admin');
    
    $student = null;
    $action = 'student_store';
    $title = 'Nieuwe gebruiker';
    require __DIR__ . '/../views/docent/student_form.php';
  }
  
  /**
   * Stores a new user in the database.
   */
  public function store() {
    validateCsrfToken();
    requireRole('admin');
    
    $role = requestString($_POST, 'role', 20, 'student');
    if (!in_array($role, validRoles(), true)) {
        abort(400, 'Ongeldige rol.');
    }

    $name = trim(requestString($_POST, 'name', MAX_NAME_LENGTH));
    $email = normalizeEmail(requestString($_POST, 'email', 254));
    $password = requestString($_POST, 'password', 1024);

    if ($name === '' || $email === null) {
        $_SESSION['error'] = 'Vul een geldige naam en e-mailadres in.';
        header('Location: /?action=student_create');
        exit;
    }
    if (($error = validatePasswordPolicy($password)) !== null) {
        $_SESSION['error'] = $error;
        header('Location: /?action=student_create');
        exit;
    }
    if (User::findByEmail($email)) {
        $_SESSION['error'] = 'Dit e-mailadres is al in gebruik.';
        header('Location: /?action=student_create');
        exit;
    }

    $pdo = Database::connect();
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, force_password_change) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);

    AuditLog::log('user_create', [
        'name' => $name, 
        'role' => $role
    ]);
    
    header('Location: /?action=students');
    exit;
  }
  
  /**
   * Shows the form to edit a user.
   */
  public function edit() {
    requireLogin();

    $id = requestInt($_GET, 'id');
    if ($id === null) {
        abort(400, 'Ongeldig verzoek.');
    }

    // Check: Admin of Eigen profiel
    if ($_SESSION['role'] !== 'admin' && (int)$_SESSION['user_id'] !== $id) {
        abort(403, 'Geen toegang: je mag alleen je eigen profiel bewerken.');
    }
    
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT id, name, email, role, force_password_change FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        abort(404, 'Gebruiker niet gevonden.');
    }

    $action = 'student_update';
    $title = 'Gebruiker bewerken';
    require __DIR__ . '/../views/docent/student_form.php';
  }
  
  /**
   * Updates a user in the database.
   */
  public function update() {
    validateCsrfToken();
    requireLogin();
    
    $userIdToUpdate = requestInt($_POST, 'id');
    if ($userIdToUpdate === null) {
        abort(400, 'Ongeldig verzoek.');
    }
    $isAdmin = ($_SESSION['role'] === 'admin');
    $isSelf = ((int)$_SESSION['user_id'] === $userIdToUpdate);
    
    // Check: Admin of Eigen profiel
    if (!$isAdmin && !$isSelf) {
        abort(403, 'Geen toegang.');
    }

    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT id, role, email, password, force_password_change FROM users WHERE id = ?");
    $stmt->execute([$userIdToUpdate]);
    $currentUserData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentUserData) {
        abort(404, 'Gebruiker niet gevonden.');
    }

    $name = trim(requestString($_POST, 'name', MAX_NAME_LENGTH));
    if ($name === '') {
        $_SESSION['error'] = 'Naam mag niet leeg zijn.';
        header('Location: /?action=student_edit&id=' . $userIdToUpdate);
        exit;
    }
    
    // Als geen admin: behoud huidige rol, e-mail en force_password_change
    if (!$isAdmin) {
        $newRole = $currentUserData['role'];
        $email = $currentUserData['email'];
        $forcePasswordChange = $currentUserData['force_password_change'];
    } else {
        $newRole = requestString($_POST, 'role', 20, 'student');
        if (!in_array($newRole, validRoles(), true)) {
            abort(400, 'Ongeldige rol.');
        }
        $email = normalizeEmail(requestString($_POST, 'email', 254));
        if ($email === null) {
            $_SESSION['error'] = 'Ongeldig e-mailadres.';
            header('Location: /?action=student_edit&id=' . $userIdToUpdate);
            exit;
        }
        $existing = User::findByEmail($email);
        if ($existing && (int)$existing['id'] !== $userIdToUpdate) {
            $_SESSION['error'] = 'Dit e-mailadres is al in gebruik.';
            header('Location: /?action=student_edit&id=' . $userIdToUpdate);
            exit;
        }
        $forcePasswordChange = isset($_POST['force_password_change']) ? 1 : 0;
    }

    $newPassword = requestString($_POST, 'password', 1024);
    $passwordChanged = false;
    if ($newPassword !== '') {
        if (($error = validatePasswordPolicy($newPassword)) !== null) {
            $_SESSION['error'] = $error;
            header('Location: /?action=student_edit&id=' . $userIdToUpdate);
            exit;
        }
        // Eigen wachtwoord wijzigen vereist het huidige wachtwoord
        if ($isSelf) {
            $current = requestString($_POST, 'current_password', 1024);
            if ($current === '' || !password_verify($current, $currentUserData['password'])) {
                $_SESSION['error'] = 'Het huidige wachtwoord is onjuist.';
                header('Location: /?action=student_edit&id=' . $userIdToUpdate);
                exit;
            }
        }
        $passwordChanged = true;
    }
    
    if ($passwordChanged) {
        $sql = "UPDATE users SET name = ?, email = ?, role = ?, force_password_change = ?, password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $params = [$name, $email, $newRole, $forcePasswordChange, password_hash($newPassword, PASSWORD_DEFAULT), $userIdToUpdate];
    } else {
        $sql = "UPDATE users SET name = ?, email = ?, role = ?, force_password_change = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $params = [$name, $email, $newRole, $forcePasswordChange, $userIdToUpdate];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    AuditLog::log('user_update', [
        'id' => $userIdToUpdate,
        'name' => $name,
        'role' => $newRole,
        'password_changed' => $passwordChanged,
    ]);

    if ($isSelf) {
        $_SESSION['name'] = $name;
        if ($passwordChanged) {
            session_regenerate_id(true);
        }
    }
    $_SESSION['success_message'] = 'Gebruiker opgeslagen.';
    
    $this->redirectAfterUpdate();
  }
  
  /**
   * Deletes a user.
   */
  public function delete() {
    validateCsrfToken();
    requireRole('admin');

    $id = requestInt($_GET, 'id') ?? requestInt($_POST, 'id');
    if ($id === null) {
        abort(400, 'Ongeldig verzoek.');
    }
    if ($id === (int)$_SESSION['user_id']) {
        abort(400, 'Je kunt jezelf niet verwijderen.');
    }

    try {
        Database::connect()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        AuditLog::log('user_delete', ['id' => $id]);
    } catch (PDOException $e) {
        // FK RESTRICT: docent heeft nog toetsen
        $_SESSION['error'] = 'Deze gebruiker kan niet worden verwijderd zolang er toetsen aan gekoppeld zijn.';
    }
    header('Location: /?action=students');
    exit;
  }
}
