<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../models/ApiKey.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Class ApiKeyController
 * Manages API keys for external services.
 */
class ApiKeyController {
  
  /**
   * Lists all API keys.
   */
  public function index() {
    requireRole('admin');
    $keys = ApiKey::all();
    require __DIR__ . '/../views/docent/api_keys.php';
  }
  
  /**
   * Creates a new API key.
   */
  public function create() {
    requireRole('admin');
    
    $name = $_POST['name'];
    $newKey = ApiKey::create($name);
    AuditLog::log('api_key_create', ['name' => $name]);
    $_SESSION['new_api_key'] = [
        'name' => $name,
        'key' => $newKey
    ];
    
    header("Location: /?action=api_keys");
    exit;
  }
  
  /**
   * Toggles the active status of an API key.
   */
  public function toggle() {
    requireRole('admin');
    AuditLog::log('api_key_toggle', ['id' => $_GET['id']]);
    ApiKey::toggle($_GET['id']);
    header("Location: /?action=api_keys");
    exit;
  }
  
  /**
   * Deletes an API key.
   */
  public function delete() {
    requireRole('admin');
    AuditLog::log('api_key_delete', ['id' => $_GET['id']]);
    ApiKey::delete($_GET['id']);
    header("Location: /?action=api_keys");
    exit;
  }
}
?>
