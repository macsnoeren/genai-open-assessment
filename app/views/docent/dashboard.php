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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Docent Dashboard</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#examModal">
        Nieuw examen
    </button>
</div>

<p class="lead">Welkom <?= htmlspecialchars($_SESSION['name']) ?></p>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Titel</th>
                  <th>Aangemaakt</th>
                  <th class="text-end">Acties</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($exams as $exam): ?>
                <tr>
                  <td class="align-middle"><?= htmlspecialchars($exam['title']) ?></td>
                  <td class="align-middle"><?= $exam['created_at'] ?></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="/?action=questions&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary">Vragen</a>
                        <a href="/?action=exam_results&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary">Resultaten</a>
                        <a href="/?action=start_exam&exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary" onclick="return confirm('Testen?')" title="Testen">Testen</a>
                        <a href="/?action=exam_edit&id=<?= $exam['id'] ?>" class="btn btn-outline-primary">Bewerken</a>
                        <a href="/?action=exam_delete&id=<?= $exam['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Verwijderen?')">Verwijderen</a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="examModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nieuw examen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
    <form id="modalForm" method="POST" action="/?action=exam_store">
      <input type="hidden" name="id" id="examId">
      <div class="mb-3">
          <label class="form-label">Titel</label>
          <input type="text" name="title" class="form-control" required>
      </div>
      <div class="mb-3">
          <label class="form-label">Omschrijving</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Opslaan</button>
      </div>
    </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var examModal = document.getElementById('examModal');
    // Verplaats de modal naar de body om z-index problemen te voorkomen
    document.body.appendChild(examModal);
    
    var modalTitle = examModal.querySelector('.modal-title');
    var modalForm = document.getElementById('modalForm');
    var titleInput = modalForm.querySelector('input[name="title"]');
    var descInput = modalForm.querySelector('textarea[name="description"]');
    var idInput = document.getElementById('examId');

    // Reset formulier bij openen (voor Nieuw examen)
    examModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (button && button.getAttribute('data-bs-target') === '#examModal') {
            modalTitle.textContent = 'Nieuw examen';
            modalForm.action = '/?action=exam_store';
            titleInput.value = '';
            descInput.value = '';
            idInput.value = '';
        }
    });

    // Afhandeling Bewerken knoppen
    document.querySelectorAll('.editExam').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            modalTitle.textContent = 'Examen bewerken';
            modalForm.action = '/?action=exam_update';
            titleInput.value = this.getAttribute('data-title');
            descInput.value = this.getAttribute('data-desc');
            idInput.value = this.getAttribute('data-id');
            
            var bsModal = new bootstrap.Modal(examModal);
            bsModal.show();
        });
    });
});
</script>

<?php
$content = ob_get_clean();
$title = "Docent Dashboard";
require __DIR__ . '/../layouts/main.php';
?>
