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

<form method="POST" action="/?action=student_store">
    <h2>Nieuwe student</h2>

    <label>Naam</label>
        <input type="text" name="name" required>

    <label>Email</label>
        <input type="email" name="email" required>

    <label>Wachtwoord</label>
        <input type="password" name="password" required>

    <button type="submit">Opslaan</button>
    </form>

<?php
$content = ob_get_clean();
$title = "Nieuwe student";
require __DIR__ . '/../layouts/main.php';
?>