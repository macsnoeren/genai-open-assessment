<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/StudentAnswer.php';
require_once __DIR__ . '/AnswerAssessment.php';

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

  /**
   * Het eindresultaat van één toetspoging. Dit is de enige plek waar het
   * eindcijfer wordt berekend (docentweergave, resultatenlijst, student,
   * integratie-API). Beide schalen geven dezelfde vorm:
   * - scale: points of levels
   * - graded / total: aantal beoordeelde antwoorden / aantal antwoorden van de poging
   * - computed: berekend cijfer (float) of null. Bij levels pas als elk antwoord
   *   een docentniveau heeft (B6); bij points het gemiddelde van de docentscores
   * - override: handmatig eindcijfer (float) of woord (string), of null
   * - final: override als die er is, anders computed
   * - label: woord als de toets de woordbeoordeling toont (bij een override met
   *   een woord dat woord), anders null
   * - override_outdated: true als het berekende cijfer sinds de aanpassing is veranderd
   * Daarnaast voor de docentweergave: show_label, scheme, override_reason,
   * override_by_name, override_at en override_basis. De student ziet alleen
   * final of label (B8).
   */
  public static function attemptResult(int $studentExamId): array {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT se.id, se.grade_override, se.grade_override_label, se.grade_override_reason,
               se.grade_override_at, se.grade_override_basis, ou.name AS override_by_name,
               e.grading_scale, e.show_grade_label,
               gs.id AS scheme_id, gs.name AS scheme_name,
               gs.points_voldoende, gs.points_goed, gs.points_uitstekend
        FROM student_exams se
        JOIN exams e ON se.exam_id = e.id
        LEFT JOIN grading_schemes gs ON e.grading_scheme_id = gs.id
        LEFT JOIN users ou ON se.grade_override_by = ou.id
        WHERE se.id = ?
    ");
    $stmt->execute([$studentExamId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      throw new RuntimeException('Toetspoging niet gevonden.');
    }
    $stmt = $pdo->prepare("SELECT teacher_score, teacher_level FROM student_answers WHERE student_exam_id = ? ORDER BY id ASC");
    $stmt->execute([$studentExamId]);
    return self::resultFromRows($row, $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /** Rekent attemptResult() uit op een rij (poging + toets + schema) en de antwoorden. */
  private static function resultFromRows(array $row, array $answers): array {
    $scale = self::examScale($row);
    $scheme = $row['scheme_id'] !== null ? [
      'id' => (int)$row['scheme_id'],
      'name' => (string)$row['scheme_name'],
      'points_voldoende' => (int)$row['points_voldoende'],
      'points_goed' => (int)$row['points_goed'],
      'points_uitstekend' => (int)$row['points_uitstekend'],
    ] : null;
    $showLabel = $scale === self::SCALE_LEVELS && !empty($row['show_grade_label']);

    $total = count($answers);
    $graded = 0;
    $computed = null;
    if ($scale === self::SCALE_LEVELS) {
      $levels = [];
      foreach ($answers as $answer) {
        $level = self::isLevel($answer['teacher_level'] ?? null) ? $answer['teacher_level'] : null;
        $graded += $level !== null ? 1 : 0;
        $levels[] = $level;
      }
      $computed = $scheme ? self::grade($levels, $scheme) : null;
    } else {
      $scores = [];
      foreach ($answers as $answer) {
        if ($answer['teacher_score'] !== null && $answer['teacher_score'] !== '') {
          $scores[] = (float)$answer['teacher_score'];
        }
      }
      $graded = count($scores);
      $computed = $scores ? self::roundGrade(array_sum($scores) / count($scores)) : null;
    }

    $override = null;
    if (self::isLevel($row['grade_override_label'] ?? null)) {
      $override = $row['grade_override_label'];
    } elseif ($row['grade_override'] !== null && $row['grade_override'] !== '') {
      $override = (float)$row['grade_override'];
    }
    $final = $override ?? $computed;

    $label = null;
    if (is_string($final)) {
      $label = $final;
    } elseif ($showLabel && $final !== null) {
      $label = self::gradeLabel($final);
    }

    $basis = ($row['grade_override_basis'] ?? null) !== null ? (float)$row['grade_override_basis'] : null;
    $outdated = $override !== null && (($basis === null) !== ($computed === null)
                                       || ($basis !== null && abs($basis - $computed) > 0.001));

    return [
      'scale' => $scale,
      'graded' => $graded,
      'total' => $total,
      'computed' => $computed,
      'override' => $override,
      'final' => $final,
      'label' => $label,
      'override_outdated' => $outdated,
      'show_label' => $showLabel,
      'scheme' => $scheme,
      'override_reason' => $override !== null ? (string)($row['grade_override_reason'] ?? '') : null,
      'override_by_name' => $override !== null ? $row['override_by_name'] : null,
      'override_at' => $override !== null ? $row['grade_override_at'] : null,
      'override_basis' => $basis,
    ];
  }

  /**
   * AI-cijfer per bron (model uit ai_feedback of de agentic beoordeling), over de
   * antwoorden waarvoor die bron een niveau heeft (B6), net als het gemiddelde
   * per model bij points.
   * @param array $answers rijen met ai_feedback en agentic_level (zie AnswerAssessment::agenticLevelSql())
   * @return array bron => ['grade' => float, 'count' => aantal antwoorden met een niveau]
   */
  public static function aiGrades(array $answers, array $scheme): array {
    $levelsBySource = [];
    foreach ($answers as $answer) {
      foreach (StudentAnswer::aiLevels($answer['ai_feedback'] ?? null, $answer['agentic_level'] ?? null) as $source => $level) {
        $levelsBySource[$source][] = $level;
      }
    }
    $grades = [];
    foreach ($levelsBySource as $source => $levels) {
      $grade = self::grade($levels, $scheme);
      if ($grade !== null) {
        $grades[$source] = ['grade' => $grade, 'count' => count($levels)];
      }
    }
    ksort($grades);
    return $grades;
  }
}
