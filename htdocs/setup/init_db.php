<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../../database/database.sqlite';
$schemaPath   = __DIR__ . '/schema.sql';

echo "🔧 Database initialisatie gestart...\n";

// 1. Controleer of storage map bestaat
$storageDir = dirname($databasePath);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
    echo "📁 Storage map aangemaakt\n";
}

// 2. Maak verbinding met SQLite
try {
    $db = new PDO('sqlite:' . $databasePath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Verbonden met database\n";
} catch (PDOException $e) {
    die("❌ Database connectie mislukt: " . $e->getMessage());
}

// 3. Lees SQL schema
if (!file_exists($schemaPath)) {
    die("❌ schema.sql niet gevonden");
}

$schema = file_get_contents($schemaPath);

// 4. Voer schema uit
try {
    $db->exec($schema);
    echo "✅ Database schema succesvol aangemaakt\n";
} catch (PDOException $e) {
    die("❌ Fout bij uitvoeren schema: " . $e->getMessage());
}

echo "🎉 Database setup voltooid!\n";
?>