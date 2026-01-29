<?php
session_start();

require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/StudentController.php';
require_once __DIR__ . '/../app/controllers/DocentController.php';
require_once __DIR__ . '/../app/controllers/StudentExamController.php';
require_once __DIR__ . '/../app/controllers/ApiKeyController.php';

$action = $_GET['action'] ?? 'login';

$auth = new AuthController();
$docent = new DocentController();
$studentController = new StudentController();
$studentExamController = new StudentExamController();
$apiKeyController = new ApiKeyController();

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
   
 case 'questions':
   $docent->questions($_GET['exam_id']);
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

 case 'exam_results':
   $docent->viewExamResults($_GET['exam_id']);
   break;

 case 'view_student_answers':
   $docent->viewStudentAnswers($_GET['student_exam_id']);
   break;

 case 'delete_student_exam':
   $docent->deleteStudentExam();
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
   
 default:
   echo "404";
 }

?>
