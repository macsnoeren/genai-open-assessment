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
require_once __DIR__ . '/../models/QuestionDesign.php';
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

    /**
     * Geeft de open vraagontwerpen aan de ontwerp-worker (bin/process_design_jobs.py).
     * Contract: zie ARCHITECTURE.md §6.2.
     */
    public function getOpenDesignJobs() {
        header('Content-Type: application/json');
        $this->verifyApiKey();

        $pingFile = __DIR__ . '/../../database/last_design_ping.txt';
        @file_put_contents($pingFile, time());

        $limit = requestInt($_GET, 'limit');
        $limit = $limit === null ? 3 : max(1, min($limit, 10));

        $jobs = [];
        foreach (QuestionDesign::getPendingJobs($limit) as $row) {
            $design = QuestionDesign::decode($row);
            $jobs[] = [
                'design_id' => (int)$design['id'],
                'revision' => (int)$design['revision'],
                'step' => $design['status'] === QuestionDesign::STATUS_ANALYSIS_PENDING ? 'analysis' : 'assessment',
                'question_text' => $design['question_text'],
                'model_answer' => $design['model_answer'],
                'analysis' => $design['analysis'],
                'teacher_answers' => $design['teacher_answers'] ?? [],
                'teacher_feedback' => (string)($design['teacher_feedback'] ?? ''),
                'previous_rubric' => $design['validation']['rubric'] ?? null,
            ];
        }

        // Alleen loggen als er werk is, anders loopt de audit log vol door het pollen.
        if (count($jobs) > 0) {
            AuditLog::log('api_design_jobs', [
                'api_key_id' => $this->apiKey['id'],
                'design_ids' => array_column($jobs, 'design_id'),
            ], 'API:' . $this->apiKey['name']);
        }
        echo json_encode(['jobs' => $jobs]);
    }

    /**
     * Ontvangt het resultaat (of de fout) van een ontwerpstap van de worker.
     * Een resultaat voor een oude revision of een andere stap wordt geweigerd (409),
     * zodat het nooit nieuwere invoer van de docent overschrijft.
     */
    public function submitDesignResult() {
        header('Content-Type: application/json');
        $this->verifyApiKey();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->jsonError(405, 'Method not allowed');
            return;
        }

        $raw = file_get_contents('php://input', false, null, 0, MAX_DESIGN_RESULT_LENGTH + 1);
        if ($raw === false || strlen($raw) > MAX_DESIGN_RESULT_LENGTH) {
            $this->jsonError(413, 'Body too large (max ' . MAX_DESIGN_RESULT_LENGTH . ' bytes)');
            return;
        }

        $input = json_decode($raw, true);
        $hasResult = is_array($input) && array_key_exists('result', $input);
        $hasError = is_array($input) && array_key_exists('error', $input);
        if (!is_array($input) || $hasResult === $hasError) {
            $this->jsonError(400, 'Expected design_id, revision, step and either result or error');
            return;
        }
        $designId = requestInt($input, 'design_id');
        $revision = requestInt($input, 'revision');
        $step = $input['step'] ?? null;
        if ($designId === null || $revision === null || !in_array($step, ['analysis', 'assessment'], true)) {
            $this->jsonError(400, 'Invalid design_id, revision or step');
            return;
        }

        $design = QuestionDesign::find($designId);
        if (!$design) {
            $this->jsonError(404, 'Unknown design_id');
            return;
        }
        $expectedStatus = $step === 'analysis'
            ? QuestionDesign::STATUS_ANALYSIS_PENDING
            : QuestionDesign::STATUS_ASSESSMENT_PENDING;
        if ($design['status'] !== $expectedStatus || (int)$design['revision'] !== $revision) {
            $this->jsonError(409, 'Stale result');
            return;
        }

        if ($hasError) {
            $message = QuestionDesign::cleanText($input['error']);
            if ($message === null || $message === '') {
                $this->jsonError(400, 'Invalid error message');
                return;
            }
            $saved = QuestionDesign::markFailed($designId, $revision, $expectedStatus, $message);
        } elseif ($step === 'analysis') {
            $analysis = QuestionDesign::normalizeAnalysis($input['result']);
            if ($analysis === null) {
                $this->jsonError(400, 'Invalid analysis');
                return;
            }
            $saved = QuestionDesign::saveAnalysis($designId, $revision, $analysis);
        } else {
            $result = $input['result'];
            $assessment = QuestionDesign::normalizeAssessment(is_array($result) ? ($result['assessment'] ?? null) : null);
            if ($assessment === null) {
                $this->jsonError(400, 'Invalid assessment');
                return;
            }
            $validation = QuestionDesign::normalizeValidation($result['validation'] ?? null);
            if ($validation === null) {
                $this->jsonError(400, 'Invalid validation');
                return;
            }
            $saved = QuestionDesign::saveAssessment($designId, $revision, $assessment, $validation);
        }

        // De status kan net door de docent zijn gewijzigd (tussen find en update).
        if (!$saved) {
            $this->jsonError(409, 'Stale result');
            return;
        }

        $nextStatus = QuestionDesign::find($designId)['status'];
        AuditLog::log('design_result_submit', [
            'design_id' => $designId,
            'revision' => $revision,
            'step' => $step,
            'next_status' => $nextStatus,
            'api_key_id' => $this->apiKey['id'],
        ], 'API:' . $this->apiKey['name']);
        echo json_encode(['status' => 'success', 'next_status' => $nextStatus]);
    }

    private function jsonError(int $code, string $message): void {
        http_response_code($code);
        echo json_encode(['error' => $message]);
    }
}
