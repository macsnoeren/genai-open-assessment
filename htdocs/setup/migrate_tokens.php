<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../../database/database.sqlite';

echo "🔧 Migratie gestart: Publieke tokens genereren...\n";

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

// 1. Controleer of de kolom bestaat
$columns = $db->query("PRAGMA table_info(exams)")->fetchAll(PDO::FETCH_ASSOC);
$hasTokenColumn = false;
foreach ($columns as $col) {
    if ($col['name'] === 'public_token') {
        $hasTokenColumn = true;
        break;
    }
}

if (!$hasTokenColumn) {
    echo "⚠️ Kolom 'public_token' ontbreekt. Wordt nu toegevoegd...\n";
    try {
        $db->exec("ALTER TABLE exams ADD COLUMN public_token TEXT");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_exams_public_token ON exams(public_token)");
        echo "✅ Kolom 'public_token' succesvol toegevoegd.\n";
    } catch (PDOException $e) {
        die("❌ Fout bij toevoegen kolom: " . $e->getMessage() . "\n");
    }
}

// 2. Haal exams op zonder token
$stmt = $db->query("SELECT id, title FROM exams WHERE public_token IS NULL OR public_token = ''");
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($exams)) {
    echo "ℹ️ Alle examens hebben al een token. Geen actie nodig.\n";
    exit;
}

echo "🔄 " . count($exams) . " examens gevonden om bij te werken.\n";

$updateStmt = $db->prepare("UPDATE exams SET public_token = ? WHERE id = ?");

$count = 0;
foreach ($exams as $exam) {
    // Genereer token (zelfde logica als in Exam model)
    $token = bin2hex(random_bytes(16));
    
    try {
        $updateStmt->execute([$token, $exam['id']]);
        echo "   - Token gegenereerd voor: " . $exam['title'] . " (ID: " . $exam['id'] . ")\n";
        $count++;
    } catch (PDOException $e) {
        echo "   ❌ Fout bij updaten examen ID " . $exam['id'] . ": " . $e->getMessage() . "\n";
    }
}

echo "✅ Migratie voltooid! $count tokens gegenereerd.\n";
?>