<?php

require_once __DIR__ . '/../models/Student.php';
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
    
    header('Location: /?action=students');
    exit;
  }
  
  public function delete() {
            requireLogin();
	    requireRole('docent');
	    
	    Student::delete($_GET['id']);
	    header('Location: /?action=students');
	    exit;
  }
}
?>
