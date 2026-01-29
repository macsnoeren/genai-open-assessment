<?php ob_start(); ?>

<h2>Audit Log</h2>
<p>Overzicht van recente acties in het systeem.</p>

<table style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em;">
    <thead>
        <tr style="text-align: left;">
            <th style="padding: 8px; border-bottom: 2px solid #ddd;">Tijdstip</th>
            <th style="padding: 8px; border-bottom: 2px solid #ddd;">Gebruiker</th>
            <th style="padding: 8px; border-bottom: 2px solid #ddd;">Actie</th>
            <th style="padding: 8px; border-bottom: 2px solid #ddd;">Details</th>
            <th style="padding: 8px; border-bottom: 2px solid #ddd;">IP Adres</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap;"><?= htmlspecialchars($log['created_at']) ?></td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($log['user_name'] ?? '') ?></td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($log['action']) ?></td>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-family: monospace; max-width: 400px; overflow-wrap: break-word;"><?= htmlspecialchars($log['details'] ?? '') ?></td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php 
$content = ob_get_clean();
$title = "Audit Log";
require __DIR__ . '/../layouts/main.php';
?>