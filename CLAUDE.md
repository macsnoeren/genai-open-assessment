# CLAUDE.md

Werkinstructies voor Claude Code in deze repository. De volledige uitleg van de opzet staat in [ARCHITECTURE.md](ARCHITECTURE.md). Lees die eerst bij grotere wijzigingen, nieuwe features of een nieuwe applicatie op basis van deze code.

## Wat dit is

Een webapplicatie voor toetsen met open vragen die door generatieve AI worden voorbeoordeeld, op basis van rubrics die de docent opstelt. Er zijn twee componenten die apart worden gedeployd:

- **Webapp:** PHP 8.2 zonder framework of Composer, SQLite via PDO, Bootstrap 5. Code in `htdocs/` (documentroot), `app/`, `config/` en `setup/`.
- **AI-worker:** `bin/process_ai_feedback.py` (Python 3 + `requests`). Haalt via `htdocs/api/index.php` antwoorden op, laat ze door Ollama-modellen beoordelen en stuurt de feedback terug.
- **Ontwerp-worker:** `bin/process_design_jobs.py` + `bin/design_agents.py`. De AI-vraagontwerper: drie agents (analyse, rubricvoorstel, validatie) werken een vraag van de docent uit tot een rubric (ARCHITECTURE §6.6). Draait als tweede proces.
- **Assessment-worker:** `bin/process_assessment_jobs.py` + `bin/assessment_agents.py`. Agentic beoordelen: drie agents (evidence, assessment, validatie) en een deterministische `decide()` beoordelen een antwoord op een rubric-vraag. Dat is een AI-beoordeling naast `ai_feedback`, nooit een docentbeoordeling (ARCHITECTURE §6.8). Draait als derde proces.
- **Externe koppeling:** een andere website laat haar deelnemers via een eenmalige startlink een toets maken en volgt de status via de integratie-API (keys met scope `integration`) en ondertekende webhooks (ARCHITECTURE §6.9, `docs/integration-api.md`, demo in `docs/integration-demo/`). Alleen webapp; de workers merken er niets van.

De UI-teksten, codecommentaar en docs zijn in het **Nederlands**; klassen, methodes en variabelen in het Engels. Houd dat zo.

## Commando's

```bash
# Lokaal draaien (http://localhost:8080, login admin@school.nl / admin123)
./docker/start.sh

# De image KOPIEERT de code (geen bind mount): na elke wijziging opnieuw bouwen
./docker/start.sh                                  # of: cd docker && docker compose up --build

# Schone database
cd docker && docker compose down -v

# PHP-syntaxcheck (er is lokaal geen PHP: via Docker)
docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c 'find app config htdocs setup -name "*.php" -print0 | xargs -0 -n1 php -l' | grep -v "^No syntax errors"

# Python-syntaxcheck
python3 -m py_compile bin/process_ai_feedback.py bin/dataset_import.py bin/process_design_jobs.py bin/design_agents.py bin/test_design_agents.py bin/test_rubric_grading.py bin/process_assessment_jobs.py bin/assessment_agents.py bin/test_assessment_agents.py docs/integration-demo/demo_site.py

# Mocktests van de vraagontwerper (gemockte call_ollama, vereist bin/config.py)
cd bin && python3 -m unittest test_design_agents -v

# Mocktests van de rubric-beoordeling in de AI-worker
cd bin && python3 -m unittest test_rubric_grading -v

# Mocktests van agentic beoordelen (agents, decide(), orchestrator)
cd bin && python3 -m unittest test_assessment_agents -v

# Worker starten (vereist bin/config.py, zie bin/config.py.sample)
cd bin && python process_ai_feedback.py
cd bin && python process_design_jobs.py   # ontwerp-worker, tweede proces
cd bin && python process_assessment_jobs.py   # assessment-worker, derde proces
```

Er is **geen geautomatiseerde testsuite** voor de webapp (alleen de mocktests van `bin/design_agents.py`, `bin/assessment_agents.py` en de rubric-beoordeling). Controleer wijzigingen met de syntaxchecks hierboven en een handmatige rooktest in de Docker-omgeving (inloggen, de gewijzigde flow doorlopen, en voor muterende acties ook controleren dat een GET een 405 geeft).

## Architectuur in het kort

- Elke pagina is `/?action=<naam>`. De `switch` in `htdocs/index.php` roept een controllermethode aan. **Een nieuwe action is altijd ook een nieuwe `case`.**
- **Controllers** (`app/controllers/`): één publieke methode per action. **Models** (`app/models/`): alleen statische methodes, prepared statements, arrays terug. **Views** (`app/views/`): `ob_start()`, daarna `$content = ob_get_clean()` en `require` van `layouts/main.php`.
- Create en edit delen één formulier (`*_form.php`). De controller zet `$action`, `$title` en het object (of `null`).
- De database is de wachtrij voor de worker: een antwoord met lege `ai_feedback`, een ingeleverde poging en `exams.ai_grading_enabled = 1` staat klaar voor AI-beoordeling. De vraagontwerper en agentic beoordelen hebben een eigen tabel als wachtrij (`question_designs`, `answer_assessments`). Een antwoord op een rubric-vraag gaat automatisch naar agentic beoordelen en **niet** naar de AI-worker (`AGENTIC_AUTO_ASSESSMENT`, `AnswerAssessment::excludeFromAiGradingSql()`); alleen na een mislukte agentic run valt het terug op de AI-worker.

## Verplichte patronen (beveiliging)

Elke muterende controllermethode begint zo, in deze volgorde:

```php
validateCsrfToken();              // eist POST, anders 405; ongeldig token geeft 403
requireRole('docent');            // admin > docent > beoordelaar; student apart
$id = requestInt($_POST, 'id');   // nooit $_POST/$_GET rechtstreeks gebruiken
$this->checkExamOwnership($id, true);  // objectautorisatie: lezen (false) of schrijven (true)
```

En verder:

- **Output:** altijd `e($value)` in views. Formulieren krijgen `<?= csrfInput() ?>`.
- **Invoer:** `requestInt()` en `requestString($src, $key, $maxLen)`. Ongeldige invoer leidt tot `abort(400|403|404, 'Nederlandse melding')`. Gebruik nooit `die()` of `exit` met tekst.
- **Autorisatie:** baseer de check op data uit de database (bijvoorbeeld `StudentAnswer::findWithExam($id)` → `exam_id`), nooit op ids die de client meestuurt.
- **Muterende links** (verwijderen, togglen, dupliceren) krijgen `data-confirm="..."`. De layout zet zo'n link om naar een POST met CSRF-token. Maak nooit een muterende GET.
- **Scripts:** inline `<script>` alleen met `nonce="<?= e(cspNonce()) ?>"`. Geen `onclick=` en andere inline handlers (de CSP blokkeert ze), gebruik data-attributen en een event listener. Externe scripts alleen van `cdn.jsdelivr.net` of `cdnjs.cloudflare.com`, met `integrity`.
- **SQL:** alleen prepared statements. Een `LIMIT` alleen met `(int)`-cast of `bindValue(..., PDO::PARAM_INT)`.
- **API:** elk API-endpoint roept `verifyApiKey()` aan met de juiste scope: `ApiKey::SCOPE_WORKER` voor de worker-endpoints, en voor de integratie-endpoints `requireIntegration()` (scope `integration`), waarna elke query filtert op de koppeling van de key. Een object van een andere koppeling geeft 404.
- **Audit:** elke relevante wijziging krijgt `AuditLog::log('actie_naam', [...details])`. De audit log is ook de bron voor rate limiting (`AuditLog::countRecent`).
- **CSV-export:** tekstkolommen altijd via `csvSafe()`.
- **Redirects:** gebruik `header('Location: /?action=...'); exit;` (Post/Redirect/Get). Gebruikersmeldingen gaan via `$_SESSION['error']` of `$_SESSION['success_message']`.

## Contracten die niet ongemerkt mogen breken

1. **Het tekstformaat van `ai_feedback`:** de worker schrijft blokken met `Model: …`, `Tijdsduur: …`, `Aantal punten: N` en `Feedback: …`. De PHP-code leest de scores uit met de regex `/Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)/is` op één plek: `StudentAnswer::aiScores()`, die er ook de agentic score als bron `Agentic AI` bij voegt. Daarnaast leest `StudentAnswer::hasInjectionWarning()` het formaat (de tekst begint met `WAARSCHUWING:`) voor de confidence van de externe koppeling. Verander je de labels, pas dan die regex aan, en ook `clean_output_text()` in de worker (die neutraliseert deze labels in modeluitvoer tegen score-spoofing).
2. **De API tussen webapp en worker:** de key gaat via `Authorization: Bearer` (nooit via de query string) en wordt als SHA-256-hash opgeslagen. Endpoints: `open_student_answers` (GET) en `submit_ai_feedback` (POST JSON). Een wijziging hieraan vereist een gecoördineerde uitrol van beide kanten. Beschrijf die in `docs/`, naar het voorbeeld van `docs/rollout-new-version.md`.
3. **Het schema:** werk bij een wijziging **beide** paden bij: `setup/schema.sql` (nieuwe databases) **en** `Database::migrate()` in `config/database.php` (bestaande databases, idempotent via `PRAGMA table_info`). Kies defaults die veilig zijn voor bestaande rijen.
4. **Scores:** de AI mag alleen `{0, 1, 5, 10}` geven (`ALLOWED_SCORES` en het JSON-schema in de worker). De docentscore is een geheel getal van 0 t/m 10. Het eindcijfer is het gemiddelde van de docentscores.
5. **Rollen** (`student`, `docent`, `beoordelaar`, `admin`) staan op meerdere plekken: de schema-`CHECK`, `validRoles()`, `requireRole()`, de navigatie in `layouts/main.php` en drie redirect-per-rol-functies. Pas ze altijd allemaal tegelijk aan.
6. **De JSON-vormen van de ontwerp-agents** (analyse, rubric, assessment, validatie) staan aan beide kanten: `QuestionDesign::normalize*()` in PHP en de JSON-schema's plus `validate_*()` in `bin/design_agents.py`, met dezelfde limieten (tekstvelden ≤ 800 tekens, lijstmaxima, `checks` precies zes, `weight` en `check` uit een vaste lijst). Verander je een veld of limiet, pas dan beide kanten aan, en ook de endpoints `open_design_jobs`/`submit_design_result` als de envelop verandert. Statusovergangen gaan altijd via `WHERE status = ? AND revision = ?` in `QuestionDesign`.
7. **Het rubric-tekstformaat in `questions.criteria`:** `QuestionDesign::rubricToCriteriaText()` schrijft de kopjes `Modelantwoord:`, `Beoordelingscriteria:`, `Puntentoekenning:` en `Ook correct:`, regels `- [essentieel|aanvullend] naam: beschrijving` en `10 punten:`/`5 punten:`/`1 punt:`/`0 punten:`. `parse_rubric_criteria()` in de worker herkent die opbouw en beoordeelt dan per criterium (ARCHITECTURE §6.7). Verander je het formaat, pas dan de parser aan en maak `bin/fixtures/criteria_rubric.txt` opnieuw aan.
8. **De JSON-vormen van de assessment-agents** (rubric, evidence, assessment, validation, decision, run_log) staan aan beide kanten: `AnswerAssessment::normalize*()` in PHP en de JSON-schema's plus `validate_*()` in `bin/assessment_agents.py`, met dezelfde limieten (tekst ≤ 800 tekens, citaat ≤ 300, `model_answer` ≤ 4000, criteria 1–10 met elk `nr` precies één keer, citaten 0–3, `issues`/`corrections`/`reasons` 0–10, `checks` precies zeven, `rounds` 1–3, enums `evidence_found`/`status`/`confidence`/`agreement` en `score` uit `{0, 1, 5, 10}`). De citaatnormalisatie staat ook dubbel: `verify_quotes()` in de worker en `AnswerAssessment::quoteFound()` in PHP. Verander je een veld of limiet, pas dan beide kanten aan, de fixtures in `bin/fixtures/assessment/`, en de endpoints `open_assessment_jobs`/`submit_assessment_result` als de envelop verandert. De worker mag alleen een run met status `pending` bijwerken (`WHERE id = ? AND status = 'pending'`); het agentic resultaat komt nooit in `teacher_score`/`teacher_feedback` (die zijn altijd van een mens) en de student ziet ervan alleen de score en de feedback (`AnswerAssessment::studentSummary()`).

9. **De integratie-API en de webhooks** (`docs/integration-api.md`, ARCHITECTURE §6.9). Deze afspraak is met **externe partijen**: een wijziging breekt hun integratie, dus alleen additief of met een overgangsperiode.
   - Endpoints (scope `integration`): `integration_exams`, `integration_attempt_start` (idempotent per `external_ref`: 201 nieuw, 200 nieuwe startlink, 409 andere toets of al ingeleverd), `integration_attempt`, `integration_attempts` (`open`/`needs_review`/`all`) en `integration_attempt_review`. De JSON-vormen staan in `IntegrationAttempt::summary()`, `answerResult()` en `listForIntegration()`.
   - Webhook: POST met `X-Assessment-Event`, `X-Assessment-Timestamp` en `X-Assessment-Signature: sha256=HMAC-SHA256(secret, timestamp + "." + body)`; body `{event_id, event, attempt_id, external_ref, status, review_needed, occurred_at}`, zonder toetsinhoud. Events `attempt.submitted`, `attempt.graded`, `attempt.reviewed`, elk één keer per poging (`UNIQUE (student_exam_id, event)`), at-least-once.
   - Terugkeer-URL: `return_url` plus `attempt_id`, `external_ref` en `status` (`submitted` of `in_progress`), zonder scores; de origin moet exact `return_origin` zijn.
   - De status wordt berekend, niet opgeslagen (B6: `not_started`, `in_progress`, `grading`, `graded`, `reviewed`), en confidence/`review_needed` volgen vaste regels (B7, tabel in ARCHITECTURE §6.9). Verander je die regels, pas dan ook `docs/integration-api.md` aan.
   - `teacher_score` komt alleen van een mens: een docent hier, of de beoordelaar van de externe website via `integration_attempt_review`.

## Valkuilen

- **`bin/config.py` is gitignored en de worker draait op een aparte machine met een eigen `config.py`.** Een wijziging aan de lokale `config.py` bereikt die machine niet. Lees nieuwe instellingen daarom altijd met `getattr(config, "NAAM", default)`, documenteer ze in `bin/config.py.sample` en `bin/README.md`, en vermeld expliciet wat de gebruiker op de worker-machine moet aanpassen.
- **Worker testen:** mock `call_ollama()` voor functionele tests (zie `bin/test_design_agents.py`, die `design_agents.call_ollama` patcht). Live tests alleen met een cloud-model (bijvoorbeeld `gpt-oss:120b-cloud` via de lokale Ollama), **niet met lokale modellen** zoals `qwen3:4b`: die zijn traag en laten de machine vastlopen.
- **Ongebruikte bestanden:** `views/docent/exam_create.php`, `exam_edit.php`, `question_create.php`, `question_edit.php`, `student_create.php` en `student_edit.php`, en `models/Student.php`, worden nergens geladen. De actieve formulieren zijn de `*_form.php`-bestanden.
- `models/Questions.php` bevat `class Question` (enkelvoud).
- `StudentController` beheert **alle** gebruikers, niet alleen studenten.
- Wijzig je de prompt van een toets, dan wist `updateExam()` alle AI-feedback van die toets. Dat is bewust zo.
- De audit log is ook de bron voor de login-lockout en alle rate limits. "Log leegmaken" laat daarom het laatste uur staan; verwijder nooit recentere regels.
- `requireLogin()` doet bij elk verzoek een query op `users` (rol, verwijderd, wachtwoord gewijzigd). Zet de sessie na een login of een eigen wachtwoordwijziging altijd via `setSessionUser()` of werk `pw_marker` bij, anders logt de gebruiker zichzelf uit.
- De score-aggregatie (gemiddelden per model) staat op meerdere plekken gedupliceerd. Wijzig je die, wijzig dan alle plekken.
- **Webhooks gaan alleen tijdens worker-polls** (`open_student_answers`, `open_assessment_jobs`, ná het antwoord aan de worker). Zonder draaiende worker gaan er geen webhooks. Test ze lokaal met de demo-site; de Docker-dev zet `INTEGRATION_ALLOW_HTTP=1` (nooit in productie).
- **CSP `form-action`:** browsers passen die ook toe op de redirect na een POST. Een redirect naar een externe origin na een formulier (zoals de terugkeer-URL na inleveren) werkt alleen als die origin in de `form-action` van de pagina met het formulier staat (`sendSecurityHeaders(true, [$origin])`).

## Branches en commits

- Tak af van `main`. Gebruik de naamgeving `dev-<onderwerp>`.
- Commitberichten zijn kort en in het Engels, zoals in de bestaande historie.
- Raakt een wijziging zowel de webapp als de worker (contract of schema), vermeld dan in de PR welke kant eerst moet worden uitgerold en of er een overgangsvlag nodig is.

## Checklist voor een merge naar main

- [ ] PHP- en Python-syntaxcheck slagen (zie Commando's)
- [ ] Rooktest in Docker met een **nieuwe** database (`docker compose down -v`) en, bij een schemawijziging, ook met een bestaande
- [ ] Nieuwe actions hebben een `case` in `htdocs/index.php`, een rolcheck, objectautorisatie en (als ze muteren) `validateCsrfToken()` en `AuditLog::log()`
- [ ] Alle output in views gaat via `e()`, inline scripts hebben een nonce en er zijn geen inline handlers
- [ ] Schemawijziging staat in `schema.sql` **en** in `Database::migrate()`
- [ ] Contract met de worker ongewijzigd, of beide kanten aangepast en de uitrol beschreven
- [ ] Integratie-API en webhooks (contract 9) ongewijzigd of alleen additief, en `docs/integration-api.md` bijgewerkt
- [ ] Nieuwe worker-instellingen hebben een `getattr`-default en staan in `config.py.sample` en `bin/README.md`
- [ ] Docs bijgewerkt: `MANUAL.md` (gebruikersgedrag), `bin/README.md` (worker), `ARCHITECTURE.md` (structuur of contracten), `docs/security-issues.txt` (security-relevante wijzigingen)
- [ ] Alle drie de mocktestsuites slagen (`test_design_agents`, `test_rubric_grading`, `test_assessment_agents`)
