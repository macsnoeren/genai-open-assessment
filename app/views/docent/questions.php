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
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Vragen</h1>
        <p class="text-muted mb-0"><?= e($exam['title']) ?></p>
    </div>
    <div class="page-header-actions" data-html2canvas-ignore="true">
        <button type="button" id="exportPdfBtn" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Export PDF</button>
        <?php if (!empty($canEdit)): ?>
        <a href="/?action=question_design_create&amp;exam_id=<?= (int)$exam['id'] ?>" class="btn btn-outline-primary"><i class="bi bi-magic me-1" aria-hidden="true"></i>Vraag ontwerpen met AI</a>
        <a href="/?action=question_create&amp;exam_id=<?= (int)$exam['id'] ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nieuwe vraag</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="col-w-45">Vraag</th>
              <th class="col-w-40">Criteria</th>
              <th class="text-end col-w-15" data-html2canvas-ignore="true">Acties</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($questions as $q): ?>
            <tr>
              <td><?= nl2br(e($q['question_text'])) ?></td>
              <td><small class="text-muted"><?= nl2br(e($q['criteria'])) ?></small></td>
              <td class="text-end" data-html2canvas-ignore="true">
                <?php if (!empty($canEdit)): ?>
                <div class="btn-group btn-group-sm">
                    <a href="index.php?action=question_edit&id=<?= (int)$q['id'] ?>" class="btn btn-outline-primary">Bewerken</a>
                    <a href="index.php?action=question_delete&id=<?= (int)$q['id'] ?>" class="btn btn-outline-danger" data-confirm="Weet je het zeker?">Verwijderen</a>
                </div>
                <?php else: ?>
                <span class="text-muted small">Alleen-lezen</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
    </div>
</div>

<?php if (!empty($designs)): ?>
<div class="card mt-4" data-html2canvas-ignore="true">
    <div class="card-header fw-bold">AI-vraagontwerpen</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Vraag</th>
              <th>Status</th>
              <th>Gestart</th>
              <th class="text-end">Acties</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($designs as $d): ?>
            <?php $preview = mb_strlen($d['question_text']) > 100 ? mb_substr($d['question_text'], 0, 100) . '…' : $d['question_text']; ?>
            <tr>
              <td><?= e($preview) ?></td>
              <td><?= e(QuestionDesign::statusLabel($d['status'])) ?></td>
              <td class="text-nowrap"><?= e($d['created_at']) ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <a href="/?action=question_design_view&id=<?= (int)$d['id'] ?>" class="btn btn-outline-primary">Openen</a>
                    <a href="/?action=question_design_delete&id=<?= (int)$d['id'] ?>" class="btn btn-outline-danger"
                       data-confirm="Dit vraagontwerp verwijderen? Een al toegevoegde vraag blijft bestaan.">Verwijderen</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"
        integrity="sha384-Yv5O+t3uE3hunW8uyrbpPW3iw6/5/Y7HitWJBLgqfMoA36NogMmy+8wWZMpn3HWc" crossorigin="anonymous"></script>
<script nonce="<?= e(cspNonce()) ?>">
document.addEventListener('DOMContentLoaded', function() {
    var pdfBtn = document.getElementById('exportPdfBtn');
    if (pdfBtn) { pdfBtn.addEventListener('click', generatePDF); }
});
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
