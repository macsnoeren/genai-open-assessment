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
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/ApiKey.php';
require_once __DIR__ . '/../models/StudentAnswer.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Class ApiController
 * Handles external API requests (e.g., from the AI feedback service).
 */
class ApiController {

    /** Geverifieerde API-key (id + name) van het huidige verzoek. */
    private $apiKey = null;

    /**
     * Leest de API-key uit de Authorization: Bearer <key> of X-Api-Key header.
     * De key wordt bewust NIET uit de query string gelezen (belandt in logs).
     */
    private function readApiKeyFromHeaders(): string {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($auth === '' && function_exists('apache_request_headers')) {
            $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
            $auth = $headers['authorization'] ?? '';
            if ($auth === '' && isset($headers['x-api-key'])) {
                return trim((string)$headers['x-api-key']);
            }
        }
        if (preg_match('/^Bearer\s+(.+)$/i', trim((string)$auth), $m)) {
            return trim($m[1]);
        }
        return trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
    }

    /**
     * Verifies the API key provided in the request.
     */
    private function verifyApiKey() {
        $key = $this->readApiKeyFromHeaders();
        $this->apiKey = ApiKey::findActiveByKey($key);

        if (!$this->apiKey) {
            AuditLog::log('api_auth_failed');
            http_response_code(401);
            header('WWW-Authenticate: Bearer');
            echo json_encode(['error' => 'Unauthorized: Invalid or missing API Key']);
            exit;
        }
    }

    /**
     * Retrieves open answers that need AI grading.
     */
    public function getOpenAnswers() {
        header('Content-Type: application/json');
        $this->verifyApiKey();

        $pingFile = __DIR__ . '/../../database/last_api_ping.txt';
        @file_put_contents($pingFile, time());
        
        $limit = requestInt($_GET, 'limit');
        if ($limit !== null) {
            $limit = max(1, min($limit, 100));
        }

        $answers = StudentAnswer::getPendingAiGrading($limit);
        if (count($answers) > 0) {
            AuditLog::log('api_open_answers', [
                'api_key_id' => $this->apiKey['id'],
                'count' => count($answers),
            ], 'API:' . $this->apiKey['name']);
        }
        echo json_encode(['answers' => $answers]);
    }

    /**
     * Receives AI feedback and updates the student answer.
     */
    public function submitAiFeedback() {
        header('Content-Type: application/json');
        $this->verifyApiKey();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($input) || !isset($input['student_answer_id']) || !isset($input['ai_feedback'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        $answerId = requestInt($input, 'student_answer_id');
        $feedback = $input['ai_feedback'];
        if ($answerId === null || !is_string($feedback) || trim($feedback) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid student_answer_id or ai_feedback']);
            return;
        }
        if (strlen($feedback) > MAX_AI_FEEDBACK_LENGTH) {
            http_response_code(413);
            echo json_encode(['error' => 'ai_feedback too long (max ' . MAX_AI_FEEDBACK_LENGTH . ' characters)']);
            return;
        }

        if (!StudentAnswer::find($answerId)) {
            http_response_code(404);
            echo json_encode(['error' => 'Unknown student_answer_id']);
            return;
        }

        StudentAnswer::updateAiFeedback($answerId, $feedback);
        AuditLog::log('ai_feedback_submit', [
            'student_answer_id' => $answerId,
            'api_key_id' => $this->apiKey['id'],
        ], 'API:' . $this->apiKey['name']);
        echo json_encode(['status' => 'success']);
    }
}
