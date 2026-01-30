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

if (isset($_SESSION['new_api_key'])): 
    $newKeyData = $_SESSION['new_api_key'];
    unset($_SESSION['new_api_key']);
?>
<div class="alert alert-success mb-4">
    <strong>Nieuwe API-key succesvol aangemaakt!</strong><br>
    Dit is de enige keer dat de volledige key wordt getoond. Kopieer hem nu en bewaar hem op een veilige plek.<br><br>
    <strong>Naam:</strong> <?= htmlspecialchars($newKeyData['name']) ?><br>
    <strong>Key:</strong> <input type="text" readonly onclick="this.select();" value="<?= htmlspecialchars($newKeyData['key']) ?>" class="form-control font-monospace mt-2">
</div>
<?php endif; ?>

<h2>API-keys beheren</h2>

<p>Beheer hier de API-keys voor externe applicaties, zoals de AI feedback service.</p>

<div class="mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#apiKeyModal">Nieuwe API-key</button>
</div>

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
  <thead class="table-light">
    <tr>
      <th>Naam</th>
      <th>Key (gedeeltelijk)</th>
      <th>Aangemaakt</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($keys as $key): ?>
    <tr>
      <td><?= htmlspecialchars($key['name']) ?></td>
      <td class="font-monospace"><?= htmlspecialchars(substr($key['api_key'], 0, 8)) ?>...</td>
      <td><?= $key['created_at'] ?></td>
      <td class="text-end">
        <a href="/?action=api_key_delete&id=<?= $key['id'] ?>"
           onclick="return confirm('Weet je zeker dat je deze API-key wilt verwijderen?')" class="btn btn-sm btn-outline-danger">Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<!-- Modal -->
<div class="modal fade" id="apiKeyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nieuwe API-key</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="/?action=api_key_create">
          <div class="mb-3">
              <label class="form-label">Naam</label>
              <input type="text" name="name" class="form-control" placeholder="bv. AI Feedback Script" required>
          </div>
          <div class="mb-3">
              <p class="text-muted small">Er wordt een nieuwe, unieke key gegenereerd. Deze wordt na het aanmaken niet meer getoond.</p>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary">Aanmaken</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
$title = "API-keys beheren";
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'API Keys' => ''
];
require __DIR__ . '/../layouts/main.php';
?>