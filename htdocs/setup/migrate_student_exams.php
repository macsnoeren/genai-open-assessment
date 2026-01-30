<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../../database/database.sqlite';

echo "🔧 Migratie gestart: student_exams tabel bijwerken...\n";

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

// 1. Controleer of de kolom 'guest_name' al bestaat
$columns = $db->query("PRAGMA table_info(student_exams)")->fetchAll(PDO::FETCH_ASSOC);
$hasGuestColumn = false;
foreach ($columns as $col) {
    if ($col['name'] === 'guest_name') {
        $hasGuestColumn = true;
        break;
    }
}

if ($hasGuestColumn) {
    echo "ℹ️ Tabel 'student_exams' is al bijgewerkt. Geen actie nodig.\n";
    exit;
}

echo "⚠️ Tabel 'student_exams' is verouderd. Wordt nu opnieuw opgebouwd...\n";

try {
    // Foreign keys uitzetten om problemen bij tabel swap te voorkomen
    $db->exec("PRAGMA foreign_keys = OFF");

    $db->beginTransaction();

    // 1. Hernoem oude tabel
    $db->exec("ALTER TABLE student_exams RENAME TO student_exams_old");

    // 2. Maak nieuwe tabel aan (met guest_name, access_token en nullable student_id)
    $db->exec("
        CREATE TABLE student_exams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER, -- Mag NULL zijn voor gasten
            guest_name TEXT,    -- Naam van de gaststudent
            exam_id INTEGER NOT NULL,
            unique_id TEXT NOT NULL,
            access_token TEXT UNIQUE, -- Token voor de cookie om sessie te herstellen
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE
        )
    ");

    // 3. Kopieer data
    // We kopiëren alleen de kolommen die in de oude tabel bestonden.
    // guest_name en access_token blijven NULL voor bestaande records.
    $db->exec("
        INSERT INTO student_exams (id, student_id, exam_id, unique_id, started_at, completed_at)
        SELECT id, student_id, exam_id, unique_id, started_at, completed_at
        FROM student_exams_old
    ");

    // 4. Verwijder oude tabel
    $db->exec("DROP TABLE student_exams_old");

    $db->commit();
    
    // Foreign keys weer aanzetten
    $db->exec("PRAGMA foreign_keys = ON");

    echo "✅ Tabel 'student_exams' succesvol gemigreerd.\n";

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("❌ Fout bij migratie: " . $e->getMessage() . "\n");
}
?>