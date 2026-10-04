<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start(); ?>

<div class="row justify-content-center">
<div class="col-md-8">

<h2 class="mb-4"><?= e($title) ?></h2>

<?php if (!empty($formError)): ?>
    <div class="alert alert-danger"><?= e($formError) ?></div>
<?php endif; ?>

<div class="card">
<div class="card-body">
<form action="/?action=<?= e($action) ?>" method="post" id="gradingSchemeForm">
    <?= csrfInput() ?>
    <?php if ($scheme): ?>
        <input type="hidden" name="id" value="<?= (int)$scheme['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label" for="schemeName">Naam</label>
        <input type="text" name="name" id="schemeName" class="form-control" maxlength="100" value="<?= e($values['name']) ?>" required>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-3">
            <label class="form-label" for="pointsOnvoldoende">Punten voor onvoldoende</label>
            <input type="number" name="points_onvoldoende" id="pointsOnvoldoende" class="form-control" min="0" max="97" step="1" value="<?= e($values['points_onvoldoende']) ?>" required>
        </div>
        <div class="col-sm-3">
            <label class="form-label" for="pointsVoldoende">Punten voor voldoende</label>
            <input type="number" name="points_voldoende" id="pointsVoldoende" class="form-control" min="1" max="98" step="1" value="<?= e($values['points_voldoende']) ?>" required>
        </div>
        <div class="col-sm-3">
            <label class="form-label" for="pointsGoed">Punten voor goed</label>
            <input type="number" name="points_goed" id="pointsGoed" class="form-control" min="2" max="99" step="1" value="<?= e($values['points_goed']) ?>" required>
        </div>
        <div class="col-sm-3">
            <label class="form-label" for="pointsUitstekend">Punten voor uitstekend</label>
            <input type="number" name="points_uitstekend" id="pointsUitstekend" class="form-control" min="3" max="100" step="1" value="<?= e($values['points_uitstekend']) ?>" required>
        </div>
    </div>

    <p class="form-text mb-1">De punten moeten oplopen: 0 ≤ onvoldoende &lt; voldoende &lt; goed &lt; uitstekend ≤ 100. Meestal is onvoldoende 0 punten. Elke combinatie bestaat maar één keer.</p>
    <p class="form-text mb-4">
        Rekenvoorbeeld: alles onvoldoende = 10 × O / U, alles voldoende = 10 × V / U<span id="schemeExample"></span>.
        Alles uitstekend geeft altijd een 10.
    </p>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Opslaan</button>
        <a href="/?action=grading_schemes" class="btn btn-outline-secondary">Annuleren</a>
    </div>
</form>
</div>
</div>

</div>
</div>

<script nonce="<?= e(cspNonce()) ?>">
document.addEventListener('DOMContentLoaded', function () {
    var o = document.getElementById('pointsOnvoldoende');
    var v = document.getElementById('pointsVoldoende');
    var g = document.getElementById('pointsGoed');
    var u = document.getElementById('pointsUitstekend');
    var out = document.getElementById('schemeExample');
    function fmt(x) { return (Math.floor(x * 10 + 0.5 + 1e-9) / 10).toFixed(1).replace('.', ','); }
    function update() {
        var oo = parseInt(o.value || '0', 10), vv = parseInt(v.value, 10), gg = parseInt(g.value, 10), uu = parseInt(u.value, 10);
        if (oo >= 0 && vv > oo && gg > vv && uu > gg) {
            out.textContent = ' (nu: alles onvoldoende = ' + fmt(10 * oo / uu) + ', alles voldoende = ' + fmt(10 * vv / uu)
                + ', alles goed = ' + fmt(10 * gg / uu) + ')';
        } else {
            out.textContent = '';
        }
    }
    [o, v, g, u].forEach(function (el) { el.addEventListener('input', update); });
    update();
});
</script>

<?php
$content = ob_get_clean();
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    "Puntenschema's" => '/?action=grading_schemes',
    $title => ''
];
require __DIR__ . '/../layouts/main.php';
?>
