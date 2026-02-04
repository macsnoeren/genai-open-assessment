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

<div id="questions-content">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Vragen: <?= htmlspecialchars($exam['title']) ?></h2>
    <div data-html2canvas-ignore="true">
        <button onclick="generatePDF()" class="btn btn-secondary me-2">Export PDF</button>
        <a href="index.php?action=question_create&exam_id=<?= $exam['id'] ?>" class="btn btn-primary">Nieuwe vraag</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 45%">Vraag</th>
              <th style="width: 40%">Criteria</th>
              <th style="width: 15%" class="text-end" data-html2canvas-ignore="true">Acties</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($questions as $q): ?>
            <tr>
              <td><?= nl2br(htmlspecialchars($q['question_text'])) ?></td>
              <td><small class="text-muted"><?= nl2br(htmlspecialchars($q['criteria'])) ?></small></td>
              <td class="text-end" data-html2canvas-ignore="true">
                <div class="btn-group btn-group-sm">
                    <a href="index.php?action=question_edit&id=<?= $q['id'] ?>" class="btn btn-outline-primary">Bewerken</a>
                    <a href="index.php?action=question_delete&id=<?= $q['id'] ?>" class="btn btn-outline-danger" data-confirm="Weet je het zeker?">Verwijderen</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
    </div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function generatePDF() {
    const element = document.getElementById('questions-content');
    const opt = {
        margin:       10,
        filename:     'Vragen_<?= preg_replace('/[^a-z0-9]/i', '_', $exam['title']) ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save();
}
</script>

<?php
$content = ob_get_clean();
$title = "Vragen beheren";
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    "Vragen: " . $exam['title'] => ''
];
require __DIR__ . '/../layouts/main.php';
?>
