<?php

class Database {
    private static $pdo;

    public static function connect() {
        if (!isset(self::$pdo)) {
            // Configuration should ideally come from environment variables
            $host = 'localhost';
            $db   = 'genai_assessment';
            $user = 'root';
            $pass = '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }
        return self::$pdo;
    }
}