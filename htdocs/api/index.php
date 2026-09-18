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
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/controllers/ApiController.php';

registerErrorHandling(true);
sendSecurityHeaders(false);
header('Content-Type: application/json');

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
