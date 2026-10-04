<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * Puntenschema's: zetten de niveaus van een toets met grading_scale levels om in
 * punten (onvoldoende is altijd 0). De rekenregels staan in Grading.
 */
class GradingScheme {

  /** Alle schema's op naam, met de naam van de eigenaar (NULL bij het systeemschema) en het aantal toetsen. */
  public static function all(): array {
    $pdo = Database::connect();
    $stmt = $pdo->query("
        SELECT gs.*, u.name AS owner_name,
               (SELECT COUNT(*) FROM exams e WHERE e.grading_scheme_id = gs.id) AS exam_count
        FROM grading_schemes gs
        LEFT JOIN users u ON gs.owner_id = u.id
        ORDER BY gs.name COLLATE NOCASE ASC, gs.id ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function find($id) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM grading_schemes WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  /** Het systeemschema (zonder eigenaar) met de laagste id: de standaardkeuze bij een nieuwe toets. */
  public static function defaultScheme() {
    $pdo = Database::connect();
    $stmt = $pdo->query("SELECT * FROM grading_schemes WHERE owner_id IS NULL ORDER BY id ASC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  /** Schema met precies deze punten (de combinatie is UNIQUE), of false. */
  public static function findByPoints(int $v, int $g, int $u) {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT * FROM grading_schemes
        WHERE points_voldoende = ? AND points_goed = ? AND points_uitstekend = ?
    ");
    $stmt->execute([$v, $g, $u]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function create(string $name, int $v, int $g, int $u, ?int $ownerId): int {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        INSERT INTO grading_schemes (name, points_voldoende, points_goed, points_uitstekend, owner_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $v, $g, $u, $ownerId]);
    return (int)$pdo->lastInsertId();
  }

  public static function update(int $id, string $name, int $v, int $g, int $u): void {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        UPDATE grading_schemes
        SET name = ?, points_voldoende = ?, points_goed = ?, points_uitstekend = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$name, $v, $g, $u, $id]);
  }

  public static function delete(int $id): void {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("DELETE FROM grading_schemes WHERE id = ?");
    $stmt->execute([$id]);
  }

  /** Aantal toetsen dat dit schema gebruikt. */
  public static function usageCount(int $id): int {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE grading_scheme_id = ?");
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn();
  }

  /**
   * True als minstens één toets met dit schema een ingeleverde poging heeft. Het
   * schema kan dan niet meer worden gewijzigd: de cijfers van die pogingen zouden
   * ongemerkt veranderen.
   */
  public static function isLocked(int $id): bool {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
        SELECT 1 FROM exams e
        JOIN student_exams se ON se.exam_id = e.id
        WHERE e.grading_scheme_id = ? AND se.completed_at IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$id]);
    return (bool)$stmt->fetchColumn();
  }

  /**
   * True als de huidige gebruiker dit schema mag wijzigen of verwijderen: de admin
   * altijd, een docent alleen een eigen schema. Een systeemschema (owner_id NULL)
   * alleen de admin.
   */
  public static function canManage(array $scheme): bool {
    if (($_SESSION['role'] ?? '') === 'admin') {
      return true;
    }
    return $scheme['owner_id'] !== null && (int)$scheme['owner_id'] === (int)($_SESSION['user_id'] ?? 0);
  }
}
