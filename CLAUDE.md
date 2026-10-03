# CLAUDE.md

Werkinstructies voor Claude Code in deze repository. De volledige uitleg van de opzet staat in [ARCHITECTURE.md](ARCHITECTURE.md). Lees die eerst bij grotere wijzigingen, nieuwe features of een nieuwe applicatie op basis van deze code.

## Wat dit is

Een webapplicatie voor toetsen met open vragen die door generatieve AI worden voorbeoordeeld, op basis van rubrics die de docent opstelt. Er zijn twee componenten die apart worden gedeployd:

- **Webapp:** PHP 8.2 zonder framework of Composer, SQLite via PDO, Bootstrap 5. Code in `htdocs/` (documentroot), `app/`, `config/` en `setup/`.
- **AI-worker:** `bin/process_ai_feedback.py` (Python 3 + `requests`). Haalt via `htdocs/api/index.php` antwoorden op, laat ze door Ollama-modellen beoordelen en stuurt de feedback terug.
- **Ontwerp-worker:** `bin/process_design_jobs.py` + `bin/design_agents.py`. De AI-vraagontwerper: drie agents (analyse, rubricvoorstel, validatie) werken een vraag van de docent uit tot een rubric (ARCHITECTURE §6.6). Draait als tweede proces.

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
python3 -m py_compile bin/process_ai_feedback.py bin/dataset_import.py bin/process_design_jobs.py bin/design_agents.py bin/test_design_agents.py

# Mocktests van de vraagontwerper (gemockte call_ollama, vereist bin/config.py)
cd bin && python3 -m unittest test_design_agents -v

# Worker starten (vereist bin/config.py, zie bin/config.py.sample)
cd bin && python process_ai_feedback.py
cd bin && python process_design_jobs.py   # ontwerp-worker, tweede proces
```

Er is **geen geautomatiseerde testsuite** voor de webapp (alleen de mocktests van `bin/design_agents.py`). Controleer wijzigingen met de syntaxchecks hierboven en een handmatige rooktest in de Docker-omgeving (inloggen, de gewijzigde flow doorlopen, en voor muterende acties ook controleren dat een GET een 405 geeft).

## Architectuur in het kort

- Elke pagina is `/?action=<naam>`. De `switch` in `htdocs/index.php` roept een controllermethode aan. **Een nieuwe action is altijd ook een nieuwe `case`.**
- **Controllers** (`app/controllers/`): één publieke methode per action. **Models** (`app/models/`): alleen statische methodes, prepared statements, arrays terug. **Views** (`app/views/`): `ob_start()`, daarna `$content = ob_get_clean()` en `require` van `layouts/main.php`.
- Create en edit delen één formulier (`*_form.php`). De controller zet `$action`, `$title` en het object (of `null`).
- De database is de wachtrij voor de worker: een antwoord met lege `ai_feedback`, een ingeleverde poging en `exams.ai_grading_enabled = 1` staat klaar voor AI-beoordeling.

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
- **Audit:** elke relevante wijziging krijgt `AuditLog::log('actie_naam', [...details])`. De audit log is ook de bron voor rate limiting (`AuditLog::countRecent`).
- **CSV-export:** tekstkolommen altijd via `csvSafe()`.
- **Redirects:** gebruik `header('Location: /?action=...'); exit;` (Post/Redirect/Get). Gebruikersmeldingen gaan via `$_SESSION['error']` of `$_SESSION['success_message']`.

## Contracten die niet ongemerkt mogen breken

1. **Het tekstformaat van `ai_feedback`:** de worker schrijft blokken met `Model: …`, `Tijdsduur: …`, `Aantal punten: N` en `Feedback: …`. De PHP-code leest de scores uit met de regex `/Model:\s+(.+?)\s+.*?Aantal punten:\s+(\d+)/is`, op 5 plekken in `DocentController` en `StudentExamController`. Verander je de labels, pas dan alle plekken aan, en ook `clean_output_text()` in de worker (die neutraliseert deze labels in modeluitvoer tegen score-spoofing).
2. **De API tussen webapp en worker:** de key gaat via `Authorization: Bearer` (nooit via de query string) en wordt als SHA-256-hash opgeslagen. Endpoints: `open_student_answers` (GET) en `submit_ai_feedback` (POST JSON). Een wijziging hieraan vereist een gecoördineerde uitrol van beide kanten. Beschrijf die in `docs/`, naar het voorbeeld van `docs/rollout-new-version.md`.
3. **Het schema:** werk bij een wijziging **beide** paden bij: `setup/schema.sql` (nieuwe databases) **en** `Database::migrate()` in `config/database.php` (bestaande databases, idempotent via `PRAGMA table_info`). Kies defaults die veilig zijn voor bestaande rijen.
4. **Scores:** de AI mag alleen `{0, 1, 5, 10}` geven (`ALLOWED_SCORES` en het JSON-schema in de worker). De docentscore is een geheel getal van 0 t/m 10. Het eindcijfer is het gemiddelde van de docentscores.
5. **Rollen** (`student`, `docent`, `beoordelaar`, `admin`) staan op meerdere plekken: de schema-`CHECK`, `validRoles()`, `requireRole()`, de navigatie in `layouts/main.php` en drie redirect-per-rol-functies. Pas ze altijd allemaal tegelijk aan.
6. **De JSON-vormen van de ontwerp-agents** (analyse, rubric, assessment, validatie) staan aan beide kanten: `QuestionDesign::normalize*()` in PHP en de JSON-schema's plus `validate_*()` in `bin/design_agents.py`, met dezelfde limieten (tekstvelden ≤ 800 tekens, lijstmaxima, `checks` precies zes, `weight` en `check` uit een vaste lijst). Verander je een veld of limiet, pas dan beide kanten aan, en ook de endpoints `open_design_jobs`/`submit_design_result` als de envelop verandert. Statusovergangen gaan altijd via `WHERE status = ? AND revision = ?` in `QuestionDesign`.

## Valkuilen

- **`bin/config.py` is gitignored en de worker draait op een aparte machine met een eigen `config.py`.** Een wijziging aan de lokale `config.py` bereikt die machine niet. Lees nieuwe instellingen daarom altijd met `getattr(config, "NAAM", default)`, documenteer ze in `bin/config.py.sample` en `bin/README.md`, en vermeld expliciet wat de gebruiker op de worker-machine moet aanpassen.
- **Worker testen:** mock `call_ollama()` voor functionele tests (zie `bin/test_design_agents.py`, die `design_agents.call_ollama` patcht). Live tests alleen met een cloud-model (bijvoorbeeld `gpt-oss:120b-cloud` via de lokale Ollama), **niet met lokale modellen** zoals `qwen3:4b`: die zijn traag en laten de machine vastlopen.
- **Ongebruikte bestanden:** `views/docent/exam_create.php`, `exam_edit.php`, `question_create.php`, `question_edit.php`, `student_create.php` en `student_edit.php`, en `models/Student.php`, worden nergens geladen. De actieve formulieren zijn de `*_form.php`-bestanden.
- `models/Questions.php` bevat `class Question` (enkelvoud).
- `StudentController` beheert **alle** gebruikers, niet alleen studenten.
- Wijzig je de prompt van een toets, dan wist `updateExam()` alle AI-feedback van die toets. Dat is bewust zo.
- Wie de audit log leegmaakt, zet daarmee ook de login-lockout en de gast-rate-limit terug.
- De score-aggregatie (gemiddelden per model) staat op meerdere plekken gedupliceerd. Wijzig je die, wijzig dan alle plekken.

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
- [ ] Nieuwe worker-instellingen hebben een `getattr`-default en staan in `config.py.sample` en `bin/README.md`
- [ ] Docs bijgewerkt: `MANUAL.md` (gebruikersgedrag), `bin/README.md` (worker), `ARCHITECTURE.md` (structuur of contracten), `docs/security-issues.txt` (security-relevante wijzigingen)
