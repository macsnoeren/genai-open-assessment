<?php
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