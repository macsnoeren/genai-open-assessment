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
require_once __DIR__ . '/../models/Grading.php';
require_once __DIR__ . '/../models/GradingScheme.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../helpers/auth.php';

/**
 * Beheer van puntenschema's. Elke docent kan elk schema kiezen en eigen schema's
 * maken; alleen de maker (en de admin) wijzigt of verwijdert een schema.
 */
class GradingSchemeController {

    public function index() {
        requireRole('docent');
        $schemes = GradingScheme::all();
        require __DIR__ . '/../views/docent/grading_schemes.php';
    }

    public function create() {
        requireRole('docent');
        $this->showForm(null, 'store_grading_scheme', 'Nieuw puntenschema');
    }

    public function store() {
        validateCsrfToken();
        requireRole('docent');

        [$name, $v, $g, $u] = $this->readInput();
        $error = Grading::validateScheme($name, $v, $g, $u);
        if ($error === null && ($existing = GradingScheme::findByPoints($v, $g, $u))) {
            $error = 'Dit puntenschema bestaat al: ' . $existing['name'] . '.';
        }
        if ($error !== null) {
            $this->showForm(null, 'store_grading_scheme', 'Nieuw puntenschema', $error, $this->postedValues());
            return;
        }

        $id = GradingScheme::create($name, $v, $g, $u, (int)$_SESSION['user_id']);
        AuditLog::log('grading_scheme_create', [
            'id' => $id,
            'name' => $name,
            'points' => [$v, $g, $u],
        ]);
        $_SESSION['success_message'] = 'Puntenschema "' . $name . '" is aangemaakt.';
        header('Location: /?action=grading_schemes');
        exit;
    }

    public function edit() {
        requireRole('docent');

        $scheme = $this->findManageable(requestInt($_GET, 'id'));
        if (GradingScheme::isLocked((int)$scheme['id'])) {
            $_SESSION['error'] = self::LOCKED_MESSAGE;
            header('Location: /?action=grading_schemes');
            exit;
        }
        $this->showForm($scheme, 'update_grading_scheme', 'Puntenschema wijzigen');
    }

    public function update() {
        validateCsrfToken();
        requireRole('docent');

        $scheme = $this->findManageable(requestInt($_POST, 'id'));
        $id = (int)$scheme['id'];
        if (GradingScheme::isLocked($id)) {
            $_SESSION['error'] = self::LOCKED_MESSAGE;
            header('Location: /?action=grading_schemes');
            exit;
        }

        [$name, $v, $g, $u] = $this->readInput();
        $error = Grading::validateScheme($name, $v, $g, $u);
        if ($error === null && ($existing = GradingScheme::findByPoints($v, $g, $u)) && (int)$existing['id'] !== $id) {
            $error = 'Dit puntenschema bestaat al: ' . $existing['name'] . '.';
        }
        if ($error !== null) {
            $this->showForm($scheme, 'update_grading_scheme', 'Puntenschema wijzigen', $error, $this->postedValues());
            return;
        }

        GradingScheme::update($id, $name, $v, $g, $u);
        AuditLog::log('grading_scheme_update', [
            'id' => $id,
            'old' => [
                'name' => $scheme['name'],
                'points' => [(int)$scheme['points_voldoende'], (int)$scheme['points_goed'], (int)$scheme['points_uitstekend']],
            ],
            'new' => ['name' => $name, 'points' => [$v, $g, $u]],
        ]);
        $_SESSION['success_message'] = 'Puntenschema "' . $name . '" is gewijzigd.';
        header('Location: /?action=grading_schemes');
        exit;
    }

    public function delete() {
        validateCsrfToken();
        requireRole('docent');

        $scheme = $this->findManageable(requestInt($_GET, 'id') ?? requestInt($_POST, 'id'));
        $id = (int)$scheme['id'];
        $usage = GradingScheme::usageCount($id);
        if ($usage > 0) {
            $_SESSION['error'] = 'Dit puntenschema wordt gebruikt door ' . $usage . ($usage === 1 ? ' toets' : ' toetsen')
                . ' en kan niet worden verwijderd.';
            header('Location: /?action=grading_schemes');
            exit;
        }

        GradingScheme::delete($id);
        AuditLog::log('grading_scheme_delete', [
            'id' => $id,
            'name' => $scheme['name'],
            'points' => [(int)$scheme['points_voldoende'], (int)$scheme['points_goed'], (int)$scheme['points_uitstekend']],
        ]);
        $_SESSION['success_message'] = 'Puntenschema "' . $scheme['name'] . '" is verwijderd.';
        header('Location: /?action=grading_schemes');
        exit;
    }

    private const LOCKED_MESSAGE = 'Dit schema wordt gebruikt door een toets met resultaten. Maak een nieuw schema.';

    /** Schema dat de huidige gebruiker mag beheren; anders 404 of 403. */
    private function findManageable(?int $id): array {
        $scheme = $id !== null ? GradingScheme::find($id) : null;
        if (!$scheme) {
            abort(404, 'Puntenschema niet gevonden.');
        }
        if (!GradingScheme::canManage($scheme)) {
            abort(403, 'Geen toegang: alleen de maker van dit puntenschema mag het wijzigen.');
        }
        return $scheme;
    }

    /** Naam en punten uit het formulier; een ongeldig getal wordt null (validateScheme() meldt dat). */
    private function readInput(): array {
        return [
            trim(requestString($_POST, 'name', 255)),
            requestInt($_POST, 'points_voldoende'),
            requestInt($_POST, 'points_goed'),
            requestInt($_POST, 'points_uitstekend'),
        ];
    }

    /** Ingevulde waarden om het formulier na een fout opnieuw te tonen. */
    private function postedValues(): array {
        return [
            'name' => requestString($_POST, 'name', 255),
            'points_voldoende' => requestString($_POST, 'points_voldoende', 10),
            'points_goed' => requestString($_POST, 'points_goed', 10),
            'points_uitstekend' => requestString($_POST, 'points_uitstekend', 10),
        ];
    }

    private function showForm(?array $scheme, string $action, string $title, ?string $formError = null, ?array $values = null): void {
        $values = $values ?? [
            'name' => $scheme['name'] ?? '',
            'points_voldoende' => $scheme['points_voldoende'] ?? '',
            'points_goed' => $scheme['points_goed'] ?? '',
            'points_uitstekend' => $scheme['points_uitstekend'] ?? '',
        ];
        if ($formError !== null) {
            http_response_code(400);
        }
        require __DIR__ . '/../views/docent/grading_scheme_form.php';
    }
}
