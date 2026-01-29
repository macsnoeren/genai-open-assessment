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
    
    // Admin mag alles wat een docent mag
    if ($requiredRole === 'docent' && $userRole === 'admin') {
        return;
    }
    
    // Admin mag alles wat een student mag (optioneel, maar voor de zekerheid)
    if ($requiredRole === 'student' && $userRole === 'admin') {
        return;
    }

    if ($userRole !== $requiredRole) {
        die('Geen toegang: onvoldoende rechten.');
    }
}
?>