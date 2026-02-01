<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../../database/database.sqlite';

echo "🔧 Migratie gestart: Kolom force_password_change toevoegen aan users...\n";

if (!file_exists($databasePath)) {
    die("❌ Database niet gevonden op: $databasePath\n");
}

try {
    $db = new PDO('sqlite:' . $databasePath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Verbonden met database\n";
} catch (PDOException $e) {
    die("❌ Database connectie mislukt: " . $e->getMessage());
}

// Controleer of kolom al bestaat
$columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    if ($col['name'] === 'force_password_change') {
        die("ℹ️ Kolom 'force_password_change' bestaat al. Geen actie nodig.\n");
    }
}

try {
    $db->exec("ALTER TABLE users ADD COLUMN force_password_change INTEGER DEFAULT 0");
    echo "✅ Kolom 'force_password_change' succesvol toegevoegd.\n";
} catch (PDOException $e) {
    die("❌ Fout bij toevoegen kolom: " . $e->getMessage() . "\n");
}
?>