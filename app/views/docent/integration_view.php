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
$id = (int)$integration['id'];
?>

<?php if ($credentials): ?>
<div class="alert alert-success mb-4">
    <strong><?= $credentials['key'] !== null ? 'Koppeling aangemaakt.' : 'Nieuw webhookgeheim aangemaakt.' ?></strong><br>
    Dit is de enige keer dat <?= $credentials['key'] !== null ? 'de API-key en het webhookgeheim worden' : 'het webhookgeheim wordt' ?> getoond. Kopieer ze nu en geef ze veilig door aan de beheerder van <?= e($credentials['name']) ?>.
    <?php if ($credentials['key'] !== null): ?>
    <div class="mt-3"><strong>API-key</strong> (alleen voor de server van de externe website, nooit in de browser):</div>
    <div class="input-group mt-1">
        <input type="text" id="newIntegrationKey" readonly data-select-on-click value="<?= e($credentials['key']) ?>" class="form-control font-monospace">
        <button class="btn btn-outline-secondary" type="button" data-copy-target="newIntegrationKey" title="Kopieer key">📋</button>
    </div>
    <?php endif; ?>
    <div class="mt-3"><strong>Webhookgeheim</strong> (om de handtekening van webhooks te controleren):</div>
    <div class="input-group mt-1">
        <input type="text" id="newIntegrationSecret" readonly data-select-on-click value="<?= e($credentials['secret']) ?>" class="form-control font-monospace">
        <button class="btn btn-outline-secondary" type="button" data-copy-target="newIntegrationSecret" title="Kopieer geheim">📋</button>
    </div>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="mb-0">
        <?= e($integration['name']) ?>
        <?php if ($integration['active']): ?>
        <span class="badge bg-success fs-6 align-middle">Actief</span>
        <?php else: ?>
        <span class="badge bg-secondary fs-6 align-middle">Uitgeschakeld</span>
        <?php endif; ?>
    </h2>
    <div class="d-flex flex-wrap gap-2">
        <a href="/?action=integration_edit&id=<?= $id ?>" class="btn btn-sm btn-outline-primary">Wijzigen</a>
        <a href="/?action=integration_rotate_secret&id=<?= $id ?>"
           data-confirm="Nieuw webhookgeheim maken? Het huidige geheim werkt direct niet meer: de externe website moet het nieuwe geheim gebruiken om webhooks te controleren." class="btn btn-sm btn-outline-warning">Webhookgeheim vernieuwen</a>
        <?php if ($integration['active']): ?>
        <a href="/?action=integration_toggle&id=<?= $id ?>"
           data-confirm="Koppeling uitschakelen? De externe website krijgt dan geen toegang meer tot de API en startlinks werken niet meer." class="btn btn-sm btn-outline-secondary">Uitschakelen</a>
        <?php else: ?>
        <a href="/?action=integration_toggle&id=<?= $id ?>" data-confirm="Koppeling weer inschakelen?" class="btn btn-sm btn-outline-success">Inschakelen</a>
        <?php endif; ?>
        <a href="/?action=integration_delete&id=<?= $id ?>"
           data-confirm="Koppeling en API-key verwijderen? De pogingen blijven als gastpogingen bestaan, maar de externe website kan ze niet meer opvragen." class="btn btn-sm btn-outline-danger">Verwijderen</a>
    </div>
</div>

<div class="card mb-4">
<div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-4">API-endpoint</dt>
        <dd class="col-sm-8 font-monospace small"><?= e($apiUrl) ?></dd>
        <dt class="col-sm-4">Origin terugkeer-URL</dt>
        <dd class="col-sm-8 font-monospace small"><?= e($integration['return_origin']) ?></dd>
        <dt class="col-sm-4">Webhook-URL</dt>
        <dd class="col-sm-8 font-monospace small"><?= $integration['webhook_url'] ? e($integration['webhook_url']) : '<span class="text-muted">geen (alleen de API)</span>' ?></dd>
        <dt class="col-sm-4">Drempel menselijke controle</dt>
        <dd class="col-sm-8">confidence onder <strong><?= e($integration['min_confidence']) ?></strong></dd>
        <dt class="col-sm-4">Aangemaakt</dt>
        <dd class="col-sm-8"><?= e($integration['created_at']) ?></dd>
    </dl>
</div>
</div>

<h4>Gekoppelde toetsen</h4>
<?php if (empty($linkedExams)): ?>
    <p class="text-muted">Nog geen toetsen gekoppeld. De externe website kan dan niets starten.</p>
<?php else: ?>
<ul class="list-group mb-4">
    <?php foreach ($linkedExams as $exam): ?>
    <li class="list-group-item d-flex justify-content-between align-items-center">
        <span>
            <a href="/?action=exam_results&exam_id=<?= (int)$exam['id'] ?>"><?= e($exam['title']) ?></a>
            <span class="text-muted small">(toets-id <?= (int)$exam['id'] ?>, <?= (int)$exam['question_count'] ?> vragen)</span>
        </span>
        <?php if (empty($exam['ai_grading_enabled'])): ?>
        <span class="badge bg-warning text-dark" title="Nieuwe pogingen kunnen niet starten; lopende pogingen blijven op grading staan.">AI-beoordeling staat uit</span>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<h4>Webhooks</h4>
<p class="small text-muted mb-2">
    Afgeleverd: <?= (int)$eventCounts['delivered'] ?> ·
    Open (wacht op een volgende poging): <?= (int)$eventCounts['open'] ?> ·
    Opgegeven na <?= (int)INTEGRATION_WEBHOOK_MAX_ATTEMPTS ?> pogingen: <?= (int)$eventCounts['given_up'] ?>.
    Webhooks worden verstuurd terwijl de AI-workers pollen; zonder draaiende worker gaan er geen webhooks.
</p>
<div class="card mb-4">
<div class="table-responsive">
<table class="table table-sm table-striped mb-0 align-middle">
    <thead class="table-light">
        <tr>
            <th>Event</th>
            <th>Poging</th>
            <th class="text-end">Mislukte pogingen</th>
            <th>Laatste status of fout</th>
            <th>Afgeleverd op</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($events)): ?>
        <tr><td colspan="5" class="text-muted">Nog geen events.</td></tr>
        <?php endif; ?>
        <?php foreach ($events as $event): ?>
        <tr>
            <td class="font-monospace small">#<?= (int)$event['id'] ?> <?= e($event['event']) ?></td>
            <td class="small"><?= (int)$event['student_exam_id'] ?><?= $event['external_ref'] !== null ? ' (' . e($event['external_ref']) . ')' : '' ?></td>
            <td class="text-end"><?= (int)$event['attempts'] ?></td>
            <td class="small">
                <?php if ($event['last_status'] !== null): ?>HTTP <?= (int)$event['last_status'] ?><?php endif; ?>
                <?php if ($event['last_error']): ?><span class="text-danger"><?= e($event['last_error']) ?></span><?php endif; ?>
                <?php if ($event['delivered_at'] === null && $event['next_attempt_at'] === null): ?>
                    <span class="badge bg-danger">Opgegeven</span>
                <?php elseif ($event['delivered_at'] === null): ?>
                    <span class="text-muted">volgende poging vanaf <?= e($event['next_attempt_at']) ?></span>
                <?php endif; ?>
            </td>
            <td class="small"><?= e($event['delivered_at'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<h4>Laatste pogingen</h4>
<div class="card mb-4">
<div class="table-responsive">
<table class="table table-sm table-striped mb-0 align-middle">
    <thead class="table-light">
        <tr>
            <th>Poging</th>
            <th>Ref</th>
            <th>Naam</th>
            <th>Toets</th>
            <th>Status</th>
            <th>Aangemaakt</th>
            <th>Ingeleverd</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($attempts)): ?>
        <tr><td colspan="7" class="text-muted">Nog geen pogingen.</td></tr>
        <?php endif; ?>
        <?php foreach ($attempts as $attempt): ?>
        <tr>
            <td><a href="/?action=view_student_answers&student_exam_id=<?= (int)$attempt['student_exam_id'] ?>"><?= (int)$attempt['student_exam_id'] ?></a></td>
            <td class="font-monospace small"><?= e($attempt['external_ref']) ?></td>
            <td><?= e($attempt['guest_name']) ?></td>
            <td><?= e($attempt['exam_title']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= e($attempt['status'] ?? '') ?></span></td>
            <td class="small"><?= e($attempt['created_at']) ?></td>
            <td class="small"><?= e($attempt['completed_at'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php
$content = ob_get_clean();
$title = 'Koppeling: ' . $integration['name'];
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Koppelingen' => '/?action=integrations',
    $integration['name'] => ''
];
require __DIR__ . '/../layouts/main.php';
