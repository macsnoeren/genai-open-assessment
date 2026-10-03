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
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Integration.php';
require_once __DIR__ . '/../models/IntegrationAttempt.php';
require_once __DIR__ . '/../models/IntegrationEvent.php';

/**
 * Class IntegrationController
 * Beheer van externe koppelingen (alleen admin). Zie ARCHITECTURE.md §6.9.
 */
class IntegrationController {

  /** Lijst van alle koppelingen. */
  public function index() {
    requireRole('admin');
    $integrations = Integration::all();
    require __DIR__ . '/../views/docent/integrations.php';
  }

  /** Formulier voor een nieuwe koppeling. */
  public function create() {
    requireRole('admin');
    $integration = null;
    $selectedExamIds = [];
    $exams = Exam::all();
    $action = 'integration_store';
    $title = 'Nieuwe koppeling';
    require __DIR__ . '/../views/docent/integration_form.php';
  }

  public function store() {
    validateCsrfToken();
    requireRole('admin');

    $data = $this->validatedInput(null, '/?action=integration_create');
    $examIds = $this->validatedExamIds(null, '/?action=integration_create');

    $created = Integration::create($data, (int)$_SESSION['user_id']);
    Integration::setExams($created['id'], $examIds);
    AuditLog::log('integration_create', [
        'id' => $created['id'],
        'name' => $data['name'],
        'return_origin' => $data['return_origin'],
        'webhook_url' => $data['webhook_url'],
        'min_confidence' => $data['min_confidence'],
        'exam_ids' => $examIds,
    ]);

    // Eenmalig tonen: daarna zijn de key (alleen als hash opgeslagen) en het geheim niet meer te zien.
    $_SESSION['new_integration_credentials'] = [
        'name' => $data['name'],
        'key' => $created['key'],
        'secret' => $created['secret'],
    ];
    header('Location: /?action=integration_view&id=' . $created['id']);
    exit;
  }

  public function edit() {
    requireRole('admin');
    $integration = $this->findOrAbort(requestInt($_GET, 'id'));
    $selectedExamIds = Integration::examIds($integration['id']);
    $exams = Exam::all();
    $action = 'integration_update';
    $title = 'Koppeling wijzigen';
    require __DIR__ . '/../views/docent/integration_form.php';
  }

  public function update() {
    validateCsrfToken();
    requireRole('admin');
    $integration = $this->findOrAbort(requestInt($_POST, 'id'));
    $id = (int)$integration['id'];
    $back = '/?action=integration_edit&id=' . $id;

    $data = $this->validatedInput($id, $back);
    $examIds = $this->validatedExamIds($id, $back);
    $oldExamIds = Integration::examIds($id);

    Integration::update($id, $data);
    Integration::setExams($id, $examIds);

    $changes = ['id' => $id];
    foreach (['name', 'return_origin', 'webhook_url', 'min_confidence'] as $field) {
        if ((string)($integration[$field] ?? '') !== (string)($data[$field] ?? '')) {
            $changes[$field] = ['old' => $integration[$field], 'new' => $data[$field]];
        }
    }
    sort($examIds);
    if ($oldExamIds !== $examIds) {
        $changes['exam_ids'] = ['old' => $oldExamIds, 'new' => $examIds];
    }
    AuditLog::log('integration_update', $changes);

    $_SESSION['success_message'] = 'Koppeling opgeslagen.';
    header('Location: /?action=integration_view&id=' . $id);
    exit;
  }

  /** Nieuw webhookgeheim; het oude werkt direct niet meer. */
  public function rotateSecret() {
    validateCsrfToken();
    requireRole('admin');
    $integration = $this->findOrAbort(requestInt($_GET, 'id') ?? requestInt($_POST, 'id'));

    $secret = Integration::rotateSecret($integration['id']);
    AuditLog::log('integration_rotate_secret', ['id' => (int)$integration['id'], 'name' => $integration['name']]);
    $_SESSION['new_integration_credentials'] = [
        'name' => $integration['name'],
        'key' => null,
        'secret' => $secret,
    ];
    header('Location: /?action=integration_view&id=' . (int)$integration['id']);
    exit;
  }

  /** Aan/uit via de API-key van de koppeling. Uit: de API geeft 401 en startlinks werken niet meer. */
  public function toggle() {
    validateCsrfToken();
    requireRole('admin');
    $integration = $this->findOrAbort(requestInt($_GET, 'id') ?? requestInt($_POST, 'id'));

    ApiKey::toggle($integration['api_key_id']);
    AuditLog::log('integration_toggle', [
        'id' => (int)$integration['id'],
        'name' => $integration['name'],
        'active' => $integration['active'] ? 0 : 1,
    ]);
    header('Location: /?action=integrations');
    exit;
  }

  /** Verwijdert de koppeling (en de key). De pogingen blijven als gastpogingen bestaan. */
  public function delete() {
    validateCsrfToken();
    requireRole('admin');
    $integration = $this->findOrAbort(requestInt($_GET, 'id') ?? requestInt($_POST, 'id'));

    AuditLog::log('integration_delete', ['id' => (int)$integration['id'], 'name' => $integration['name']]);
    Integration::delete($integration['id']);
    $_SESSION['success_message'] = 'Koppeling verwijderd. De pogingen blijven als gastpogingen bestaan.';
    header('Location: /?action=integrations');
    exit;
  }

  /** Detailpagina: gegevens, toetsen, recente events en pogingen. */
  public function view() {
    requireRole('admin');
    $integration = $this->findOrAbort(requestInt($_GET, 'id'));
    $id = (int)$integration['id'];

    $linkedExams = Integration::exams($id);
    $events = IntegrationEvent::recentByIntegration($id, 50);
    $eventCounts = IntegrationEvent::counts($id);
    $attempts = IntegrationAttempt::recentByIntegration($id, 50);
    $apiUrl = appBaseUrl() . '/api/index.php';

    $credentials = $_SESSION['new_integration_credentials'] ?? null;
    unset($_SESSION['new_integration_credentials']);

    require __DIR__ . '/../views/docent/integration_view.php';
  }

  // ---------------------------------------------------------------------

  private function findOrAbort(?int $id): array {
    $integration = $id !== null ? Integration::find($id) : null;
    if (!$integration) {
        abort(404, 'Koppeling niet gevonden.');
    }
    return $integration;
  }

  /** Invoerfout: melding tonen en terug naar het formulier. */
  private function failForm(string $message, string $back): void {
    $_SESSION['error'] = $message;
    header('Location: ' . $back);
    exit;
  }

  /**
   * Leest en valideert naam, origin, webhook-URL en drempel.
   * @return array name, return_origin, webhook_url (of null), min_confidence
   */
  private function validatedInput(?int $id, string $back): array {
    $name = trim(requestString($_POST, 'name', MAX_NAME_LENGTH));
    if ($name === '') {
        $this->failForm('Naam is verplicht.', $back);
    }
    if (Integration::nameExists($name, $id)) {
        $this->failForm('Er bestaat al een koppeling met deze naam.', $back);
    }

    $origin = Integration::normalizeOrigin(requestString($_POST, 'return_origin', Integration::MAX_URL_LENGTH));
    if ($origin === null) {
        $this->failForm('Ongeldige origin. Gebruik de vorm https://leeromgeving.example (zonder pad), alleen https.', $back);
    }

    $webhookUrl = trim(requestString($_POST, 'webhook_url', Integration::MAX_URL_LENGTH + 1));
    if ($webhookUrl === '') {
        $webhookUrl = null;
    } elseif (!Integration::validWebhookUrl($webhookUrl)) {
        $this->failForm('Ongeldige webhook-URL. Alleen https, zonder gebruikersnaam of wachtwoord in de URL en zonder #.', $back);
    }

    $minConfidence = requestString($_POST, 'min_confidence', 10, 'hoog');
    if (!in_array($minConfidence, Integration::CONFIDENCES, true)) {
        $this->failForm('Ongeldige drempel voor de confidence.', $back);
    }

    return [
        'name' => $name,
        'return_origin' => $origin,
        'webhook_url' => $webhookUrl,
        'min_confidence' => $minConfidence,
    ];
  }

  /**
   * Toets-id's uit de checkboxes. Alleen bestaande toetsen met AI-beoordeling
   * aan; een toets die al gekoppeld was mag blijven, ook als AI intussen uit staat.
   */
  private function validatedExamIds(?int $id, string $back): array {
    $posted = $_POST['exam_ids'] ?? [];
    if (!is_array($posted)) {
        $this->failForm('Ongeldige toetsselectie.', $back);
    }
    $linked = $id !== null ? Integration::examIds($id) : [];
    $examIds = [];
    foreach (array_keys($posted) as $key) {
        $examId = requestInt($posted, (string)$key);
        $exam = $examId !== null ? Exam::find($examId) : null;
        if (!$exam) {
            $this->failForm('Onbekende toets geselecteerd.', $back);
        }
        if (empty($exam['ai_grading_enabled']) && !in_array($examId, $linked, true)) {
            $this->failForm('Toets "' . $exam['title'] . '" heeft geen AI-beoordeling. Zet eerst AI-beoordeling aan.', $back);
        }
        $examIds[$examId] = $examId;
    }
    return array_values($examIds);
  }
}
