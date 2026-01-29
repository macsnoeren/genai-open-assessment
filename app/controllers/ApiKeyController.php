<?php

require_once __DIR__ . '/../models/ApiKey.php';
require_once __DIR__ . '/../models/AuditLog.php';

class ApiKeyController {
  
  public function index() {
    requireRole('admin');
    $keys = ApiKey::all();
    require __DIR__ . '/../views/docent/api_keys.php';
  }
  
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
  
  public function toggle() {
    requireRole('admin');
    AuditLog::log('api_key_toggle', ['id' => $_GET['id']]);
    ApiKey::toggle($_GET['id']);
    header("Location: /?action=api_keys");
    exit;
  }
  
  public function delete() {
    requireRole('admin');
    AuditLog::log('api_key_delete', ['id' => $_GET['id']]);
    ApiKey::delete($_GET['id']);
    header("Location: /?action=api_keys");
    exit;
  }
}
?>
