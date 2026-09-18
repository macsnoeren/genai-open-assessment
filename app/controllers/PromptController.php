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
require_once __DIR__ . '/../models/Prompt.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../helpers/auth.php';

class PromptController {

    public function index() {
        requireRole('admin');
        $prompts = Prompt::all();
        require __DIR__ . '/../views/docent/prompts.php';
    }

    public function create() {
        requireRole('admin');
        $prompt = null;
        $action = 'prompt_store';
        $title = 'Nieuwe prompt';
        require __DIR__ . '/../views/docent/prompt_form.php';
    }

    public function store() {
        validateCsrfToken();
        requireRole('admin');

        $titleText = trim(requestString($_POST, 'title', 255));
        $promptText = requestString($_POST, 'prompt_text');
        if ($titleText === '' || trim($promptText) === '') {
            abort(400, 'Titel en prompttekst zijn verplicht.');
        }
        
        Prompt::create(
            $titleText,
            requestString($_POST, 'description'),
            $promptText
        );

        AuditLog::log('prompt_create', ['title' => $titleText]);
        header('Location: /?action=prompts');
        exit;
    }

    public function edit() {
        requireRole('admin');
        
        $id = requestInt($_GET, 'id');
        $prompt = $id !== null ? Prompt::find($id) : null;
        if (!$prompt) {
            abort(404, 'Prompt niet gevonden.');
        }

        $action = 'prompt_update';
        $title = 'Prompt bewerken';
        require __DIR__ . '/../views/docent/prompt_form.php';
    }

    public function update() {
        validateCsrfToken();
        requireRole('admin');

        $id = requestInt($_POST, 'id');
        if ($id === null || !Prompt::find($id)) {
            abort(404, 'Prompt niet gevonden.');
        }
        $titleText = trim(requestString($_POST, 'title', 255));
        $promptText = requestString($_POST, 'prompt_text');
        if ($titleText === '' || trim($promptText) === '') {
            abort(400, 'Titel en prompttekst zijn verplicht.');
        }
        
        Prompt::update(
            $id,
            $titleText,
            requestString($_POST, 'description'),
            $promptText
        );

        AuditLog::log('prompt_update', ['id' => $id, 'title' => $titleText]);
        header('Location: /?action=prompts');
        exit;
    }

    public function delete() {
        validateCsrfToken();
        requireRole('admin');

        $id = requestInt($_GET, 'id') ?? requestInt($_POST, 'id');
        if ($id === null) {
            abort(400, 'Ongeldig verzoek.');
        }
        AuditLog::log('prompt_delete', ['id' => $id]);
        Prompt::delete($id);
        
        header('Location: /?action=prompts');
        exit;
    }

    public function help() {
        requireRole('admin');
        require __DIR__ . '/../views/docent/prompt_help.php';
    }
}
