<?php

require_once __DIR__ . '/../models/ApiKey.php';

class ApiKeyController {
  
  public function index() {
    requireRole('docent');
    $keys = ApiKey::all();
    require __DIR__ . '/../views/docent/api_keys.php';
  }
  
  public function create() {
    requireRole('docent');
    
    $newKey = ApiKey::create($_POST['name']);
    $_SESSION['new_api_key'] = $newKey;
    
    header("Location: /?action=api_keys");
    exit;
  }
  
  public function toggle() {
    requireRole('docent');
    ApiKey::toggle($_GET['id']);
    header("Location: /?action=api_keys");
    exit;
  }
  
  public function delete() {
    requireRole('docent');
    ApiKey::delete($_GET['id']);
    header("Location: /?action=api_keys");
    exit;
  }
}
?>
