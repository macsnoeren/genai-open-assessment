<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../../database/database.sqlite';

echo "🔧 Migratie gestart: Kolom prompt_id toevoegen aan exams...\n";

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
    if ($col['name'] === 'prompt_id') {
        die("ℹ️ Kolom 'prompt_id' bestaat al. Geen actie nodig.\n");
    }
}

try {
    $db->exec("ALTER TABLE exams ADD COLUMN prompt_id INTEGER REFERENCES prompts(id) ON DELETE SET NULL");
    echo "✅ Kolom 'prompt_id' succesvol toegevoegd.\n";
} catch (PDOException $e) {
    die("❌ Fout bij toevoegen kolom: " . $e->getMessage() . "\n");
}
?>