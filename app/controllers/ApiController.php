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
require_once __DIR__ . '/../models/AnswerAssessment.php';
require_once __DIR__ . '/../models/Questions.php';
require_once __DIR__ . '/../models/Integration.php';
require_once __DIR__ . '/../models/IntegrationAttempt.php';
require_once __DIR__ . '/../models/IntegrationEvent.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Grading.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Class ApiController
 * Handles external API requests (e.g., from the AI feedback service).
 */
class ApiController {

    /** Geverifieerde API-key (id, name, scope) van het huidige verzoek. */
    private $apiKey = null;

    /** De koppeling bij een integratiekey (zie requireIntegration()). */
    private $integration = null;

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
     * Controleert de API-key van het verzoek en de scope ervan.
     * Een ongeldige, onbekende of uitgeschakelde key geeft 401; een geldige key
     * met een andere scope 403. Zo kan een integratiekey nooit bij de
     * worker-endpoints (alle studentantwoorden) en een workerkey nooit bij de
     * integratie-endpoints.
     * @param string $scope ApiKey::SCOPE_WORKER of ApiKey::SCOPE_INTEGRATION
     */
    private function verifyApiKey(string $scope) {
        $key = $this->readApiKeyFromHeaders();
        $this->apiKey = ApiKey::findActiveByKey($key);

        if (!$this->apiKey) {
            AuditLog::log('api_auth_failed');
            http_response_code(401);
            header('WWW-Authenticate: Bearer');
            echo json_encode(['error' => 'Unauthorized: Invalid or missing API Key']);
            exit;
        }
        if (($this->apiKey['scope'] ?? ApiKey::SCOPE_WORKER) !== $scope) {
            AuditLog::log('api_scope_denied', [
                'api_key_id' => $this->apiKey['id'],
                'expected' => $scope,
                'action' => is_string($_GET['action'] ?? null) ? substr($_GET['action'], 0, 50) : '',
            ], 'API:' . $this->apiKey['name']);
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden for this key']);
            exit;
        }
    }

    /**
     * Retrieves open answers that need AI grading.
     */
    public function getOpenAnswers() {
        header('Content-Type: application/json');
        $this->verifyApiKey(ApiKey::SCOPE_WORKER);

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
        $this->respondThenDeliverWebhooks(json_encode(['answers' => $answers]));
    }

    /**
     * Receives AI feedback and updates the student answer.
     */
    public function submitAiFeedback() {
        header('Content-Type: application/json');
        $this->verifyApiKey(ApiKey::SCOPE_WORKER);

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
        $this->afterAiResult($answerId);
        echo json_encode(['status' => 'success']);
    }

    /**
     * Geeft de open vraagontwerpen aan de ontwerp-worker (bin/process_design_jobs.py).
     * Contract: zie ARCHITECTURE.md §6.2.
     */
    public function getOpenDesignJobs() {
        header('Content-Type: application/json');
        $this->verifyApiKey(ApiKey::SCOPE_WORKER);

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
                // points | levels (van de toets); een worker zonder dit veld gaat uit van points
                'grading_scale' => ($design['grading_scale'] ?? 'points') === 'levels' ? 'levels' : 'points',
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
        $this->verifyApiKey(ApiKey::SCOPE_WORKER);

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
            // De schaal komt uit de database (de toets van het ontwerp), nooit uit de body
            $scale = QuestionDesign::scaleForDesign($design);
            $assessment = QuestionDesign::normalizeAssessment(is_array($result) ? ($result['assessment'] ?? null) : null, $scale);
            if ($assessment === null) {
                $this->jsonError(400, 'Invalid assessment');
                return;
            }
            $validation = QuestionDesign::normalizeValidation($result['validation'] ?? null, $scale);
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

    /**
     * Geeft de open agentic beoordelingen aan de assessment-worker
     * (bin/process_assessment_jobs.py). Vraag, criteria en antwoord komen uit
     * de snapshot van de run. Contract: zie ARCHITECTURE.md §6.2.
     */
    public function getOpenAssessmentJobs() {
        header('Content-Type: application/json');
        $this->verifyApiKey(ApiKey::SCOPE_WORKER);

        $pingFile = __DIR__ . '/../../database/last_assessment_ping.txt';
        @file_put_contents($pingFile, time());

        $limit = requestInt($_GET, 'limit');
        $limit = $limit === null ? 3 : max(1, min($limit, 10));

        // Antwoorden die (inmiddels) aan de voorwaarden voldoen en nog geen run hebben,
        // bijvoorbeeld omdat AI-beoordeling later is aangezet of de criteria een rubric werden.
        $autoRuns = AnswerAssessment::createAutomaticRuns(null, ASSESSMENT_AUTO_START_BATCH);
        if ($autoRuns) {
            AuditLog::log('answer_assessment_auto_start', [
                'api_key_id' => $this->apiKey['id'],
                'runs' => $autoRuns,
            ], 'API:' . $this->apiKey['name']);
        }

        $jobs = [];
        foreach (AnswerAssessment::getPendingJobs($limit) as $row) {
            $jobs[] = [
                'assessment_id' => (int)$row['id'],
                'question_text' => $row['question_snapshot'],
                'criteria' => $row['criteria_snapshot'],
                'answer' => $row['answer_snapshot'],
                // points | levels; een worker zonder dit veld gaat uit van points
                'grading_scale' => $row['grading_scale'] === 'levels' ? 'levels' : 'points',
            ];
        }

        // Alleen loggen als er werk is, anders loopt de audit log vol door het pollen.
        if (count($jobs) > 0) {
            AuditLog::log('api_assessment_jobs', [
                'api_key_id' => $this->apiKey['id'],
                'assessment_ids' => array_column($jobs, 'assessment_id'),
            ], 'API:' . $this->apiKey['name']);
        }
        $this->respondThenDeliverWebhooks(json_encode(['jobs' => $jobs]));
    }

    /**
     * Ontvangt het resultaat (of de fout) van een agentic beoordeling. Alleen
     * een run met status pending wordt bijgewerkt; anders 409 (verouderd: de
     * docent heeft intussen opnieuw gestart).
     */
    public function submitAssessmentResult() {
        header('Content-Type: application/json');
        $this->verifyApiKey(ApiKey::SCOPE_WORKER);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->jsonError(405, 'Method not allowed');
            return;
        }

        $raw = file_get_contents('php://input', false, null, 0, MAX_ASSESSMENT_RESULT_LENGTH + 1);
        if ($raw === false || strlen($raw) > MAX_ASSESSMENT_RESULT_LENGTH) {
            $this->jsonError(413, 'Body too large (max ' . MAX_ASSESSMENT_RESULT_LENGTH . ' bytes)');
            return;
        }

        $input = json_decode($raw, true);
        $hasResult = is_array($input) && array_key_exists('result', $input);
        $hasError = is_array($input) && array_key_exists('error', $input);
        $assessmentId = is_array($input) ? requestInt($input, 'assessment_id') : null;
        if ($assessmentId === null || $hasResult === $hasError) {
            $this->jsonError(400, 'Expected assessment_id and either result or error');
            return;
        }

        $run = AnswerAssessment::find($assessmentId);
        if (!$run) {
            $this->jsonError(404, 'Unknown assessment_id');
            return;
        }
        if ($run['status'] !== AnswerAssessment::STATUS_PENDING) {
            $this->jsonError(409, 'Stale result');
            return;
        }

        if ($hasError) {
            $message = AnswerAssessment::cleanText($input['error']);
            if ($message === null || $message === '') {
                $this->jsonError(400, 'Invalid error message');
                return;
            }
            $saved = AnswerAssessment::markFailed($assessmentId, $message);
            $details = ['id' => $assessmentId, 'failed' => true];
        } else {
            // De schaal komt uit de database (de toets van de run), nooit uit de body
            $scale = AnswerAssessment::scaleForRun($run);
            $result = AnswerAssessment::normalizeResult($input['result'], $reason, $scale);
            if ($result === null) {
                $this->jsonError(400, $reason ?? 'Invalid result');
                return;
            }
            $saved = AnswerAssessment::saveResult($assessmentId, $result);
            $details = [
                'id' => $assessmentId,
                'human_review_needed' => $result['decision']['human_review_needed'],
            ];
            if ($scale === 'levels') {
                $details['final_level'] = $result['decision']['level'];
            } else {
                $details['final_score'] = $result['decision']['score'];
            }
        }

        // De run kan net door de docent zijn vervangen (tussen find en update).
        if (!$saved) {
            $this->jsonError(409, 'Stale result');
            return;
        }

        AuditLog::log('assessment_result_submit', $details + ['api_key_id' => $this->apiKey['id']],
            'API:' . $this->apiKey['name']);
        if (!$hasError) {
            $this->afterAiResult((int)$run['student_answer_id']);
        }
        echo json_encode(['status' => 'success']);
    }

    /**
     * Externe koppeling: is de poging van dit antwoord nu helemaal nagekeken,
     * dan komt attempt.graded in de outbox. Een fout hier mag het antwoord aan
     * de worker nooit breken (die zou het resultaat anders opnieuw insturen).
     */
    private function afterAiResult(int $studentAnswerId): void {
        try {
            IntegrationAttempt::checkGraded($studentAnswerId);
        } catch (Throwable $e) {
            error_log('Integratie-event na AI-resultaat mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Stuurt het antwoord aan de worker af en verstuurt daarna de webhooks die
     * aan de beurt zijn (B8: webhooks gaan tijdens de polls van de workers).
     * Met PHP-FPM sluit fastcgi_finish_request() het verzoek; onder mod_php
     * sluiten Content-Length en Connection: close het voor de client af. Een
     * webhookfout breekt een poll nooit.
     */
    private function respondThenDeliverWebhooks(string $json): void {
        if (function_exists('fastcgi_finish_request')) {
            echo $json;
            fastcgi_finish_request();
        } else {
            ignore_user_abort(true);
            if (!headers_sent()) {
                header('Connection: close');
                header('Content-Length: ' . strlen($json));
            }
            echo $json;
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        try {
            IntegrationEvent::deliverDue(INTEGRATION_WEBHOOK_BATCH);
        } catch (Throwable $e) {
            error_log('Webhooks versturen mislukt: ' . $e->getMessage());
        }
    }

    private function jsonError(int $code, string $message): void {
        http_response_code($code);
        echo json_encode(['error' => $message]);
    }

    private function jsonOut(int $code, array $data): void {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    // ---------------------------------------------------------------------
    // Integratie-API voor externe websites (contract 9, docs/integration-api.md)
    // ---------------------------------------------------------------------

    /**
     * Eist een geldige key met scope integration die bij een koppeling hoort.
     * Alle queries hierna filteren op $this->integration['id'].
     */
    private function requireIntegration(): array {
        $this->verifyApiKey(ApiKey::SCOPE_INTEGRATION);
        $this->integration = Integration::findByApiKeyId($this->apiKey['id']);
        if (!$this->integration) {
            AuditLog::log('api_auth_failed', ['api_key_id' => $this->apiKey['id'], 'reason' => 'no integration'],
                'API:' . $this->apiKey['name']);
            http_response_code(401);
            header('WWW-Authenticate: Bearer');
            echo json_encode(['error' => 'Unauthorized: Invalid or missing API Key']);
            exit;
        }
        return $this->integration;
    }

    /** Audit log voor de koppeling (gebruikersnaam API:<naam>, ook de bron voor de rate limit). */
    private function integrationLog(string $action, array $details): void {
        AuditLog::log($action, ['integration_id' => (int)$this->integration['id']] + $details,
            'API:' . $this->apiKey['name']);
    }

    /**
     * Leest een JSON-object uit de body van een POST.
     * Geeft null (en heeft dan al 405, 413 of 400 gestuurd) als dat niet lukt.
     */
    private function readJsonBody(int $max): ?array {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $this->jsonError(405, 'Method not allowed');
            return null;
        }
        $raw = file_get_contents('php://input', false, null, 0, $max + 1);
        if ($raw === false || strlen($raw) > $max) {
            $this->jsonError(413, 'Body too large (max ' . $max . ' bytes)');
            return null;
        }
        $input = json_decode($raw, true);
        if (!is_array($input) || array_is_list($input) && $input !== []) {
            $this->jsonError(400, 'Invalid JSON: expected an object');
            return null;
        }
        return $input;
    }

    /** GET-only endpoints: een ander verzoek geeft 405. */
    private function requireGet(): bool {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            $this->jsonError(405, 'Method not allowed');
            return false;
        }
        return true;
    }

    /** GET integration_exams: de gekoppelde toetsen die gestart kunnen worden (AI-beoordeling aan). */
    public function integrationExams() {
        header('Content-Type: application/json');
        $integration = $this->requireIntegration();
        if (!$this->requireGet()) {
            return;
        }
        $exams = [];
        foreach (Integration::startableExams($integration['id']) as $exam) {
            $exams[] = [
                'exam_id' => (int)$exam['id'],
                'title' => (string)$exam['title'],
                'question_count' => (int)$exam['question_count'],
                'grading_scale' => ($exam['grading_scale'] ?? 'points') === 'levels' ? 'levels' : 'points',
            ];
        }
        $this->jsonOut(200, ['exams' => $exams]);
    }

    /**
     * POST integration_attempt_start: start een poging (201) of geeft een nieuwe
     * startlink voor een bestaande, nog niet ingeleverde poging met dezelfde
     * external_ref (200). Zie B5 in TASKS.md en docs/integration-api.md.
     */
    public function integrationAttemptStart() {
        header('Content-Type: application/json');
        $integration = $this->requireIntegration();
        $input = $this->readJsonBody(MAX_INTEGRATION_BODY);
        if ($input === null) {
            return;
        }

        $examId = requestInt($input, 'exam_id');
        if ($examId === null || !(is_int($input['exam_id']) || is_string($input['exam_id']))) {
            $this->jsonError(400, 'Invalid exam_id');
            return;
        }
        $ref = $input['external_ref'] ?? null;
        if (!is_string($ref) || !preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $ref)) {
            $this->jsonError(400, 'Invalid external_ref (1-100 characters A-Z a-z 0-9 . _ : -)');
            return;
        }
        $displayName = $input['display_name'] ?? null;
        if ($displayName !== null && !is_string($displayName)) {
            $this->jsonError(400, 'Invalid display_name');
            return;
        }
        $displayName = trim((string)$displayName);
        if (mb_strlen($displayName) > MAX_NAME_LENGTH || preg_match('/[\x00-\x1f\x7f]/', $displayName)) {
            $this->jsonError(400, 'Invalid display_name (max ' . MAX_NAME_LENGTH . ' characters)');
            return;
        }
        if ($displayName === '') {
            $displayName = 'Deelnemer';
        }
        $returnUrl = $input['return_url'] ?? null;
        if (!is_string($returnUrl) || !Integration::allowsReturnUrl($integration, $returnUrl)) {
            $this->jsonError(400, 'Invalid return_url (must be on ' . $integration['return_origin'] . ')');
            return;
        }

        $exam = Integration::allowedExam($integration['id'], $examId);
        if (!$exam) {
            $this->jsonError(404, 'Unknown exam');
            return;
        }
        if (!Question::idsByExam($examId)) {
            $this->jsonError(400, 'Exam has no questions');
            return;
        }

        if (AuditLog::countRecent('integration_attempt_start', 60, null, 'API:' . $this->apiKey['name'])
                >= INTEGRATION_START_MAX_PER_HOUR) {
            $this->integrationLog('integration_rate_limited', ['endpoint' => 'integration_attempt_start']);
            header('Retry-After: 600');
            $this->jsonError(429, 'Too many attempts started, try again later');
            return;
        }

        $created = null;
        $existing = IntegrationAttempt::findByRef($integration['id'], $ref);
        if (!$existing) {
            $created = IntegrationAttempt::create($integration['id'], $examId, $ref, $displayName, $returnUrl);
            if ($created === null) {
                // Gelijktijdige start met dezelfde external_ref: verder als bestaande poging.
                $existing = IntegrationAttempt::findByRef($integration['id'], $ref);
            }
        }

        if ($created !== null) {
            $attemptId = $created['attempt_id'];
            $token = $created['token'];
            $expiresAt = $created['expires_at'];
            $status = IntegrationAttempt::STATUS_NOT_STARTED;
            $code = 201;
        } else {
            $attemptId = (int)$existing['student_exam_id'];
            if ((int)$existing['exam_id'] !== $examId) {
                $this->jsonOut(409, ['error' => 'external_ref is already used for another exam', 'attempt_id' => $attemptId]);
                return;
            }
            if (!empty($existing['completed_at'])) {
                $this->jsonOut(409, ['error' => 'Attempt already submitted', 'attempt_id' => $attemptId]);
                return;
            }
            $launch = IntegrationAttempt::newLaunchToken($attemptId);
            $token = $launch['token'];
            $expiresAt = $launch['expires_at'];
            $status = $existing['launch_used_at'] ? IntegrationAttempt::STATUS_IN_PROGRESS : IntegrationAttempt::STATUS_NOT_STARTED;
            $code = 200;
        }

        $this->integrationLog('integration_attempt_start', [
            'attempt_id' => $attemptId,
            'exam_id' => $examId,
            'external_ref' => $ref,
            'new' => $code === 201,
        ]);
        $this->jsonOut($code, [
            'attempt_id' => $attemptId,
            'launch_url' => appBaseUrl() . '/?action=integration_launch&token=' . $token,
            'expires_at' => $expiresAt,
            'status' => $status,
        ]);
    }

    /** GET integration_attempt&attempt_id=N: de samenvatting van één poging van deze koppeling. */
    public function integrationAttempt() {
        header('Content-Type: application/json');
        $integration = $this->requireIntegration();
        if (!$this->requireGet()) {
            return;
        }
        $attemptId = requestInt($_GET, 'attempt_id');
        // Een poging van een andere koppeling bestaat voor deze koppeling niet: 404, geen 403.
        $attempt = $attemptId !== null ? IntegrationAttempt::findForIntegration($integration['id'], $attemptId) : null;
        if (!$attempt) {
            $this->jsonError(404, 'Unknown attempt');
            return;
        }
        $summary = IntegrationAttempt::summary($attempt);
        // Alleen loggen als er resultaten worden gelezen, zodat pollen de log niet vult.
        if (in_array($summary['status'], [IntegrationAttempt::STATUS_GRADED, IntegrationAttempt::STATUS_REVIEWED], true)) {
            $this->integrationLog('integration_attempt_read', ['attempt_id' => $attemptId, 'status' => $summary['status']]);
        }
        $this->jsonOut(200, $summary);
    }

    /** GET integration_attempts&filter=open|needs_review|all&limit=1..100 */
    public function integrationAttempts() {
        header('Content-Type: application/json');
        $integration = $this->requireIntegration();
        if (!$this->requireGet()) {
            return;
        }
        $filter = $_GET['filter'] ?? 'open';
        if (!is_string($filter) || !in_array($filter, IntegrationAttempt::FILTERS, true)) {
            $this->jsonError(400, 'Invalid filter (open, needs_review or all)');
            return;
        }
        $limit = requestInt($_GET, 'limit');
        $limit = $limit === null ? 50 : max(1, min($limit, 100));
        $this->jsonOut(200, ['attempts' => IntegrationAttempt::listForIntegration($integration['id'], $filter, $limit)]);
    }

    /**
     * POST integration_attempt_review: de externe website meldt een menselijke
     * beoordeling terug. Met grades worden dat de docentscores (teacher_score
     * komt altijd van een mens; bij een toets met niveaus teacher_level, met
     * level in plaats van score); zonder grades wordt de poging alleen als
     * afgehandeld gemarkeerd. Alleen bij status graded of reviewed.
     */
    public function integrationAttemptReview() {
        header('Content-Type: application/json');
        $integration = $this->requireIntegration();
        $input = $this->readJsonBody(MAX_INTEGRATION_BODY);
        if ($input === null) {
            return;
        }

        $attemptId = requestInt($input, 'attempt_id');
        if ($attemptId === null || !(is_int($input['attempt_id']) || is_string($input['attempt_id']))) {
            $this->jsonError(400, 'Invalid attempt_id');
            return;
        }
        $attempt = IntegrationAttempt::findForIntegration($integration['id'], $attemptId);
        if (!$attempt) {
            $this->jsonError(404, 'Unknown attempt');
            return;
        }

        $reviewer = $input['reviewer'] ?? '';
        if (!is_string($reviewer)) {
            $this->jsonError(400, 'Invalid reviewer');
            return;
        }
        $reviewer = trim($reviewer);
        if (mb_strlen($reviewer) > MAX_NAME_LENGTH || preg_match('/[\x00-\x1f\x7f]/', $reviewer)) {
            $this->jsonError(400, 'Invalid reviewer (max ' . MAX_NAME_LENGTH . ' characters)');
            return;
        }

        $rawGrades = $input['grades'] ?? [];
        if (!is_array($rawGrades) || !array_is_list($rawGrades)) {
            $this->jsonError(400, 'Invalid grades (expected a list)');
            return;
        }
        $isLevels = Grading::examScale(Exam::find($attempt['exam_id'])) === Grading::SCALE_LEVELS;
        // Alleen antwoorden van DEZE poging, uit de database
        $answersByQuestion = [];
        foreach (StudentAnswer::allByStudentExam($attemptId) as $answer) {
            $answersByQuestion[(int)$answer['question_id']] = $answer;
        }
        $grades = [];
        foreach ($rawGrades as $i => $grade) {
            $questionId = is_array($grade) ? requestInt($grade, 'question_id') : null;
            $answer = $questionId !== null ? ($answersByQuestion[$questionId] ?? null) : null;
            if ($answer === null || !(is_int($grade['question_id']) || is_string($grade['question_id']))) {
                $this->jsonError(400, 'Invalid question_id in grades[' . $i . ']');
                return;
            }
            if (isset($grades[(int)$answer['id']])) {
                $this->jsonError(400, 'Duplicate question_id in grades[' . $i . ']');
                return;
            }
            // Toets met niveaus: level in plaats van score (de schaal komt uit de database)
            $score = null;
            $level = null;
            if ($isLevels) {
                $level = $grade['level'] ?? null;
                if (!Grading::isLevel($level)) {
                    $this->jsonError(400, 'Invalid level in grades[' . $i . '] (onvoldoende, voldoende, goed or uitstekend)');
                    return;
                }
            } else {
                $score = $grade['score'] ?? null;
                if (!is_int($score) || $score < 0 || $score > 10) {
                    $this->jsonError(400, 'Invalid score in grades[' . $i . '] (integer 0-10)');
                    return;
                }
            }
            $feedback = $grade['feedback'] ?? null;
            if ($feedback !== null && (!is_string($feedback) || mb_strlen($feedback) > 5000)) {
                $this->jsonError(400, 'Invalid feedback in grades[' . $i . '] (max 5000 characters)');
                return;
            }
            $grades[(int)$answer['id']] = [
                'score' => $score,
                'level' => $level,
                // Zonder feedback blijft de bestaande docentfeedback staan.
                'feedback' => $feedback ?? (string)($answer['teacher_feedback'] ?? ''),
                'old' => $answer,
            ];
        }

        $status = IntegrationAttempt::summary($attempt)['status'];
        if (!in_array($status, [IntegrationAttempt::STATUS_GRADED, IntegrationAttempt::STATUS_REVIEWED], true)) {
            $this->jsonOut(409, ['error' => 'Not graded yet', 'status' => $status]);
            return;
        }

        IntegrationAttempt::saveReview($attemptId, $grades, $isLevels);
        foreach ($grades as $answerId => $grade) {
            $old = $grade['old'];
            $this->integrationLog('teacher_grade', [
                'student_answer_id' => $answerId,
                'student_exam_id' => $attemptId,
                $isLevels ? 'teacher_level' : 'teacher_score' => $isLevels
                    ? ['old' => $old['teacher_level'] ?? null, 'new' => $grade['level']]
                    : ['old' => $old['teacher_score'], 'new' => $grade['score']],
                'teacher_feedback' => ['old' => (string)($old['teacher_feedback'] ?? ''), 'new' => $grade['feedback']],
                'source' => 'integration',
                'reviewer' => $reviewer,
            ]);
        }
        $this->integrationLog('integration_attempt_review', [
            'attempt_id' => $attemptId,
            'external_ref' => $attempt['external_ref'],
            'reviewer' => $reviewer,
            'grades' => count($grades),
        ]);
        try {
            IntegrationAttempt::checkReviewed($attemptId);
        } catch (Throwable $e) {
            error_log('Integratie-event attempt.reviewed mislukt: ' . $e->getMessage());
        }
        $this->jsonOut(200, ['status' => 'success']);
    }
}
