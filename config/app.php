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
