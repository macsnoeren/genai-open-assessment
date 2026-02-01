<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

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
        
        Prompt::create(
            $_POST['title'],
            $_POST['description'],
            $_POST['prompt_text']
        );

        AuditLog::log('prompt_create', ['title' => $_POST['title']]);
        header('Location: /?action=prompts');
        exit;
    }

    public function edit() {
        requireRole('admin');
        
        $prompt = Prompt::find($_GET['id']);
        if (!$prompt) {
            die("Prompt niet gevonden.");
        }

        $action = 'prompt_update';
        $title = 'Prompt bewerken';
        require __DIR__ . '/../views/docent/prompt_form.php';
    }

    public function update() {
        validateCsrfToken();
        requireRole('admin');
        
        Prompt::update(
            $_POST['id'],
            $_POST['title'],
            $_POST['description'],
            $_POST['prompt_text']
        );

        AuditLog::log('prompt_update', ['id' => $_POST['id'], 'title' => $_POST['title']]);
        header('Location: /?action=prompts');
        exit;
    }

    public function delete() {
        validateCsrfToken();
        requireRole('admin');
        
        AuditLog::log('prompt_delete', ['id' => $_GET['id']]);
        Prompt::delete($_GET['id']);
        
        header('Location: /?action=prompts');
        exit;
    }

    public function help() {
        requireRole('admin');
        require __DIR__ . '/../views/docent/prompt_help.php';
    }
}
?>