<h2>API-keys beheren</h2>

<?php if (!empty($_SESSION['new_api_key'])): ?>
<div class="alert alert-warning">
  <strong>Nieuwe API-key:</strong><br>
  <code><?= $_SESSION['new_api_key'] ?></code><br>
  ⚠️ Kopieer deze sleutel nu, hij wordt niet opnieuw getoond.
</div>
<?php unset($_SESSION['new_api_key']); endif; ?>

<form method="post" action="/?action=api_key_create" class="form-wide">
  <input type="text" name="name" placeholder="Naam (bv. Python AI)" required>
  <button class="btn-primary">➕ Nieuwe API-key</button>
</form>

<table class="styled-table">
  <tr>
    <th>Naam</th>
    <th>Key</th>
    <th>Status</th>
    <th>Aangemaakt</th>
    <th>Acties</th>
  </tr>
  
  <?php foreach ($keys as $key): ?>
  <tr>
    <td><?= htmlspecialchars($key['name']) ?></td>
    <td>****<?= substr($key['api_key'], -4) ?></td>
    <td><?= $key['active'] ? 'Actief' : 'Geblokkeerd' ?></td>
    <td><?= $key['created_at'] ?></td>
    <td>
      <a class="table-btn" href="/?action=api_key_toggle&id=<?= $key['id'] ?>">
	<?= $key['active'] ? 'Blokkeer' : 'Activeer' ?>
      </a>
      <a class="table-btn danger"
	 href="/?action=api_key_delete&id=<?= $key['id'] ?>"
	 onclick="return confirm('API-key verwijderen?')">
	Verwijder
      </a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
