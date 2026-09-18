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
 * Algemene beveiligingshelpers: HTTP-headers, CSP-nonce, escaping,
 * invoervalidatie en nette foutafhandeling.
 */

/** HTML-escape voor output in views. */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** True als het verzoek via HTTPS binnenkwam (ook achter een reverse proxy). */
function isHttps(): bool {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

/** Basis-URL van de applicatie (schema + host), voor het opbouwen van links. */
function appBaseUrl(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (isHttps() ? 'https' : 'http') . '://' . $host;
}

/** Per-request nonce voor inline scripts (Content-Security-Policy). */
function cspNonce(): string {
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

/**
 * Stuurt de security headers. Aanroepen voordat er output is.
 * @param bool $html true voor HTML-pagina's (met CSP), false voor JSON-API.
 */
function sendSecurityHeaders(bool $html = true): void {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header_remove('X-Powered-By');

    if (isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if ($html) {
        $nonce = cspNonce();
        header("Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "img-src 'self' data: blob:; "
            . "font-src 'self' https://cdn.jsdelivr.net; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self'; "
            . "form-action 'self'; "
            . "base-uri 'self'; "
            . "object-src 'none'");
    } else {
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    }
}

/** Beëindigt het verzoek met een HTTP-status en een korte, veilige melding. */
function abort(int $code, string $message): void {
    http_response_code($code);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Fout ' . $code . '</title>'
        . '<link rel="stylesheet" href="/style.css"></head><body class="p-4">'
        . '<h1>' . $code . '</h1><p>' . e($message) . '</p>'
        . '<p><a href="/">Terug naar de startpagina</a></p></body></html>';
    exit;
}

/** Leest een positief integer-id uit een input-array; null als afwezig/ongeldig. */
function requestInt(array $source, string $key): ?int {
    if (!isset($source[$key]) || is_array($source[$key])) {
        return null;
    }
    $value = trim((string)$source[$key]);
    if ($value === '' || !preg_match('/^\d{1,18}$/', $value)) {
        return null;
    }
    return (int)$value;
}

/** Leest een string uit een input-array (nooit een array), met lengtelimiet. */
function requestString(array $source, string $key, int $maxLength = 65535, string $default = ''): string {
    if (!isset($source[$key]) || is_array($source[$key])) {
        return $default;
    }
    $value = (string)$source[$key];
    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

/** Maakt een waarde veilig voor CSV-export (formula injection). */
function csvSafe($value): string {
    $text = (string)$value;
    if ($text !== '' && preg_match('/^[=+\-@\t\r]/', $text)) {
        return "'" . $text;
    }
    return $text;
}

/** Registreert een generieke afhandeling van onafgevangen fouten (geen stack traces naar de client). */
function registerErrorHandling(bool $jsonResponse = false): void {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    set_exception_handler(function (Throwable $ex) use ($jsonResponse) {
        error_log(sprintf('[%s] %s in %s:%d', get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine()));
        if ($jsonResponse) {
            http_response_code(500);
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['error' => 'Internal server error']);
            exit;
        }
        abort(500, 'Er is een interne fout opgetreden. Probeer het later opnieuw.');
    });
}
