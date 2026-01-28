<?php
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