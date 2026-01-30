<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
?>
<h2>Nieuw examen</h2>

<form method="POST" action="index.php?action=exam_store">
    <label>Titel</label><br>
        <input type="text" name="title" required><br><br>

    <label>Omschrijving</label><br>
        <textarea name="description"></textarea><br><br>

    <button type="submit">Opslaan</button>
    </form>

<a href="index.php?action=docent_dashboard">Terug</a>
