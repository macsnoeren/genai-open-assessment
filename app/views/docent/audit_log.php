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

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<div class="mb-3">
    <a href="/?action=clear_audit_log" class="btn btn-danger" onclick="return confirm('Weet u zeker dat u de volledige audit log wilt wissen? Deze actie kan niet ongedaan worden gemaakt.');">Log leegmaken</a>
</div>
<?php endif; ?>

<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
    <thead class="table-light">
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
                <td class="text-nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                <td><?= htmlspecialchars($log['user_name'] ?? '') ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($log['action']) ?></span></td>
                <td style="max-width: 500px; overflow-wrap: break-word;">
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
                <td class="text-muted small"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php if (isset($totalPages) && $totalPages > 1): ?>
<div class="d-flex justify-content-center mt-4">
    <?php if ($page > 1): ?>
        <a href="/?action=audit_log&page=<?= $page - 1 ?>" class="btn btn-outline-secondary me-2">&laquo; Vorige</a>
    <?php endif; ?>
    <span class="align-self-center mx-2 fw-bold">Pagina <?= $page ?> van <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
        <a href="/?action=audit_log&page=<?= $page + 1 ?>" class="btn btn-outline-secondary ms-2">Volgende &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php 
$content = ob_get_clean();
$title = "Audit Log";
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Audit Log' => ''
];
require __DIR__ . '/../layouts/main.php';
?>