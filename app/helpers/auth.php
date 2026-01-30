<?php

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