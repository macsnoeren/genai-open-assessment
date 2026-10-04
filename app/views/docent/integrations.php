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

<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Koppelingen</h1>
        <p class="text-muted mb-0">Andere websites die hun deelnemers hier een toets laten maken.</p>
    </div>
    <a href="/?action=integration_create" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nieuwe koppeling</a>
</div>

<p>Met een koppeling laat een andere website (bijvoorbeeld een leeromgeving) haar eigen deelnemers hier een toets maken. De externe server start een poging met een API-key, de deelnemer maakt de toets zonder account, en de AI kijkt de toets na. De externe website volgt de status via de API en webhooks. Zie <code>docs/integration-api.md</code> voor de technische beschrijving.</p>


<div class="card">
<div class="table-responsive">
<table class="table table-striped table-hover mb-0 align-middle">
  <thead class="table-light">
    <tr>
      <th>Naam</th>
      <th>Status</th>
      <th>Origin</th>
      <th>Webhook</th>
      <th class="text-end">Toetsen</th>
      <th class="text-end">Niet afgeleverde events</th>
      <th class="text-end">Acties</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($integrations)): ?>
    <tr><td colspan="7" class="text-muted">Er zijn nog geen koppelingen.</td></tr>
    <?php endif; ?>
    <?php foreach ($integrations as $integration): ?>
    <tr>
      <td><a href="/?action=integration_view&id=<?= (int)$integration['id'] ?>"><?= e($integration['name']) ?></a></td>
      <td>
        <?php if ($integration['active']): ?>
        <span class="badge badge-soft-success">Actief</span>
        <?php else: ?>
        <span class="badge badge-soft-secondary">Uitgeschakeld</span>
        <?php endif; ?>
      </td>
      <td class="font-monospace small"><?= e($integration['return_origin']) ?></td>
      <td><?= $integration['webhook_url'] ? 'Ja' : 'Nee' ?></td>
      <td class="text-end"><?= (int)$integration['exam_count'] ?></td>
      <td class="text-end"><?= (int)$integration['undelivered_events'] ?></td>
      <td class="text-end text-nowrap">
        <a href="/?action=integration_view&id=<?= (int)$integration['id'] ?>" class="btn btn-sm btn-outline-secondary">Bekijken</a>
        <a href="/?action=integration_edit&id=<?= (int)$integration['id'] ?>" class="btn btn-sm btn-outline-primary">Wijzigen</a>
        <?php if ($integration['active']): ?>
        <a href="/?action=integration_toggle&id=<?= (int)$integration['id'] ?>"
           data-confirm="Koppeling uitschakelen? De externe website krijgt dan geen toegang meer tot de API en startlinks werken niet meer." class="btn btn-sm btn-outline-secondary">Uitschakelen</a>
        <?php else: ?>
        <a href="/?action=integration_toggle&id=<?= (int)$integration['id'] ?>"
           data-confirm="Koppeling weer inschakelen?" class="btn btn-sm btn-outline-success">Inschakelen</a>
        <?php endif; ?>
        <a href="/?action=integration_delete&id=<?= (int)$integration['id'] ?>"
           data-confirm="Koppeling en API-key verwijderen? De pogingen blijven als gastpogingen bestaan, maar de externe website kan ze niet meer opvragen." class="btn btn-sm btn-outline-danger">Verwijderen</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<?php
$content = ob_get_clean();
$title = 'Externe koppelingen';
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Koppelingen' => ''
];
require __DIR__ . '/../layouts/main.php';
