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

/** Vernietigt de sessie volledig, inclusief de sessiecookie. */
function destroySession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /?action=login');
        exit;
    }

    // Inactiviteits-timeout
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
        destroySession();
        session_start();
        $_SESSION['error'] = 'Je sessie is verlopen door inactiviteit. Log opnieuw in.';
        header('Location: /?action=login');
        exit;
    }
    $_SESSION['last_activity'] = $now;

    // Dwing wachtwoordwijziging af indien nodig
    if (!empty($_SESSION['force_password_change'])) {
        $action = $_GET['action'] ?? '';
        if ($action !== 'change_password' && $action !== 'do_change_password' && $action !== 'logout') {
            header('Location: /?action=change_password');
            exit;
        }
    }
}

function requireRole($requiredRole) {
    requireLogin();
    
    $userRole = $_SESSION['role'] ?? '';
    
    // Admin mag alles
    if ($userRole === 'admin') {
        return;
    }
    
    // Docent mag alles wat een beoordelaar mag
    if ($requiredRole === 'beoordelaar' && $userRole === 'docent') {
        return;
    }

    if ($userRole !== $requiredRole) {
        abort(403, 'Geen toegang: onvoldoende rechten.');
    }
}

/** Geldige gebruikersrollen (moet overeenkomen met de CHECK-constraint in het schema). */
function validRoles(): array {
    return ['student', 'docent', 'admin', 'beoordelaar'];
}

/**
 * Controleert het wachtwoordbeleid.
 * @return string|null foutmelding, of null als het wachtwoord voldoet.
 */
function validatePasswordPolicy(string $password): ?string {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Het wachtwoord moet minimaal ' . PASSWORD_MIN_LENGTH . ' tekens lang zijn.';
    }
    if (strlen($password) > 1024) {
        return 'Het wachtwoord is te lang.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Het wachtwoord moet minimaal één letter en één cijfer bevatten.';
    }
    return null;
}

/** Valideert een e-mailadres; geeft de genormaliseerde waarde of null terug. */
function normalizeEmail(string $email): ?string {
    $email = trim($email);
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return strtolower($email);
}
