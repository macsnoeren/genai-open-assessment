<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start();
?>

<form method="POST" action="/?action=student_update">
    <input type="hidden" name="id" value="<?= $student['id'] ?>">

    <label>Naam</label>
        <input type="text" name="name" value="<?= htmlspecialchars($student['name']) ?>" required>

    <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>

    <label>Nieuw wachtwoord (optioneel)</label>
        <input type="password" name="password" placeholder="Laat leeg als niet wijzigen">

    <button type="submit">Opslaan</button>
    </form>

<?php
$content = ob_get_clean();
$title = "Student bewerken";
require __DIR__ . '/../layouts/main.php';
?>