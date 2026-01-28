<h2>Examen bewerken</h2>

<form method="POST" action="index.php?action=exam_update">
    <input type="hidden" name="id" value="<?= $exam['id'] ?>">

    <label>Titel</label><br>
        <input type="text" name="title"
	           value="<?= htmlspecialchars($exam['title']) ?>" required><br><br>

    <label>Omschrijving</label><br>
        <textarea name="description"><?= htmlspecialchars($exam['description']) ?></textarea><br><br>

    <button type="submit">Opslaan</button>
    </form>

<a href="index.php?action=docent_dashboard">Terug</a>
