<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /?action=login');
        exit;
    }
}

function requireRole($requiredRole) {
    requireLogin();
    
    $userRole = $_SESSION['role'];
    
    // Admin mag alles
    if ($userRole === 'admin') {
        return;
    }
    
    // Docent mag alles wat een beoordelaar mag
    if ($requiredRole === 'beoordelaar' && $userRole === 'docent') {
        return;
    }

    if ($userRole !== $requiredRole) {
        die('Geen toegang: onvoldoende rechten.');
    }
}
?>