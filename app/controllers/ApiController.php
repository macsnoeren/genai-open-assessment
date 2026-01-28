<?php

require_once __DIR__ . '/../models/ApiKey.php';
require_once __DIR__ . '/../models/StudentAnswer.php';

class ApiController {

    public function __construct() {
        header('Content-Type: application/json');
    }

    private function verifyApiKey() {
        $key = $_GET['api_key'] ?? null;
        if (!$key || !ApiKey::isValid($key)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: Invalid or missing API Key']);
            exit;
        }
    }

    public function getOpenAnswers() {
        $this->verifyApiKey();
        try {
            $answers = StudentAnswer::getPendingAiGrading();
            echo json_encode(['answers' => $answers]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function submitAiFeedback() {
        $this->verifyApiKey();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['student_answer_id']) || !isset($input['ai_feedback'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        StudentAnswer::updateAiFeedback($input['student_answer_id'], $input['ai_feedback']);
        echo json_encode(['status' => 'success']);
    }
}