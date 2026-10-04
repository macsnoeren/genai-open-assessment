<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Openbare landingspagina op /. Leest bewust geen sessie- of toetsgegevens en heeft
// geen formulier; wie ingelogd is, stuurt AuthController::showHome() door.
ob_start();

$steps = [
    ['icon' => 'bi-pencil-square', 'title' => 'Toets en rubric maken',
     'text' => 'De docent maakt een toets met open vragen; de AI-vraagontwerper helpt een rubric op te stellen.'],
    ['icon' => 'bi-chat-left-text', 'title' => 'Antwoorden',
     'text' => 'Studenten maken de toets, ingelogd of als gast via een link.'],
    ['icon' => 'bi-check2-circle', 'title' => 'Voorbeoordelen en beslissen',
     'text' => 'De AI beoordeelt per criterium en onderbouwt dat met citaten; de docent controleert en bepaalt het eindcijfer.'],
];

$features = [
    ['icon' => 'bi-list-check', 'title' => 'Beoordelen op een rubric',
     'text' => 'Elk antwoord wordt per criterium beoordeeld, met essentiële en aanvullende criteria.'],
    ['icon' => 'bi-sliders', 'title' => 'Punten of niveaus',
     'text' => 'Beoordeel met punten of met niveaus van onvoldoende tot uitstekend, met een eigen puntenschema.'],
    ['icon' => 'bi-search', 'title' => 'Agentic beoordelen',
     'text' => 'Meerdere AI-agents zoeken bewijs, beoordelen en controleren elkaar; citaten worden in het antwoord nagekeken.'],
    ['icon' => 'bi-magic', 'title' => 'AI-vraagontwerper',
     'text' => 'Van een vraag en een gewenst antwoord naar een rubric, met verduidelijkende vragen aan de docent.'],
    ['icon' => 'bi-graph-up', 'title' => 'AI en docent vergelijken',
     'text' => 'Zie per toets hoe de AI-beoordeling zich verhoudt tot die van de docent.'],
    ['icon' => 'bi-plug', 'title' => 'Koppelen met een andere website',
     'text' => 'Laat deelnemers van een andere website een toets maken, via een integratie-API en webhooks.'],
];

$audiences = [
    ['icon' => 'bi-person-workspace', 'title' => 'Docenten', 'text' => 'maken toetsen en rubrics en beslissen over het cijfer.'],
    ['icon' => 'bi-mortarboard', 'title' => 'Studenten', 'text' => 'maken toetsen en krijgen feedback per vraag.'],
    ['icon' => 'bi-clipboard-check', 'title' => 'Beoordelaars', 'text' => 'beoordelen ingeleverde toetsen, met de AI-voorbeoordeling ernaast.'],
    ['icon' => 'bi-globe2', 'title' => 'Externe partijen', 'text' => 'laten hun eigen deelnemers toetsen via een koppeling.'],
];
?>

<header class="landing-topbar">
    <div class="container landing-topbar-inner">
        <a href="/" class="landing-brand">
            <img src="/images/logo-h.png" alt="Logo" height="36">
            <span><?= e(APP_NAME) ?></span>
        </a>
        <div class="landing-topbar-actions">
            <?php if (ALLOW_SELF_REGISTRATION): ?>
            <a href="/?action=register" class="btn btn-sm btn-outline-primary d-none d-sm-inline-block">Account aanmaken</a>
            <?php endif; ?>
            <a href="/?action=login" class="btn btn-sm btn-primary">Inloggen</a>
        </div>
    </div>
</header>

<section class="landing-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h1 class="landing-hero-title">Open vragen toetsen, met AI als eerste beoordelaar</h1>
                <p class="landing-hero-lead">
                    Docenten stellen open vragen en een rubric op. De AI geeft per antwoord een onderbouwde
                    voorbeoordeling; de docent beslist.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/?action=login" class="btn btn-light btn-lg landing-btn-primary">Inloggen</a>
                    <a href="#hoe-het-werkt" class="btn btn-outline-light btn-lg">Hoe het werkt</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="landing-demo" aria-label="Voorbeeld van een AI-voorbeoordeling" role="img">
                    <div class="landing-demo-label">Vraag 2</div>
                    <p class="landing-demo-question">Leg uit waarom een plantencel niet knapt in zoet water.</p>
                    <div class="landing-demo-answer">
                        De celwand is stevig en houdt de cel in vorm. Water stroomt naar binnen door osmose,
                        maar de wand houdt de druk tegen.
                    </div>
                    <ul class="landing-demo-criteria">
                        <li><span>Noemt de celwand</span><span class="badge badge-soft-success">Voldaan</span></li>
                        <li><span>Legt osmose uit</span><span class="badge badge-soft-warning">Deels</span></li>
                        <li><span>Noemt de turgordruk</span><span class="badge badge-soft-success">Voldaan</span></li>
                    </ul>
                    <div class="landing-demo-footer">
                        <i class="bi bi-robot" aria-hidden="true"></i>
                        Voorstel: <strong>Goed</strong> &middot; wacht op docent
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="landing-section" id="hoe-het-werkt">
    <div class="container">
        <h2 class="landing-section-title">Hoe het werkt</h2>
        <ol class="row g-4 list-unstyled mb-0">
            <?php foreach ($steps as $i => $step): ?>
            <li class="col-md-4">
                <div class="landing-step">
                    <span class="landing-icon" aria-hidden="true"><i class="bi <?= e($step['icon']) ?>"></i></span>
                    <div class="landing-step-number">Stap <?= (int)$i + 1 ?></div>
                    <h3 class="h5"><?= e($step['title']) ?></h3>
                    <p class="text-muted mb-0"><?= e($step['text']) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="container">
        <h2 class="landing-section-title">Wat de applicatie kan</h2>
        <div class="row g-4">
            <?php foreach ($features as $feature): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <span class="landing-icon" aria-hidden="true"><i class="bi <?= e($feature['icon']) ?>"></i></span>
                        <h3 class="h6 mt-3"><?= e($feature['title']) ?></h3>
                        <p class="text-muted small mb-0"><?= e($feature['text']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="landing-section">
    <div class="container">
        <div class="landing-decides">
            <div class="row g-4 align-items-center">
                <div class="col-lg-4">
                    <h2 class="landing-section-title mb-0">De docent beslist</h2>
                </div>
                <div class="col-lg-8">
                    <ul class="landing-checklist">
                        <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>De AI beoordeelt alleen voor: elk voorstel is onderbouwd en zichtbaar voor de docent.</li>
                        <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Het eindcijfer komt altijd van een mens.</li>
                        <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>De beheerder kiest welke AI-modellen beoordelen. Die draaien via Ollama, ook op een eigen server.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="container">
        <h2 class="landing-section-title">Voor wie</h2>
        <ul class="row g-3 list-unstyled mb-0">
            <?php foreach ($audiences as $audience): ?>
            <li class="col-md-6">
                <div class="landing-audience">
                    <span class="landing-icon" aria-hidden="true"><i class="bi <?= e($audience['icon']) ?>"></i></span>
                    <span><strong><?= e($audience['title']) ?></strong> <?= e($audience['text']) ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="landing-section">
    <div class="container">
        <div class="landing-contact">
            <p class="mb-3">Vragen of interesse? Neem contact op met <?= e(CONTACT_NAME) ?>.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <?php if (CONTACT_URL !== ''): ?>
                <a href="<?= e(CONTACT_URL) ?>" class="btn btn-primary" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Naar <?= e(CONTACT_NAME) ?>
                </a>
                <?php endif; ?>
                <?php if (CONTACT_EMAIL !== ''): ?>
                <a href="mailto:<?= e(CONTACT_EMAIL) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-envelope me-1" aria-hidden="true"></i><?= e(CONTACT_EMAIL) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<footer class="landing-footer">
    <div class="container">
        &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; <?= e(CONTACT_NAME) ?> &middot;
        <a href="/?action=privacy">Privacy &amp; Cookies</a>
    </div>
</footer>

<?php
$content = ob_get_clean();
$title = APP_NAME;
$hideHeaderFooter = true;
$fullWidth = true;
require __DIR__ . '/../layouts/main.php';
?>
