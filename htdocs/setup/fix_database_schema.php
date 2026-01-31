<?php
// Dit script repareert de database structuur als er kolommen ontbreken.
// Plaats dit in htdocs/setup/ en voer het uit via de browser of CLI.

error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/../../database/database.sqlite';
if (!file_exists($dbPath)) {
    die("Database bestand niet gevonden op: $dbPath");
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connectie fout: " . $e->getMessage());
}

echo "<h1>Database Schema Reparatie</h1><pre>";

// 1. Prompts tabel aanmaken indien nodig
echo "--- Prompts Tabel ---\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS prompts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        prompt_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");
echo "✅ Prompts tabel gecontroleerd.\n";

// 2. Exams tabel kolommen controleren
echo "\n--- Exams Tabel ---\n";
$columns = $pdo->query("PRAGMA table_info(exams)")->fetchAll(PDO::FETCH_ASSOC);
$cols = array_column($columns, 'name');

// Check public_token
if (!in_array('public_token', $cols)) {
    echo "➕ public_token toevoegen...\n";
    $pdo->exec("ALTER TABLE exams ADD COLUMN public_token TEXT UNIQUE");
    // Genereer tokens voor bestaande examens
    $exams = $pdo->query("SELECT id FROM exams")->fetchAll(PDO::FETCH_ASSOC);
    foreach($exams as $exam) {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE exams SET public_token = ? WHERE id = ?")->execute([$token, $exam['id']]);
    }
    echo "✅ public_token toegevoegd en gegenereerd.\n";
} else {
    echo "✅ public_token bestaat al.\n";
}

// Check prompt_id (Dit lost jouw specifieke fout op)
if (!in_array('prompt_id', $cols)) {
    echo "➕ prompt_id toevoegen...\n";
    $pdo->exec("ALTER TABLE exams ADD COLUMN prompt_id INTEGER REFERENCES prompts(id) ON DELETE SET NULL");
    echo "✅ prompt_id toegevoegd.\n";
} else {
    echo "✅ prompt_id bestaat al.\n";
}

echo "\n🏁 Klaar! Je kunt nu de pagina verversen en de toets aanmaken.</pre>";
?>