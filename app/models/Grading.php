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
 * Rekenregels voor beoordelen met niveaus en puntenschema's. Alle regels voor
 * punten, het eindcijfer en de woordbeoordeling staan hier, zodat ze niet op
 * meerdere plekken gedupliceerd raken (zie ARCHITECTURE.md).
 */
class Grading {

  /** Schaal van een toets: een score 0-10 per antwoord (het oorspronkelijke systeem). */
  public const SCALE_POINTS = 'points';
  /** Schaal van een toets: een niveau per antwoord, via een puntenschema naar een cijfer. */
  public const SCALE_LEVELS = 'levels';
  public const SCALES = [self::SCALE_POINTS, self::SCALE_LEVELS];

  /** Niveaus per antwoord, van laag naar hoog (ook de enum-waarden in database en JSON). */
  public const LEVELS = ['onvoldoende', 'voldoende', 'goed', 'uitstekend'];

  /** True als $value een geldige schaal is. */
  public static function isScale($value): bool {
    return is_string($value) && in_array($value, self::SCALES, true);
  }

  /** Schaal van een toets (rij uit exams); ontbreekt hij, dan points. */
  public static function examScale(?array $exam): string {
    $scale = $exam['grading_scale'] ?? self::SCALE_POINTS;
    return self::isScale($scale) ? $scale : self::SCALE_POINTS;
  }

  /** True als $value een geldig niveau is. */
  public static function isLevel($value): bool {
    return is_string($value) && in_array($value, self::LEVELS, true);
  }

  /** Weergavenaam van een niveau ("Onvoldoende" ...). */
  public static function levelLabel(string $level): string {
    return self::isLevel($level) ? ucfirst($level) : $level;
  }

  /** Bootstrap-kleur voor een badge met dit niveau. */
  public static function levelClass(string $level): string {
    switch ($level) {
      case 'onvoldoende': return 'danger';
      case 'voldoende':   return 'warning';
      case 'goed':        return 'info';
      case 'uitstekend':  return 'success';
      default:            return 'secondary';
    }
  }

  /** Positie van een niveau (0 = onvoldoende ... 3 = uitstekend), of null. */
  public static function levelIndex(?string $level): ?int {
    $index = $level === null ? false : array_search($level, self::LEVELS, true);
    return $index === false ? null : $index;
  }

  /** Punten voor een niveau volgens het schema. Onvoldoende is altijd 0 punten. */
  public static function pointsFor(string $level, array $scheme): int {
    if ($level === 'onvoldoende' || !self::isLevel($level)) {
      return 0;
    }
    return (int)$scheme['points_' . $level];
  }

  /**
   * Cijfer 0-10 met één decimaal: 10 × som van de punten / (aantal × punten voor
   * uitstekend). Null als de lijst leeg is of een antwoord nog geen niveau heeft.
   */
  public static function grade(array $levels, array $scheme): ?float {
    if (!$levels || in_array(null, $levels, true)) {
      return null;
    }
    $max = (int)$scheme['points_uitstekend'];
    if ($max <= 0) {
      return null;
    }
    $sum = 0;
    foreach ($levels as $level) {
      $sum += self::pointsFor((string)$level, $scheme);
    }
    return self::roundGrade(10 * $sum / (count($levels) * $max));
  }

  /** Rondt een cijfer af op één decimaal (half naar boven), minimaal 0 en hooguit 10. */
  public static function roundGrade(float $grade): float {
    // Kleine marge tegen floatfouten (6,05 moet 6,1 worden, niet 6,0)
    $rounded = floor($grade * 10 + 0.5 + 1e-9) / 10;
    return max(0.0, min(10.0, $rounded));
  }

  /**
   * Woordbeoordeling van een eindcijfer (vaste mapping voor alle toetsen): eerst
   * afronden op een geheel getal (half naar boven), dan 0-5 onvoldoende, 6-7
   * voldoende, 8-9 goed en 10 uitstekend.
   */
  public static function gradeLabel(float $grade): string {
    $whole = (int)floor($grade + 0.5 + 1e-9);
    if ($whole >= 10) return 'uitstekend';
    if ($whole >= 8)  return 'goed';
    if ($whole >= 6)  return 'voldoende';
    return 'onvoldoende';
  }

  /** Nederlandse notatie met één decimaal ("7,5"). */
  public static function formatGrade(float $grade): string {
    return number_format($grade, 1, ',', '');
  }

  /**
   * Controleert een puntenschema. Geeft een Nederlandse foutmelding, of null als
   * het schema geldig is: naam 1-100 tekens, gehele getallen, 0 < V < G < U ≤ 100.
   */
  public static function validateScheme(string $name, $v, $g, $u): ?string {
    $length = mb_strlen(trim($name), 'UTF-8');
    if ($length < 1 || $length > 100) {
      return 'De naam is verplicht en hooguit 100 tekens.';
    }
    foreach ([$v, $g, $u] as $points) {
      if (!is_int($points)) {
        return 'De punten moeten gehele getallen zijn.';
      }
    }
    if (!(0 < $v && $v < $g && $g < $u && $u <= 100)) {
      return 'De punten moeten oplopen: 0 < voldoende < goed < uitstekend ≤ 100.';
    }
    return null;
  }
}
