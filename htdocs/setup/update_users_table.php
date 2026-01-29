<?php
// Dit script update de users tabel om de 'admin' rol toe te staan.
// Run dit via de browser: /setup/update_users_table.php

$databasePath = __DIR__ . '/../../database/database.sqlite';

try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Start migratie...<br>";

    $pdo->exec("PRAGMA foreign_keys=OFF");
    $pdo->beginTransaction();

    // 1. Nieuwe tabel aanmaken met de juiste CHECK constraint
    $pdo->exec("CREATE TABLE IF NOT EXISTS users_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT CHECK(role IN ('student', 'docent', 'admin')) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Data kopiëren
    $pdo->exec("INSERT INTO users_new SELECT id, name, email, password, role, created_at, updated_at FROM users");

    // 3. Oude tabel verwijderen en nieuwe hernoemen
    $pdo->exec("DROP TABLE users");
    $pdo->exec("ALTER TABLE users_new RENAME TO users");

    $pdo->commit();
    $pdo->exec("PRAGMA foreign_keys=ON");

    echo "✅ Succes! De users tabel is bijgewerkt en ondersteunt nu de rol 'admin'.<br>";
    echo "Verwijder dit bestand hierna uit veiligheidsoverwegingen.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Fout tijdens migratie: " . htmlspecialchars($e->getMessage());
}
?>