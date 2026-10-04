<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Applicatie-instellingen (beveiliging).
 * Wijzig deze waarden alleen bewust; zie docs/security-issues.txt.
 */

// Zelfregistratie via /?action=register. Standaard uit: accounts maakt de admin aan.
const ALLOW_SELF_REGISTRATION = false;

// Wachtwoordbeleid
const PASSWORD_MIN_LENGTH = 12;

// Sessie: automatisch uitloggen na deze inactiviteit (seconden)
const SESSION_IDLE_TIMEOUT = 1800;

// Brute-force-bescherming op login: max. mislukte pogingen per e-mail of IP binnen het venster
const LOGIN_MAX_FAILURES = 10;
const LOGIN_LOCKOUT_MINUTES = 15;

// Gastpogingen: max. aantal starts per IP binnen het venster
const GUEST_START_MAX_PER_IP = 20;
const GUEST_START_WINDOW_MINUTES = 10;

// Geldigheid van gastcookies (seconden)
const GUEST_COOKIE_LIFETIME = 86400 * 30;

// Invoerlimieten
const MAX_ANSWER_LENGTH = 20000;       // tekens per studentantwoord
const MAX_AI_FEEDBACK_LENGTH = 20000;  // tekens AI-feedback via de API
const MAX_NAME_LENGTH = 100;

// AI-vraagontwerper (zie TASKS.md / ARCHITECTURE.md §6.5)
const MAX_DESIGN_TEXT_LENGTH = 4000;     // tekens: vraag en gewenst antwoord
const MAX_DESIGN_INPUT_LENGTH = 2000;    // tekens: antwoord op een verduidelijkende vraag, bijsturing
const MAX_DESIGN_RESULT_LENGTH = 60000;  // bytes: JSON-body van de ontwerp-worker
const DESIGN_START_MAX_PER_HOUR = 10;    // nieuwe ontwerpen per docent per uur
const DESIGN_MAX_REVISIONS = 6;          // maximale revision; daarna geen bijsturing meer

// Agentic beoordelen van studentantwoorden (zie ARCHITECTURE.md §6.8)
const MAX_ASSESSMENT_RESULT_LENGTH = 100000; // bytes: JSON-body van de assessment-worker
const ASSESSMENT_START_MAX_PER_HOUR = 100;   // gestarte runs per docent per uur (een bulkstart telt per antwoord)
const ASSESSMENT_WORKER_STALE_SECONDS = 120; // daarna geldt de assessment-worker als niet actief
const AI_RESULTS_RESET_MAX_PER_HOUR = 30;   // resets van AI-resultaten per docent per uur (een reset van een hele poging telt als één)

// Automatisch agentic beoordelen: een ingeleverd, niet-leeg antwoord op een vraag met
// rubric-criteria (bij een toets met AI-beoordeling aan) gaat naar de assessment-worker
// in plaats van naar process_ai_feedback.py. Mislukt de agentic run, dan valt het antwoord
// terug op de gewone AI-beoordeling. false = alleen handmatig starten.
const AGENTIC_AUTO_ASSESSMENT = true;
const ASSESSMENT_AUTO_START_BATCH = 20;      // automatisch gestarte runs per poll van de assessment-worker

// Externe koppeling: andere websites laten hun deelnemers hier een toets maken
// (zie ARCHITECTURE.md §6.9 en docs/integration-api.md)
const INTEGRATION_LAUNCH_TTL = 900;            // seconden: geldigheid van een eenmalige startlink
const INTEGRATION_START_MAX_PER_HOUR = 300;    // gestarte pogingen (ook nieuwe startlinks) per koppeling per uur
const MAX_INTEGRATION_BODY = 100000;           // bytes: JSON-body van de integratie-endpoints
const INTEGRATION_WEBHOOK_TIMEOUT = 3;         // seconden per webhookverzoek
const INTEGRATION_WEBHOOK_BATCH = 3;           // webhooks per worker-poll
const INTEGRATION_WEBHOOK_MAX_ATTEMPTS = 8;    // daarna geeft de aflevering van een event het op
// Webhooks naar interne adressen (privé, loopback, link-local, ...) toestaan. Standaard uit
// (SSRF-beperking); alleen aanzetten als een gekoppelde website bewust in het eigen netwerk staat.
const INTEGRATION_WEBHOOK_ALLOW_PRIVATE = false;

// ALLEEN VOOR DE DOCKER-DEV: staat http toe naar localhost, 127.0.0.1 en
// host.docker.internal (terugkeer-URL en webhooks). Komt bewust uit een
// omgevingsvariabele, zodat er nooit per ongeluk true wordt gecommit.
// Nooit zetten in productie.
define('INTEGRATION_ALLOW_HTTP', getenv('INTEGRATION_ALLOW_HTTP') === '1');

// Beoordelen met niveaus (grading_scale levels, zie ARCHITECTURE.md en docs/rollout-level-grading.md).
// Overgangsvlag: zolang die uit staat, gaan levels-toetsen NIET naar de AI-worker en de
// assessment-worker; docenten beoordelen die dan zelf. Zet hem pas op true als de nieuwe
// worker (die grading_scale begrijpt) draait. Toetsen met points merken hier niets van.
const LEVELS_AI_ENABLED = true;
const MAX_GRADE_OVERRIDE_REASON = 1000;  // tekens: reden bij een handmatig aangepast eindcijfer

// Naam en contact (layout, footer en de openbare landingspagina op /)
const APP_NAME = 'Open vragen | AI-Toetsing';  // één naam: <title>, footer en landingspagina
const CONTACT_NAME = 'JMNL Innovation';        // organisatie achter de applicatie
const CONTACT_URL = 'https://jmnl.nl';         // website voor contact; leeg = geen knop
const CONTACT_EMAIL = '';                      // e-mailadres voor contact; leeg = niet tonen
