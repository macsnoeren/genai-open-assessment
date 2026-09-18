<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/helpers/security.php';

registerErrorHandling(false);
sendSecurityHeaders(true);

// Configure session cookie parameters for security
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isHttps(),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/StudentController.php';
require_once __DIR__ . '/../app/controllers/DocentController.php';
require_once __DIR__ . '/../app/controllers/StudentExamController.php';
require_once __DIR__ . '/../app/controllers/ApiKeyController.php';
require_once __DIR__ . '/../app/controllers/PromptController.php';

$action = $_GET['action'] ?? 'login';
if (!is_string($action)) {
    $action = 'login';
}

$auth = new AuthController();
$docent = new DocentController();
$studentController = new StudentController();
$studentExamController = new StudentExamController();
$apiKeyController = new ApiKeyController();
$promptController = new PromptController();

switch ($action) {
 case 'login':
   $auth->showLogin();
   break;
   
 case 'do_login':
   $auth->login();
   break;
   
 case 'logout':
   $auth->logout();
   break;

 case 'register':
   $auth->showRegister();
   break;

 case 'do_register':
   $auth->register();
   break;

 case 'change_password':
   $auth->showChangePassword();
   break;

 case 'do_change_password':
   $auth->updatePassword();
   break;
    
 case 'docent_dashboard':
   $docent->dashboard();
   break;
   
 case 'exam_create':
   $docent->createExam();
   break;
   
 case 'exam_store':
   $docent->storeExam();
   break;
   
 case 'exam_edit':
   $docent->editExam();
   break;
   
 case 'exam_update':
   $docent->updateExam();
   break;
   
 case 'exam_delete':
   $docent->deleteExam();
   break;

 case 'exam_duplicate':
   $docent->duplicateExam();
   break;
   
 case 'questions':
   $docent->questions(requestInt($_GET, 'exam_id'));
   break;
   
 case 'question_create':
   $docent->createQuestion();
   break;
   
 case 'question_store':
   $docent->storeQuestion();
   break;
   
 case 'question_edit':
   $docent->editQuestion();
   break;
	
 case 'question_update':
   $docent->updateQuestion();
   break;
   
 case 'question_delete':
   $docent->deleteQuestion();
   break;

 case 'student_dashboard':
   $studentExamController->dashboard();
   break;
   
 case 'students':
   $studentController->index();
   break;
   
 case 'student_create':
   $studentController->create();
   break;
   
 case 'student_store':
   $studentController->store();
   break;
   
 case 'student_edit':
   $studentController->edit();
   break;
   
 case 'student_update':
   $studentController->update();
   break;
   
 case 'student_delete':
   $studentController->delete();
   break;

 case 'exams_list':
   $studentExamController->listExams();
   break;

 case 'guest':
   $studentExamController->guestEntry();
   break;

 case 'guest_start':
   $studentExamController->guestStart();
   break;

 case 'guest_logout':
   $studentExamController->guestLogout();
   break;

 case 'start_exam':
   $studentExamController->startExam();
   break;

 case 'take_exam':
   $studentExamController->takeExam();
   break;

 case 'submit_exam':
   $studentExamController->submitExam();
   break;

 case 'my_exams':
   $studentExamController->myExams();
   break;

 case 'student_view_results':
   $studentExamController->viewResults();
   break;

 case 'exam_results':
   $docent->viewExamResults(requestInt($_GET, 'exam_id'));
   break;

 case 'exam_comparison':
   $docent->compareExamResults(requestInt($_GET, 'exam_id'));
   break;

 case 'exam_comparison_export':
   $docent->exportExamComparison(requestInt($_GET, 'exam_id'));
   break;

 case 'view_student_answers':
   $docent->viewStudentAnswers(requestInt($_GET, 'student_exam_id'));
   break;

 case 'grade_student_exam':
   $docent->gradeStudentExam(requestInt($_GET, 'student_exam_id'));
   break;

 case 'save_teacher_feedback':
   $docent->saveTeacherFeedback();
   break;

 case 'delete_student_exam':
   $docent->deleteStudentExam();
   break;

 case 'update_guest_name':
   $docent->updateGuestName();
   break;

 case 'api_keys':
   $apiKeyController->index();
   break;

 case 'api_key_create':
   $apiKeyController->create();
   break;

 case 'api_key_toggle':
   $apiKeyController->toggle();
   break;

 case 'api_key_delete':
   $apiKeyController->delete();
   break;
   
 case 'audit_log':
   $docent->auditLog();
   break;

  case 'clear_audit_log':
    $docent->clearAuditLog();
    break;

 case 'pending_assessments':
    $docent->pendingAssessments();
    break;

 case 'prompts':
    $promptController->index();
    break;

 case 'prompt_create':
    $promptController->create();
    break;

 case 'prompt_store':
    $promptController->store();
    break;

 case 'prompt_edit':
    $promptController->edit();
    break;

 case 'prompt_update':
    $promptController->update();
    break;

 case 'prompt_delete':
    $promptController->delete();
    break;

 case 'prompt_help':
    $promptController->help();
    break;

 case 'privacy':
    require __DIR__ . '/../app/views/pages/privacy.php';
    break;
   
 default:
   abort(404, 'Pagina niet gevonden.');
 }
