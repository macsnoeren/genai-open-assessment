<?php

require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../helpers/auth.php';

class StudentController {
  
  public function index() {
    requireLogin();
    requireRole('docent');
    
    $students = Student::all();
    require __DIR__ . '/../views/docent/students.php';
  }
  
  public function create() {
    requireLogin();
    requireRole('docent');
    
    $student = null;
    $action = 'student_store';
    $title = 'Nieuwe student';
    require __DIR__ . '/../views/docent/student_form.php';
  }
  
  public function store() {
    requireLogin();
    requireRole('docent');
    
    Student::create(
	            $_POST['name'],
		    $_POST['email'],
		    $_POST['password']
		    );
    AuditLog::log('student_create', ['name' => $_POST['name'], 'email' => $_POST['email']]);
    
    header('Location: /?action=students');
    exit;
  }
  
  public function edit() {
    requireLogin();
    requireRole('docent');
    
    $student = Student::find($_GET['id']);
    $action = 'student_update';
    $title = 'Student bewerken';
    require __DIR__ . '/../views/docent/student_form.php';
  }
  
  public function update() {
    requireLogin();
    requireRole('docent');
    
    Student::update(
	            $_POST['id'],
		    $_POST['name'],
		    $_POST['email'],
		    $_POST['password'] ?? null
		    );
    AuditLog::log('student_update', ['id' => $_POST['id'], 'name' => $_POST['name']]);
    
    header('Location: /?action=students');
    exit;
  }
  
  public function delete() {
            requireLogin();
	    requireRole('docent');
	    
	    AuditLog::log('student_delete', ['id' => $_GET['id']]);
	    Student::delete($_GET['id']);
	    header('Location: /?action=students');
	    exit;
  }
}
?>
