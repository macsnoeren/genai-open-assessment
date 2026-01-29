<?php
ob_start();
?>

<h2>Docent dashboard</h2>

<p>Welkom <?= htmlspecialchars($_SESSION['name']) ?></p>

<a href="/?action=api_keys" class="table-btn">
  🔐 API-keys beheren
</a>
<a href="/?action=audit_log" class="table-btn">
  📜 Audit Log
</a>
   
<!-- Studentenbeheer knop -->
<a href="/?action=students" class="table-btn">👨‍🎓 Studenten beheren</a>

<!-- Knop om modal te openen -->
<button id="openModal" class="table-btn">➕ Nieuw examen</button <br><hr>

<table>
  <thead>
    <tr>
      <th>Titel</th>
      <th>Aangemaakt</th>
      <th>Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($exams as $exam): ?>
    <tr>
      <td><?= htmlspecialchars($exam['title']) ?></td>
      <td><?= $exam['created_at'] ?></td>
      <td>
	<a href="/?action=questions&exam_id=<?= $exam['id'] ?>">📋 Vragen</a> |
<a href="/?action=exam_results&exam_id=<?= $exam['id'] ?>">👀 Resultaten</a> |
<a href="#" class="editExam" data-id="<?= $exam['id'] ?>" data-title="<?= htmlspecialchars($exam['title']) ?>" data-desc="<?= htmlspecialchars($exam['description']) ?>">✏ Bewerken</a>
	<a href="/?action=exam_delete&id=<?= $exam['id'] ?>"
	   onclick="return confirm('Weet je het zeker?')">🗑 Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Modal -->
<div id="examModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2 id="modalTitle">Nieuw examen</h2>
    
    <form id="modalForm" method="POST" action="/?action=exam_store">
      <input type="hidden" name="id" id="examId">
      
      <label>Titel</label>
      <input type="text" name="title" id="examTitle" required>
      
      <label>Omschrijving</label>
      <textarea name="description" id="examDescription"></textarea>
      
      <button type="submit">Opslaan</button>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
$title = "Docent Dashboard";
require __DIR__ . '/../layouts/main.php';
?>
