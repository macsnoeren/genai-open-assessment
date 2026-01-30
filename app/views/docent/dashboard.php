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

<h2>Docent dashboard</h2>

<p>Welkom <?= htmlspecialchars($_SESSION['name']) ?></p>

<!-- Knop om modal te openen -->
<button id="openModal" class="table-btn">➕ Nieuw examen</button><br><hr>

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
<a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" onclick="return confirm('U staat op het punt deze toets als test af te leggen. Uw poging zal zichtbaar zijn in de resultaten. Weet u het zeker?')" title="Toets afleggen als test">🧪 Testen</a> |
<a href="#" class="editExam" data-id="<?= $exam['id'] ?>" data-title="<?= htmlspecialchars($exam['title']) ?>" data-desc="<?= htmlspecialchars($exam['description']) ?>">✏ Bewerken</a> |
	<a href="/?action=exam_delete&id=<?= $exam['id'] ?>"
	   onclick="return confirm('Weet je zeker dat je deze toets en alle bijbehorende vragen en resultaten wilt verwijderen?')" style="color: #c00;">🗑 Verwijderen</a>
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

<script src="/js/modal.js"></script>

<?php
$content = ob_get_clean();
$title = "Docent Dashboard";
require __DIR__ . '/../layouts/main.php';
?>
