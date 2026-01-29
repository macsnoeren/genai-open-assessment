<?php
ob_start();

if (isset($_SESSION['new_api_key'])): 
    $newKeyData = $_SESSION['new_api_key'];
    unset($_SESSION['new_api_key']);
?>
<div class="success-message" style="padding: 15px; background-color: #e6ffe6; border: 1px solid #006600; margin-bottom: 20px; border-radius: 5px;">
    <strong>Nieuwe API-key succesvol aangemaakt!</strong><br>
    Dit is de enige keer dat de volledige key wordt getoond. Kopieer hem nu en bewaar hem op een veilige plek.<br><br>
    <strong>Naam:</strong> <?= htmlspecialchars($newKeyData['name']) ?><br>
    <strong>Key:</strong> <input type="text" readonly onclick="this.select();" value="<?= htmlspecialchars($newKeyData['key']) ?>" style="width: 100%; font-family: monospace; margin-top: 5px; padding: 5px;">
</div>
<?php endif; ?>

<h2>API-keys beheren</h2>

<p>Beheer hier de API-keys voor externe applicaties, zoals de AI feedback service.</p>

<!-- Knop om modal te openen -->
<button id="openApiKeyModal" class="table-btn">➕ Nieuwe API-key</button>
<br><hr>

<table>
  <thead>
    <tr>
      <th>Naam</th>
      <th>Key (gedeeltelijk)</th>
      <th>Aangemaakt</th>
      <th>Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($keys as $key): ?>
    <tr>
      <td><?= htmlspecialchars($key['name']) ?></td>
      <td style="font-family: monospace;"><?= htmlspecialchars(substr($key['api_key'], 0, 8)) ?>...</td>
      <td><?= $key['created_at'] ?></td>
      <td>
        <a href="/?action=api_key_delete&id=<?= $key['id'] ?>"
           onclick="return confirm('Weet je zeker dat je deze API-key wilt verwijderen?')" style="color: #c00;">🗑 Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Modal -->
<div id="apiKeyModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Nieuwe API-key</h2>
    
    <form method="POST" action="/?action=api_key_create">
      <label>Naam</label>
      <input type="text" name="name" placeholder="bv. AI Feedback Script" required>
      
      <p style="font-size: 0.9em; color: #555;">Er wordt een nieuwe, unieke key gegenereerd. Deze wordt na het aanmaken niet meer getoond.</p>
      
      <button type="submit">Aanmaken</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('apiKeyModal');
    var btn = document.getElementById('openApiKeyModal');
    var span = modal.querySelector('.close');

    if(btn) btn.onclick = () => modal.style.display = 'block';
    if(span) span.onclick = () => modal.style.display = 'none';
    window.onclick = (event) => {
        if (event.target == modal) modal.style.display = 'none';
    }
});
</script>

<?php
$content = ob_get_clean();
$title = "API-keys beheren";
require __DIR__ . '/../layouts/main.php';
?>