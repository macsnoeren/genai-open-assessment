<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../../database/database.sqlite';

echo "🔧 Migratie gestart: Kolom ai_grading_enabled toevoegen aan exams...\n";

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
$columns = $db->query("PRAGMA table_info(exams)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    if ($col['name'] === 'ai_grading_enabled') {
        die("ℹ️ Kolom 'ai_grading_enabled' bestaat al. Geen actie nodig.\n");
    }
}

try {
    $db->exec("ALTER TABLE exams ADD COLUMN ai_grading_enabled INTEGER DEFAULT 0");
    echo "✅ Kolom 'ai_grading_enabled' succesvol toegevoegd.\n";
} catch (PDOException $e) {
    die("❌ Fout bij toevoegen kolom: " . $e->getMessage() . "\n");
}
?>