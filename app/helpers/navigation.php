<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Navigatie van de layout. Het menu staat op één plek: navItems().
 * De layout bouwt de zijbalk (docent, admin, beoordelaar) en de topnavigatie
 * (student) uit deze array; een nieuwe rol of een nieuw menu-item past hier.
 */

/** De action van het huidige verzoek; zonder action is dat de landingspagina. */
function currentAction(): string {
    $action = requestString($_GET, 'action', 64);
    return $action === '' ? 'home' : $action;
}

/**
 * Alle menu-items (alleen data). Per item: group (kopje in de zijbalk; null voor de
 * student), label, action, icon (Bootstrap Icons), roles, also (onderliggende actions
 * die hetzelfde item actief maken) en optioneel counter (naam voor navCounter()).
 */
function navItems(): array {
    return [
        [
            'group' => 'Toetsen', 'label' => 'Dashboard', 'action' => 'docent_dashboard', 'icon' => 'bi-grid',
            'roles' => ['docent', 'admin'],
            'also' => ['exam_create', 'exam_edit', 'questions', 'question_create', 'question_edit',
                       'question_design_create', 'question_design_view', 'exam_results', 'exam_comparison',
                       'view_student_answers', 'answer_assessment_view'],
        ],
        [
            'group' => 'Toetsen', 'label' => 'Beoordelen', 'action' => 'pending_assessments', 'icon' => 'bi-check2-square',
            'roles' => ['docent', 'admin', 'beoordelaar'],
            'also' => ['grade_student_exam'],
            'counter' => 'pending',
        ],
        [
            'group' => 'Toetsen', 'label' => 'Mijn testpogingen', 'action' => 'my_exams', 'icon' => 'bi-play-circle',
            'roles' => ['docent', 'admin'],
            'also' => ['student_view_results'],
        ],
        [
            'group' => 'Inrichting', 'label' => 'Puntenschema\'s', 'action' => 'grading_schemes', 'icon' => 'bi-sliders',
            'roles' => ['docent', 'admin'],
            'also' => ['create_grading_scheme', 'edit_grading_scheme'],
        ],
        [
            'group' => 'Inrichting', 'label' => 'Prompts', 'action' => 'prompts', 'icon' => 'bi-chat-left-text',
            'roles' => ['admin'],
            'also' => ['prompt_create', 'prompt_edit', 'prompt_help'],
        ],
        [
            'group' => 'Beheer', 'label' => 'Gebruikers', 'action' => 'students', 'icon' => 'bi-people',
            'roles' => ['admin'],
            'also' => ['student_create'],
        ],
        [
            'group' => 'Beheer', 'label' => 'Koppelingen', 'action' => 'integrations', 'icon' => 'bi-plug',
            'roles' => ['admin'],
            'also' => ['integration_create', 'integration_edit', 'integration_view'],
        ],
        [
            'group' => 'Beheer', 'label' => 'API-keys', 'action' => 'api_keys', 'icon' => 'bi-key',
            'roles' => ['admin'],
            'also' => [],
        ],
        [
            'group' => 'Beheer', 'label' => 'Audit log', 'action' => 'audit_log', 'icon' => 'bi-journal-text',
            'roles' => ['docent', 'admin'],
            'also' => [],
        ],
        [
            'group' => null, 'label' => 'Dashboard', 'action' => 'student_dashboard', 'icon' => 'bi-house',
            'roles' => ['student'],
            'also' => ['exams_list'],
        ],
        [
            'group' => null, 'label' => 'Mijn toetsen', 'action' => 'my_exams', 'icon' => 'bi-journal-check',
            'roles' => ['student'],
            'also' => ['student_view_results'],
        ],
    ];
}

/**
 * De menu-items van een rol, gegroepeerd op group (volgorde behouden):
 * ['Toetsen' => [item, ...], ...]. Items zonder group staan onder de sleutel ''.
 */
function navItemsForRole(string $role): array {
    $groups = [];
    foreach (navItems() as $item) {
        if (!in_array($role, $item['roles'], true)) {
            continue;
        }
        $groups[$item['group'] ?? ''][] = $item;
    }
    return $groups;
}

/** Waar als het item hoort bij de huidige action (zelf of een onderliggende pagina). */
function navIsActive(array $item, string $current): bool {
    return $current === $item['action'] || in_array($current, $item['also'] ?? [], true);
}

/** Initialen voor het gebruikersmenu: eerste letter van het eerste en het laatste woord. */
function userInitials(string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return '?';
    }
    $initials = mb_substr($words[0], 0, 1);
    if (count($words) > 1) {
        $initials .= mb_substr($words[count($words) - 1], 0, 1);
    }
    return mb_strtoupper($initials);
}

/**
 * Status van de AI-worker: 'active' als de worker de afgelopen 120 seconden de API
 * heeft aangeroepen (ApiController schrijft database/last_api_ping.txt), anders 'inactive'.
 */
function workerStatus(): string {
    $pingFile = __DIR__ . '/../../database/last_api_ping.txt';
    if (file_exists($pingFile) && is_readable($pingFile)) {
        $lastPing = file_get_contents($pingFile);
        if ($lastPing !== false && is_numeric($lastPing) && (time() - (int)$lastPing) < 120) {
            return 'active';
        }
    }
    return 'inactive';
}

