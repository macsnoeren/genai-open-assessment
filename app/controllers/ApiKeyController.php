<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';
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
    validateCsrfToken();
    requireRole('admin');
    
    $name = trim(requestString($_POST, 'name', MAX_NAME_LENGTH));
    if ($name === '') {
        abort(400, 'Naam is verplicht.');
    }
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
    validateCsrfToken();
    requireRole('admin');
    $id = requestInt($_GET, 'id') ?? requestInt($_POST, 'id');
    if ($id === null) {
        abort(400, 'Ongeldig verzoek.');
    }
    AuditLog::log('api_key_toggle', ['id' => $id]);
    ApiKey::toggle($id);
    header("Location: /?action=api_keys");
    exit;
  }
  
  /**
   * Deletes an API key.
   */
  public function delete() {
    validateCsrfToken();
    requireRole('admin');
    $id = requestInt($_GET, 'id') ?? requestInt($_POST, 'id');
    if ($id === null) {
        abort(400, 'Ongeldig verzoek.');
    }
    AuditLog::log('api_key_delete', ['id' => $id]);
    ApiKey::delete($id);
    header("Location: /?action=api_keys");
    exit;
  }
}
