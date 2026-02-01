<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../helpers/auth.php';

/**
 * Class StudentController
 * Handles user management (CRUD for students/users).
 */
class StudentController {
  
  /**
   * Lists all users.
   */
  public function index() {
    requireLogin();
    requireRole('admin');
    
    // Haal ALLE gebruikers op (niet alleen studenten)
    $pdo = Database::connect();
    $stmt = $pdo->query("SELECT * FROM users ORDER BY role, name");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    require __DIR__ . '/../views/docent/students.php';
  }
  
  /**
   * Shows the form to create a new user.
   */
  public function create() {
    requireLogin();
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
    requireLogin();
    requireRole('admin');
    
    $role = $_POST['role'] ?? 'student';
    // Alleen admins mogen de admin rol toewijzen.
    if ($role === 'admin' && $_SESSION['role'] !== 'admin') {
        die("Geen toegang: alleen admins kunnen de admin rol toewijzen.");
    }

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $pdo = Database::connect();
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['name'], $_POST['email'], $password, $role]);

    AuditLog::log('user_create', [
        'name' => $_POST['name'], 
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
    
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check: Admin of Eigen profiel
    if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $student['id']) {
        die("Geen toegang: je mag alleen je eigen profiel bewerken.");
    }

    $action = 'student_update';
    $title = 'Gebruiker bewerken';
    require __DIR__ . '/../views/docent/student_form.php';
  }
  
  /**
   * Updates a user in the database.
   */
  public function update() {
    requireLogin();
    
    $userIdToUpdate = $_POST['id'];
    
    // Check: Admin of Eigen profiel
    if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $userIdToUpdate) {
        die("Geen toegang.");
    }

    $pdo = Database::connect();
    
    // Als geen admin, behoud huidige rol
    if ($_SESSION['role'] !== 'admin') {
        $stmt = $pdo->prepare("SELECT role, email FROM users WHERE id = ?");
        $stmt->execute([$userIdToUpdate]);
        $currentUserData = $stmt->fetch(PDO::FETCH_ASSOC);
        $newRole = $currentUserData['role'];
        $email = $currentUserData['email'];
    } else {
        $newRole = $_POST['role'] ?? 'student';
        $email = $_POST['email'];
    }
    
    $sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
    $params = [$_POST['name'], $email, $newRole, $userIdToUpdate];

    if (!empty($_POST['password'])) {
        $sql = "UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?";
        $params = [$_POST['name'], $email, $newRole, password_hash($_POST['password'], PASSWORD_DEFAULT), $userIdToUpdate];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    AuditLog::log('user_update', ['id' => $userIdToUpdate, 'name' => $_POST['name'], 'role' => $newRole]);
    
    if ($_SESSION['role'] === 'admin') {
        header('Location: /?action=students');
    } else {
        if ($_SESSION['role'] === 'docent') {
             header('Location: /?action=docent_dashboard');
        } elseif ($_SESSION['role'] === 'beoordelaar') {
             header('Location: /?action=pending_assessments');
        } else {
             header('Location: /?action=student_dashboard');
        }
    }
    exit;
  }
  
  /**
   * Deletes a user.
   */
  public function delete() {
            requireLogin();
	    requireRole('admin');
	    
        if ($_GET['id'] == $_SESSION['user_id']) {
            die("Je kunt jezelf niet verwijderen.");
        }

	    AuditLog::log('user_delete', ['id' => $_GET['id']]);
        Database::connect()->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['id']]);
	    header('Location: /?action=students');
	    exit;
  }
}
?>
