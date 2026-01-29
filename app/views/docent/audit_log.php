<?php 
ob_start(); 

// Helper functie om verschillen te visualiseren (inline diff)
if (!function_exists('highlightDiff')) {
    function highlightDiff($old, $new) {
        $old = (string)$old;
        $new = (string)$new;
        
        // Als er geen verschil is of één van de twee leeg is, toon gewoon de nieuwe waarde
        if ($old === $new) return htmlspecialchars($new);

        // Zoek gemeenschappelijke prefix (begin van de zin)
        $lenOld = strlen($old);
        $lenNew = strlen($new);
        $prefixLen = 0;
        $minLen = min($lenOld, $lenNew);
        
        while ($prefixLen < $minLen && $old[$prefixLen] === $new[$prefixLen]) {
            $prefixLen++;
        }

        // Zoek gemeenschappelijke suffix (einde van de zin)
        $suffixLen = 0;
        while ($suffixLen < ($minLen - $prefixLen) && 
               $old[$lenOld - 1 - $suffixLen] === $new[$lenNew - 1 - $suffixLen]) {
            $suffixLen++;
        }

        $prefix = substr($new, 0, $prefixLen);
        $suffix = substr($new, $lenNew - $suffixLen);
        
        $deleted = substr($old, $prefixLen, $lenOld - $prefixLen - $suffixLen);
        $inserted = substr($new, $prefixLen, $lenNew - $prefixLen - $suffixLen);

        $html = htmlspecialchars($prefix);
        if ($deleted !== '') {
            $html .= '<del style="background:#ffe6e6; color:#b30000; text-decoration:line-through;">' . htmlspecialchars($deleted) . '</del>';
        }
        if ($inserted !== '') {
            $html .= '<ins style="background:#e6ffe6; color:#006600; text-decoration:none;">' . htmlspecialchars($inserted) . '</ins>';
        }
        $html .= htmlspecialchars($suffix);

        return $html;
    }
}

if (!function_exists('formatLogDetails')) {
    function formatLogDetails($detailsJson) {
        $data = json_decode($detailsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) return htmlspecialchars($detailsJson);
        return $data;
    }
}
?>

<h2>Audit Log</h2>
<p>Overzicht van recente acties in het systeem.</p>

<table>
    <thead>
        <tr>
            <th>Tijdstip</th>
            <th>Gebruiker</th>
            <th>Actie</th>
            <th>Details</th>
            <th>IP Adres</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['created_at']) ?></td>
                <td><?= htmlspecialchars($log['user_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td>
                    <?php 
                    $data = formatLogDetails($log['details'] ?? '');
                    if (is_array($data)): ?>
                        <ul style="margin: 0; padding-left: 15px; list-style-type: circle;">
                            <?php foreach ($data as $key => $val): ?>
                                <li>
                                    <strong><?= htmlspecialchars(ucfirst($key)) ?>:</strong> 
                                    <?php 
                                    if (is_array($val) && isset($val['old'], $val['new'])) {
                                        echo highlightDiff($val['old'], $val['new']);
                                    } else {
                                        echo htmlspecialchars(is_array($val) ? json_encode($val) : $val);
                                    }
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <?= $data ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php 
$content = ob_get_clean();
$title = "Audit Log";
require __DIR__ . '/../layouts/main.php';
?>