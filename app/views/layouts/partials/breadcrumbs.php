<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Breadcrumbs uit $breadcrumbs (['Label' => url of null/'' voor de huidige pagina]).
// $breadcrumbsCompact: in de topbalk alleen het laatste item onder de md-breedte.
$breadcrumbsCompact = $breadcrumbsCompact ?? false;
$crumbs = !empty($breadcrumbs) && is_array($breadcrumbs) ? $breadcrumbs : [];
if (!$crumbs && $breadcrumbsCompact && !empty($title)) {
    $crumbs = [$title => null];
}
$lastLabel = $crumbs ? array_key_last($crumbs) : null;
?>
<?php if ($crumbs): ?>
<nav class="app-breadcrumbs" aria-label="Kruimelpad">
    <ol class="breadcrumb mb-0">
        <?php foreach ($crumbs as $label => $url): ?>
            <?php $hide = $breadcrumbsCompact && $label !== $lastLabel ? ' d-none d-md-inline-block' : ''; ?>
            <?php if ($url && $label !== $lastLabel): ?>
                <li class="breadcrumb-item<?= $hide ?>"><a href="<?= e($url) ?>"><?= e($label) ?></a></li>
            <?php else: ?>
                <li class="breadcrumb-item active<?= $hide ?>" aria-current="page"><?= e($label) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
