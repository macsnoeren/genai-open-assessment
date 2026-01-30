<?php
// Dit script voegt kolommen toe voor docent-feedback aan de student_answers tabel.
// Run dit via de browser: /setup/update_student_answers_table.php

$databasePath = __DIR__ . '/../../database/database.sqlite';

try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Start update student_answers tabel...<br>";

    // Voeg kolommen toe. We gebruiken try-catch voor het geval ze al bestaan.
    try {
        $pdo->exec("ALTER TABLE student_answers ADD COLUMN teacher_score INTEGER");
        echo "Kolom 'teacher_score' toegevoegd.<br>";
    } catch (Exception $e) { echo "Kolom 'teacher_score' bestond waarschijnlijk al.<br>"; }

    try {
        $pdo->exec("ALTER TABLE student_answers ADD COLUMN teacher_feedback TEXT");
        echo "Kolom 'teacher_feedback' toegevoegd.<br>";
    } catch (Exception $e) { echo "Kolom 'teacher_feedback' bestond waarschijnlijk al.<br>"; }

    echo "✅ Succes! De tabel is bijgewerkt.<br>";
    echo "Verwijder dit bestand hierna uit veiligheidsoverwegingen.";

} catch (Exception $e) {
    echo "❌ Fout: " . htmlspecialchars($e->getMessage());
}
?>