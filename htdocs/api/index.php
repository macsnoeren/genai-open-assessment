<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/controllers/ApiController.php';

$controller = new ApiController();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'open_student_answers':
        $controller->getOpenAnswers();
        break;
        
    case 'submit_ai_feedback':
        $controller->submitAiFeedback();
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown endpoint']);
}

?>
