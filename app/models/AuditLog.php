<?php

require_once __DIR__ . '/../../config/database.php';

class AuditLog {
    /**
     * @param string $action
     * @param mixed $details array/object wordt als JSON opgeslagen
     * @param string|null $userName overschrijft de naam uit de sessie (bijv. e-mail bij mislukte login)
     */
    public static function log($action, $details = null, $userName = null) {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userName === null) {
            $userName = $_SESSION['name'] ?? 'System/API';
        }
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

    /**
     * Telt hoe vaak een actie recent is gelogd, optioneel gefilterd op IP en/of gebruikersnaam.
     * Gebruikt voor brute-force- en rate-limiting.
     */
    public static function countRecent(string $action, int $minutes, ?string $ip = null, ?string $userName = null): int {
        $pdo = Database::connect();
        $sql = "SELECT COUNT(*) FROM audit_log WHERE action = ? AND created_at >= datetime('now', ?)";
        $params = [$action, '-' . max(1, $minutes) . ' minutes'];
        if ($ip !== null) {
            $sql .= " AND ip_address = ?";
            $params[] = $ip;
        }
        if ($userName !== null) {
            $sql .= " AND user_name = ?";
            $params[] = $userName;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function all() {
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 500");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
