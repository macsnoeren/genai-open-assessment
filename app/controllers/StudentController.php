<?php

require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../helpers/auth.php';

class StudentController {
  
  public function index() {
    requireLogin();
    requireRole('docent');
    
    // Haal ALLE gebruikers op (niet alleen studenten)
    $pdo = Database::connect();
    $stmt = $pdo->query("SELECT * FROM users ORDER BY role, name");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    require __DIR__ . '/../views/docent/students.php';
  }
  
  public function create() {
    requireLogin();
    requireRole('docent');
    
    $student = null;
    $action = 'student_store';
    $title = 'Nieuwe gebruiker';
    require __DIR__ . '/../views/docent/student_form.php';
  }
  
  public function store() {
    requireLogin();
    requireRole('docent');
    
    $role = $_POST['role'] ?? 'student';
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
  
  public function edit() {
    requireLogin();
    requireRole('docent');
    
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    $action = 'student_update';
    $title = 'Gebruiker bewerken';
    require __DIR__ . '/../views/docent/student_form.php';
  }
  
  public function update() {
    requireLogin();
    requireRole('docent');
    
    $pdo = Database::connect();
    
    $sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
    $params = [$_POST['name'], $_POST['email'], $_POST['role'], $_POST['id']];

    if (!empty($_POST['password'])) {
        $sql = "UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?";
        $params = [$_POST['name'], $_POST['email'], $_POST['role'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['id']];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    AuditLog::log('user_update', [
        'id' => $_POST['id'], 
        'name' => $_POST['name'],
        'role' => $_POST['role']
    ]);
    
    header('Location: /?action=students');
    exit;
  }
  
  public function delete() {
            requireLogin();
	    requireRole('docent');
	    
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
