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
require_once __DIR__ . '/../../config/database.php';

/**
 * Vingerafdruk van de wachtwoordhash, bewaard in de sessie. Verandert het
 * wachtwoord (door de gebruiker zelf of een admin), dan klopt hij niet meer
 * en eindigen de andere sessies van die gebruiker.
 */
function passwordMarker(string $passwordHash): string {
    return substr(hash('sha256', $passwordHash), 0, 32);
}

/** Zet de sessie na een geslaagde login of wachtwoordwijziging (na session_regenerate_id). */
function setSessionUser(array $user): void {
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['force_password_change'] = (int)($user['force_password_change'] ?? 0);
    $_SESSION['pw_marker'] = passwordMarker((string)$user['password']);
    $_SESSION['last_activity'] = time();
}

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

    // Rol, naam en wachtwoordstatus komen bij elk verzoek uit de database: een
    // verwijderde gebruiker is direct uitgelogd, een rolwijziging geldt direct en
    // een nieuw wachtwoord beëindigt de andere sessies.
    $stmt = Database::connect()->prepare("SELECT id, name, role, password, force_password_change FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $marker = $user ? passwordMarker((string)$user['password']) : '';
    if (!isset($_SESSION['pw_marker']) && $user) {
        $_SESSION['pw_marker'] = $marker; // sessie van vóór deze controle
    }
    if (!$user || !hash_equals((string)$_SESSION['pw_marker'], $marker)) {
        destroySession();
        session_start();
        $_SESSION['error'] = 'Je sessie is beëindigd. Log opnieuw in.';
        header('Location: /?action=login');
        exit;
    }
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['force_password_change'] = (int)$user['force_password_change'];

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
