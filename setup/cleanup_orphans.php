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
 * Ruimt "wezen" op in een oudere database (S-05): rijen die verwijzen naar een
 * rij die niet meer bestaat. Die konden ontstaan toen foreign keys nog niet
 * per verbinding aan stonden. Alleen vanaf de command line.
 *
 *   php setup/cleanup_orphans.php          # alleen tonen (dry-run)
 *   php setup/cleanup_orphans.php --apply  # opruimen
 *
 * Per foreign key volgt het de ON DELETE-regel uit het schema: CASCADE
 * verwijdert de rij, SET NULL maakt de kolom leeg. Bij RESTRICT (een toets
 * zonder docent) wordt niets gewijzigd; dat moet de beheerder zelf oplossen.
 * Maak eerst een backup van database/database.sqlite.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Dit script mag alleen vanaf de command line worden uitgevoerd.\n");
}

$apply = in_array('--apply', $argv, true);
$db = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON'); // zodat het verwijderen zelf weer cascadeert

$total = 0;
$skipped = 0;
// Herhalen: een verwijderde wees kan via een SET NULL of een andere tabel nieuwe gevolgen hebben.
for ($round = 1; $round <= 10; $round++) {
    $violations = $db->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
    $actionable = 0;
    foreach ($violations as $v) {
        $table = $v['table'];
        $fk = null;
        foreach ($db->query('PRAGMA foreign_key_list(' . $db->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int)$row['id'] === (int)$v['fkid']) {
                $fk = $row;
                break;
            }
        }
        $rule = strtoupper((string)($fk['on_delete'] ?? 'NO ACTION'));
        $desc = sprintf('%s rowid %s: %s -> %s.%s (ON DELETE %s)', $table, $v['rowid'], $fk['from'] ?? '?',
            $v['parent'], $fk['to'] ?? 'id', $rule);

        if ($rule === 'CASCADE') {
            $sql = 'DELETE FROM "' . str_replace('"', '""', $table) . '" WHERE rowid = ?';
        } elseif ($rule === 'SET NULL') {
            $sql = 'UPDATE "' . str_replace('"', '""', $table) . '" SET "' . str_replace('"', '""', (string)$fk['from']) . '" = NULL WHERE rowid = ?';
        } else {
            if ($round === 1) {
                echo "  OVERGESLAGEN  $desc (handmatig oplossen)\n";
                $skipped++;
            }
            continue;
        }
        $actionable++;
        echo ($apply ? '  OPGERUIMD     ' : '  GEVONDEN      ') . $desc . "\n";
        if ($apply) {
            $db->prepare($sql)->execute([$v['rowid']]);
        }
    }
    $total += $actionable;
    if (!$apply || $actionable === 0) {
        break;
    }
}

if ($total === 0 && $skipped === 0) {
    echo "Geen wezen gevonden.\n";
} elseif (!$apply) {
    echo "\n$total rij(en) op te ruimen, $skipped overgeslagen. Draai opnieuw met --apply (na een backup).\n";
} else {
    echo "\n$total rij(en) opgeruimd, $skipped overgeslagen.\n";
}
