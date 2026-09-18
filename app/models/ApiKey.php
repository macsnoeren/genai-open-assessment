<?php

require_once __DIR__ . '/../../config/database.php';

class ApiKey {

  /** Keys worden als SHA-256 hash opgeslagen; de ruwe key wordt alleen bij aanmaken getoond. */
  public static function hashKey(string $key): string {
    return hash('sha256', $key);
  }
  
  public static function all() {
    $pdo = Database::connect();
    return $pdo->query(
		       "SELECT id, name, api_key, active, created_at
		       FROM api_keys
		       ORDER BY created_at DESC"
		       )->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public static function create($name) {
    $key = bin2hex(random_bytes(32));
    
    $pdo = Database::connect();
    $stmt = $pdo->prepare(
			  "INSERT INTO api_keys (name, api_key) VALUES (?, ?)"
			  );
    $stmt->execute([$name, self::hashKey($key)]);
    
    return $key; // ← alleen nu teruggeven!
  }

  /**
   * Zoekt een actieve key op basis van de ruwe key.
   * Keys die nog in platte tekst zijn opgeslagen (oude versie) worden bij het
   * eerste gebruik transparant omgezet naar een hash.
   * @return array|null rij met id en name, of null.
   */
  public static function findActiveByKey(string $rawKey): ?array {
    if ($rawKey === '' || !preg_match('/^[a-f0-9]{64}$/', $rawKey)) {
      return null;
    }
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT id, name FROM api_keys WHERE api_key = ? AND active = 1");
    $stmt->execute([self::hashKey($rawKey)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      return $row;
    }

    // Legacy: platte opslag -> upgraden naar hash
    $stmt->execute([$rawKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $pdo->prepare("UPDATE api_keys SET api_key = ? WHERE id = ?")->execute([self::hashKey($rawKey), $row['id']]);
      return $row;
    }
    return null;
  }
  
  public static function toggle($id) {
    $pdo = Database::connect();
    $pdo->prepare(
		  "UPDATE api_keys SET active = 1 - active WHERE id = ?"
		  )->execute([$id]);
  }
  
  public static function delete($id) {
    $pdo = Database::connect();
    $pdo->prepare(
		  "DELETE FROM api_keys WHERE id = ?"
		  )->execute([$id]);
  }
}
