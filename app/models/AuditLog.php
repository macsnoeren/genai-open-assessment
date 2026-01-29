<?php

require_once __DIR__ . '/../../config/database.php';

class AuditLog {
    public static function log($action, $details = null) {
        $userId = $_SESSION['user_id'] ?? null;
        $userName = $_SESSION['name'] ?? 'System/API';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        if (is_array($details) || is_object($details)) {
            $details = json_encode($details);
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            "INSERT INTO audit_log (user_id, user_name, action, details, ip_address) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $userName, $action, $details, $ipAddress]);
    }

    public static function all() {
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 500");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>