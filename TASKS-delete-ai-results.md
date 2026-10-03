# TASKS: AI-resultaten verwijderen en opnieuw laten uitvoeren

Takenlijst voor de branch `dev-reset-ai-results`. De vorige takenlijsten staan in [TASKS-agentic-student-ai.md](TASKS-agentic-student-ai.md) en [TASKS-agentic-exam-ai.md](TASKS-agentic-exam-ai.md).

**Zo gebruik je deze lijst:**

- Werk de stappen in volgorde af en vink ze af. Elke stap is klein en eindigt met een controle (*Klaar als*).
- Commit aan het eind van elke fase (kort, Engels, zoals de bestaande historie).
- Lees vooraf [CLAUDE.md](CLAUDE.md) (verplichte beveiligingspatronen, contracten 1, 8 en 9) en [ARCHITECTURE.md](ARCHITECTURE.md) §6.1, §6.8 en §6.9. Alles daarin geldt hier ook.

---

## Wat we bouwen

Een docent kan bij een ingeleverde toetspoging de **AI-resultaten weghalen**, zodat de AI de antwoorden **opnieuw beoordeelt**. Het gaat om beide soorten AI-resultaat:

1. **AI-feedback** (`student_answers.ai_feedback`, van `process_ai_feedback.py`);
2. **Agentic beoordeling** (de actuele run in `answer_assessments`, van `process_assessment_jobs.py`).

Dat kan op twee niveaus (en optioneel een derde):

- **Per antwoord:** knop "AI opnieuw laten beoordelen" bij een antwoord op de antwoordenpagina (`view_student_answers`).
- **Per student bij één toets (= één toetspoging), in één keer:** alle AI-resultaten van die poging. De knop staat op twee plekken: bovenaan de antwoordenpagina, en per student in de lijst op de resultatenpagina van de toets (`exam_results`), zodat je de poging niet eerst hoeft te openen.
- *(Optioneel, fase 8)* **Per toets:** alle ingeleverde pogingen van één toets tegelijk, vanaf de resultatenpagina.

**Het idee in één zin:** een reset zet de antwoorden terug in de toestand van *net ingeleverd*. Daarna doen de bestaande mechanismen het werk: een rubric-antwoord start automatisch een nieuwe agentic run (`AGENTIC_AUTO_ASSESSMENT`), elk ander antwoord komt weer in de wachtrij van de AI-worker. Er verandert niets aan de workers.

**Wat blijft staan:** de docentbeoordeling (`teacher_score`, `teacher_feedback`) is altijd van een mens en wordt nooit aangeraakt. Oude agentic runs blijven als `superseded` bewaard (geschiedenis), net als bij "Opnieuw beoordelen".

**Waarvoor:** een ander of beter model in de worker, een aangepaste prompt of rubric, een mislukte of rare AI-beoordeling, of een vermoeden van prompt injection dat je opnieuw wilt laten toetsen.

**Buiten scope:** alleen verwijderen zonder opnieuw uitvoeren, pogingen via een externe koppeling (zie B6), een keuze welk van de twee AI-resultaten je reset, alle toetsen van een student tegelijk (zie [Later](#later)).

---

## Analyse: wat dit betekent voor deze codebase

- **AI-feedback opnieuw laten uitvoeren is eenvoudig.** De database is de wachtrij: een ingeleverd antwoord met lege `ai_feedback` bij een toets met `ai_grading_enabled = 1` staat klaar voor de AI-worker (`StudentAnswer::getPendingAiGrading()`). `ai_feedback = NULL` en `ai_updated_at = NULL` is genoeg. `StudentAnswer::clearAiFeedbackByExam()` doet dit al per toets (bij een promptwijziging in `DocentController::updateExam()`).
- **Agentic opnieuw uitvoeren heeft één addertje.** `AnswerAssessment::create()` zet de oude runs (`pending`, `done`, `failed`) al op `superseded`. Maar de automatische start (`autoEligibleSql()`, gebruikt door `createAutomaticRuns()` en `excludeFromAiGradingSql()`) eist dat er **helemaal geen run** bestaat (`NOT EXISTS (… WHERE aa2.student_answer_id = sa.id)`). Zetten we alle runs op `superseded`, dan start er dus géén nieuwe agentic run en valt een rubric-antwoord stilletjes terug op de AI-worker. Oplossing: tel `superseded`-runs niet mee in die voorwaarde.
- **Die wijziging is veilig voor bestaande data.** Een run wordt nu alleen `superseded` in `create()`, in dezelfde transactie waarin een nieuwere run wordt aangemaakt. Elk antwoord met een `superseded`-run heeft dus ook een nieuwere, niet-vervangen run. Stap 1.2 controleert dat met een query.
- **Workers merken niets.** Contract 1 (`ai_feedback`-formaat), contract 2 (API) en contract 8 (agentic JSON) blijven gelijk. Een agentic run die de worker nog aan het rekenen was, krijgt bij het insturen 409 (`WHERE status = 'pending'`) en wordt overgeslagen. Alleen de webapp wordt uitgerold.
- **De externe koppeling (contract 9) is het risico.** De status van een poging wordt berekend: `graded` als elk antwoord een AI-resultaat heeft. Een reset zou een poging terugzetten van `graded` naar `grading`, en de webhook `attempt.graded` gaat maar één keer per poging (`UNIQUE (student_exam_id, event)`). Een externe partij krijgt dan nooit bericht dat de nieuwe beoordeling klaar is. Dat is geen additieve wijziging, dus in deze versie **niet voor koppelingspogingen** (B6).
- **De resultatenpagina heeft alles al.** `viewExamResults()` geeft `$exam` en `$canEdit` mee, en de rijen uit `StudentExam::findWithStudentDetailsByExam()` bevatten `completed_at` en `integration_name`. Een knop per rij vraagt dus geen extra queries.
- **Geen schemawijziging.** Alles past in de bestaande kolommen en statussen.

| Risico | Maatregel in dit ontwerp |
|---|---|
| Docentbeoordeling gaat verloren | De reset raakt alleen `ai_feedback`, `ai_updated_at` en de status van agentic runs. `teacher_score`/`teacher_feedback` staan in geen enkele query van deze feature. |
| Rubric-antwoord valt na reset terug op de AI-worker in plaats van agentic | `autoEligibleSql()` negeert `superseded`-runs (stap 1.2); de reset start de automatische runs direct (stap 2.3). |
| Worker levert nog een oud resultaat in na de reset | Agentic: 409 door `WHERE status = 'pending'`. AI-feedback: de worker werkt alleen aan antwoorden met lege `ai_feedback`; er valt dan niets te wissen, en het resultaat hoort bij hetzelfde, ongewijzigde antwoord. |
| Kosten: veel resets geven veel LLM-aanroepen | Rate limit per docent (`AI_RESULTS_RESET_MAX_PER_HOUR`), `data-confirm`, alleen de eigenaar (B4). |
| Externe partij ziet `graded` → `grading` en krijgt geen nieuw event | Reset niet toegestaan voor koppelingspogingen (B6). |
| Oude AI-feedback is onherroepelijk weg | Oude AI-scores per antwoord gaan mee in de auditregel (B7). De agentic runs blijven als `superseded` bewaard. |
| Reset terwijl AI-beoordeling uit staat: resultaten weg, niets komt terug | Niet toegestaan; de knop verschijnt dan niet en de server weigert (B3). |
| Open redirect via de terugkeerpagina | Alleen een vaste keuze `return=exam_results`; de `exam_id` voor de redirect komt uit de database (B9). |

---

## Ontwerpbeslissingen

**B1. Reset = terug naar "net ingeleverd".** Per antwoord: `ai_feedback` en `ai_updated_at` op `NULL`, en elke run met status `pending`, `done` of `failed` op `superseded`. Dat gebeurt in **één transactie** voor alle antwoorden van de reset. Daarna bepalen de bestaande regels wat er opnieuw draait. Er komt geen nieuwe status en geen nieuwe wachtrij.

**B2. Opnieuw uitvoeren via de bestaande automatiek.** Direct na de reset roept de controller per gereset poging `AnswerAssessment::createAutomaticRuns($studentExamId, ASSESSMENT_AUTO_START_BATCH)` aan, net als bij inleveren (`StudentExamController`). Rubric-antwoorden krijgen dan meteen een nieuwe `pending` run. Staat `AGENTIC_AUTO_ASSESSMENT` uit, dan gaan ze (net als na inleveren) naar de AI-worker; de docent kan daarna zelf "Agentic beoordelen" klikken. Resten boven de batchgrootte start de assessment-worker bij zijn volgende poll.

**B3. Voorwaarden per poging.** De poging is ingeleverd (`completed_at`), de toets heeft `ai_grading_enabled = 1` en de poging hoort niet bij een externe koppeling (B6). Een leeg antwoord mag: de AI-worker beoordeelt dat ook nu al. Bij een reset per antwoord of per poging geeft een overtreding een foutmelding; bij de optionele reset per toets wordt die poging overgeslagen en geteld.

**B4. Autorisatie: alleen schrijfrecht op de toets.** `requireRole('docent')` plus `checkExamOwnership($examId, true)` (eigenaar of admin). Verwijderen is destructief en bepaalt wat studenten zien, net als een promptwijziging (die ook alleen de eigenaar mag). Een docent bij een gedeelde toets kan nog steeds "Agentic beoordelen" starten. De toets-id komt altijd uit de database (`StudentAnswer::findWithExam()` / `StudentExam::find()`), nooit uit het formulier.

**B5. Plek in de code.** Model: `StudentAnswer::resetAiResults()` plus een helper in `AnswerAssessment`. Controller: methodes in `DocentController` (daar staan `checkExamOwnership()`, `viewExamResults()`, `viewStudentAnswers()` en de bestaande `clearAiFeedbackByExam`-aanroep). Actions: `ai_results_reset_answer` en `ai_results_reset_attempt` (optioneel `ai_results_reset_exam`).

**B6. Niet voor koppelingspogingen (contract 9).** Heeft de poging een rij in `integration_attempts` (`IntegrationAttempt::findByStudentExam()`), dan verschijnt de knop niet en weigert de server. Zo blijft de externe afspraak (status loopt alleen vooruit, `attempt.graded` één keer) ongewijzigd.

**B7. Audit: één regel per reset.** Elke reset schrijft precies één regel `ai_results_reset` met `scope` (`answer`, `attempt` of `exam`), `exam_id`, de betrokken `student_exam_ids`, de `student_answer_ids`, het aantal gewiste AI-feedbacks en vervangen runs, de oude AI-scores per antwoord (via `StudentAnswer::aiScores()`, alleen de scores, niet de hele tekst) en de nieuw gestarte runs. Bij de optionele reset per toets ook de overgeslagen pogingen met reden.

**B8. Rate limit per reset, niet per antwoord.** `AI_RESULTS_RESET_MAX_PER_HOUR = 30` resets per docent per uur, geteld met `AuditLog::countRecent('ai_results_reset', 60, null, $_SESSION['name'])`. Dankzij B7 telt een reset van een hele poging (of toets) als één.

**B9. Terug naar de pagina waar je klikte.** Standaard gaat de redirect na `ai_results_reset_attempt` naar de antwoordenpagina van de poging. Stuurt het formulier `return=exam_results` mee (de knop op de resultatenpagina), dan gaat de redirect naar `/?action=exam_results&exam_id=…`, met de `exam_id` uit de database. Elke andere waarde van `return` wordt genegeerd; er komt nooit een vrije URL in het formulier.

---

## Fase 0: Voorbereiding

- [x] **0.1 Branch.** Maak `dev-reset-ai-results` vanaf een bijgewerkte `main`.
  *Klaar als:* `git status` toont de nieuwe branch, schoon.
- [x] **0.2 Testdata.** `./docker/start.sh`, inloggen als `admin@school.nl`. Maak een docent die een toets maakt met `ai_grading_enabled = 1`, **gedeeld**, met twee vragen: één **met** rubric-criteria (kopieer `bin/fixtures/criteria_rubric.txt`) en één **zonder** (gewone criteria). Maak een tweede docent (voor de autorisatietest). Laat twee studenten een poging maken en inleveren.
  *Klaar als:* beide pogingen staan bij "Resultaten" van de toets.
  *Uitgevoerd:* in een aparte testcontainer (poort 8081, eigen database), testdata via SQL in plaats van via de UI; plus een niet-ingeleverde poging, een koppelingspoging, een toets met AI-beoordeling uit en een eigen toets van docent 2.
- [x] **0.3 Workers laten draaien.** Lokale `bin/config.py` met de Docker-`BASE_URL` en een **cloud-model** (`gpt-oss:120b-cloud`, nooit een lokaal model). Start `process_ai_feedback.py` en `process_assessment_jobs.py`.
  *Klaar als:* elk gewoon antwoord heeft AI-feedback en elk rubric-antwoord een agentic run met status "Beoordeeld door AI".
- [x] **0.4 Nulmeting noteren.** Geef een paar antwoorden een docentscore en noteer per antwoord: `ai_feedback` (begin), de run-id's en de docentscores.
  *Klaar als:* je weet hoe "voor de reset" eruitziet.

## Fase 1: Model

- [x] **1.1 Helper: runs vervangen.** Voeg in `AnswerAssessment` toe: `public static function supersedeActiveRuns(PDO $pdo, array $studentAnswerIds): int`. Eén `UPDATE answer_assessments SET status = 'superseded', updated_at = CURRENT_TIMESTAMP WHERE student_answer_id IN (?, …) AND status IN ('pending', 'done', 'failed')`, met een placeholder per id (alle id's via `(int)`). Lege lijst: meteen `0`. Returnwaarde: `rowCount()`. Gebruik de statusconstanten, geen letterlijke strings.
  *Klaar als:* PHP-syntaxcheck slaagt.
- [x] **1.2 Auto-start negeert vervangen runs.** Pas in `AnswerAssessment::autoEligibleSql()` de laatste voorwaarde aan naar `NOT EXISTS (SELECT 1 FROM answer_assessments aa2 WHERE aa2.student_answer_id = $sa.id AND aa2.status != ?)` en voeg `self::STATUS_SUPERSEDED` toe aan de params (let op de volgorde: na de rubric-params). Werk het docblok bij ("nog geen run" → "nog geen run die niet vervangen is") en ook het docblok van `excludeFromAiGradingSql()`.
  *Klaar als:* syntaxcheck slaagt, en deze query in de Docker-database **0** geeft (dan verandert er niets voor bestaande data):
  ```sql
  SELECT COUNT(*) FROM answer_assessments a
  WHERE a.status = 'superseded'
    AND NOT EXISTS (SELECT 1 FROM answer_assessments b
                    WHERE b.student_answer_id = a.student_answer_id AND b.status != 'superseded');
  ```
- [x] **1.3 `create()` hergebruikt de helper.** Vervang in `AnswerAssessment::create()` de eigen `UPDATE … superseded` door `self::supersedeActiveRuns($pdo, [$studentAnswerId])`, binnen de bestaande transactie.
  *Klaar als:* "Agentic beoordelen" en "Opnieuw beoordelen" werken nog zoals voorheen (oude run wordt "Vervangen").
- [x] **1.4 Antwoorden van een poging.** Voeg in `StudentAnswer` toe: `answerIdsByStudentExam($studentExamId): array` (alleen de id's, als ints).
  *Klaar als:* syntaxcheck slaagt.
- [x] **1.5 Oude AI-scores ophalen.** Voeg in `StudentAnswer` toe: `aiScoresByIds(array $ids): array` met per antwoord-id het resultaat van `self::aiScores($row['ai_feedback'], $row['agentic_score'])`. Haal `agentic_score` op met `AnswerAssessment::agenticScoreSql('sa')`. Alleen voor de auditregel (B7).
  *Klaar als:* syntaxcheck slaagt.
- [x] **1.6 De reset zelf.** Voeg in `StudentAnswer` toe: `resetAiResults(array $studentAnswerIds): array`. In één transactie:
  1. `UPDATE student_answers SET ai_feedback = NULL, ai_updated_at = NULL WHERE id IN (…) AND ai_feedback IS NOT NULL AND ai_feedback != ''` (rowCount = gewiste AI-feedbacks);
  2. `AnswerAssessment::supersedeActiveRuns($pdo, $ids)`.

  Rollback en `throw` bij een fout (zelfde patroon als `AnswerAssessment::create()`). Returnwaarde `['ai_feedback' => n, 'agentic_runs' => m]`. **Raak `teacher_score`/`teacher_feedback` niet aan.**
  *Klaar als:* syntaxcheck slaagt.
- [x] **1.7 Instelling.** Voeg in `config/app.php` bij de andere limieten toe: `const AI_RESULTS_RESET_MAX_PER_HOUR = 30;   // resets van AI-resultaten per docent per uur (een reset van een hele poging telt als één)`.
  *Klaar als:* syntaxcheck slaagt.

  Commit: `Add AI result reset to models`.

## Fase 2: Controller en routes

- [x] **2.1 Gedeelde voorwaarden.** Voeg in `DocentController` een private methode `aiResetBlockedReason(array $studentExam, array $exam, bool $isIntegration): ?string` toe die een Nederlandse reden geeft, of `null` (B3, B6):
  - niet ingeleverd: "Deze toetspoging is nog niet ingeleverd.";
  - `ai_grading_enabled != 1`: "AI-beoordeling staat uit voor deze toets. Zet die eerst aan; anders worden de AI-resultaten alleen verwijderd.";
  - koppelingspoging: "Deze poging komt van een externe koppeling. Daar kunnen de AI-resultaten niet opnieuw worden uitgevoerd, omdat de externe website de beoordeling al heeft ontvangen."

  De aanroeper geeft `$isIntegration` mee: in de actions via `IntegrationAttempt::findByStudentExam() !== null`, op de resultatenpagina via `!empty($se['integration_name'])` (geen query per rij).
  *Klaar als:* syntaxcheck slaagt.
- [x] **2.2 Rate limit.** Private `aiResetRateLimited(): bool` met `AuditLog::countRecent('ai_results_reset', 60, null, $_SESSION['name']) >= AI_RESULTS_RESET_MAX_PER_HOUR`. Melding (gedeeld): "Je hebt het afgelopen uur al … keer AI-resultaten opnieuw laten uitvoeren. Probeer het later opnieuw."
  *Klaar als:* syntaxcheck slaagt.
- [x] **2.3 Gedeelde uitvoering.** Private `performAiReset(string $scope, int $examId, array $answerIdsByAttempt, array $auditExtra = []): array`, met `$answerIdsByAttempt` als `student_exam_id => [student_answer_id, …]`. De methode:
  1. bewaart de oude scores met `StudentAnswer::aiScoresByIds()` over alle antwoord-id's;
  2. roept één keer `StudentAnswer::resetAiResults()` aan met alle antwoord-id's (één transactie, B1);
  3. roept per poging `AnswerAssessment::createAutomaticRuns($studentExamId, ASSESSMENT_AUTO_START_BATCH)` aan (B2);
  4. schrijft **één** `AuditLog::log('ai_results_reset', [...])` met de velden uit B7, aangevuld met `$auditExtra`;
  5. geeft de tellingen terug (voor de melding).

  Met één poging is `$answerIdsByAttempt` gewoon `[$studentExamId => $ids]`; de lus is er voor de optionele reset per toets (fase 8).
  *Klaar als:* syntaxcheck slaagt.
- [x] **2.4 Action `ai_results_reset_answer`.** Publieke methode `resetAiResultsAnswer()`, in deze volgorde:
  `validateCsrfToken()`, `requireRole('docent')`, `$id = requestInt($_POST, 'student_answer_id')`, `StudentAnswer::findWithExam($id)` (geen rij: `abort(404, 'Antwoord niet gevonden.')`), `$this->checkExamOwnership($answer['exam_id'], true)`, `StudentExam::find()` en `Exam::find()`, `aiResetBlockedReason()` (reden: `$_SESSION['error']` en terug), rate limit, dan `performAiReset('answer', $examId, [$studentExamId => [$id]])`. Succesmelding in `$_SESSION['success_message']`, bijvoorbeeld "De AI-resultaten van dit antwoord zijn verwijderd. De AI beoordeelt het opnieuw." Redirect naar `/?action=view_student_answers&student_exam_id=…#answer-<id>`.
  *Klaar als:* syntaxcheck slaagt.
- [x] **2.5 Action `ai_results_reset_attempt`.** Publieke methode `resetAiResultsAttempt()`: zelfde volgorde, met `student_exam_id` via `requestInt($_POST, …)` en `StudentExam::find()` (404 als die ontbreekt). Antwoord-id's via `StudentAnswer::answerIdsByStudentExam()`. Melding met de naam van de student en tellingen: "AI-resultaten van <naam> verwijderd: X AI-feedback, Y agentic beoordelingen. De AI beoordeelt de antwoorden opnieuw."
  *Klaar als:* syntaxcheck slaagt.
- [x] **2.6 Redirect (B9).** In `resetAiResultsAttempt()`: `requestString($_POST, 'return', 20) === 'exam_results'` → `/?action=exam_results&exam_id=<exam_id uit de database>`; anders `/?action=view_student_answers&student_exam_id=…`. Ook een foutmelding (geblokkeerd, rate limit) gaat naar die pagina.
  *Klaar als:* syntaxcheck slaagt.
- [x] **2.7 Routes.** Voeg in `htdocs/index.php` twee `case`s toe bij de andere docent-actions: `ai_results_reset_answer` → `$docent->resetAiResultsAnswer()` en `ai_results_reset_attempt` → `$docent->resetAiResultsAttempt()`.
  *Klaar als:* ingelogd geeft een GET op beide actions 405.

  Commit: `Let teachers reset AI results of an attempt`.

## Fase 3: Antwoordenpagina

- [x] **3.1 Gegevens voor de view.** Geef in `DocentController::viewStudentAnswers()` een `$canResetAi` mee: `$canEdit && aiResetBlockedReason($studentExam, $exam, $integrationAttempt !== null) === null`. `$canEdit` en `$integrationAttempt` zijn er al.
  *Klaar als:* syntaxcheck slaagt.
- [x] **3.2 Kaart "AI-resultaten" met knop per poging.** Voeg in `app/views/docent/student_answers.php` een kaart "AI-resultaten" toe onder de kaart "Agentic beoordelen", alleen als `$canEdit`. Bij `$canResetAi`: korte uitleg ("Verwijdert de AI-feedback en de agentic beoordelingen van deze poging en laat de AI opnieuw beoordelen. Je eigen docentbeoordeling blijft staan.") en een POST-formulier naar `ai_results_reset_attempt` met `<?= csrfInput() ?>`, verborgen `student_exam_id` en een knop "Alle AI-resultaten opnieuw laten uitvoeren" (`btn btn-outline-danger btn-sm`) met `data-confirm="Alle AI-resultaten van deze poging verwijderen en opnieuw laten uitvoeren? Je docentbeoordeling blijft staan."`. Kopieer de opbouw van het formulier "Alle antwoorden agentic beoordelen".
  *Klaar als:* de kaart staat er bij de eigenaar en niet bij een docent van een gedeelde toets.
- [x] **3.3 Knop per antwoord.** Toon per antwoord, alleen als `$canResetAi` **en** er iets te resetten is (`$a['ai_feedback']` niet leeg of `$run` niet `null`), een klein formulier naar `ai_results_reset_answer` met verborgen `student_answer_id`, knop "AI opnieuw laten beoordelen" (`btn btn-sm btn-outline-danger`) en `data-confirm="De AI-feedback en de agentic beoordeling van dit antwoord verwijderen en opnieuw laten uitvoeren?"`. Zet hem onder de AI-blokken, vóór "Docentbeoordeling".
  *Klaar als:* de knop staat alleen bij antwoorden met een AI-resultaat.
- [x] **3.4 Melding als het niet kan.** Is `$canEdit` waar maar `$canResetAi` niet, toon dan in de kaart uit 3.2 de reden uit `aiResetBlockedReason()` (via `e()`) in plaats van de knop. Zo snapt de docent waarom de knop ontbreekt.
  *Klaar als:* bij een toets met AI-beoordeling uit staat de uitleg er.
- [x] **3.5 Uitvoer veilig.** Controleer dat alle nieuwe output via `e()` gaat, er geen inline handlers zijn en de layout de `data-confirm`-knoppen afhandelt.
  *Klaar als:* geen CSP-fouten in de browserconsole.
  *Uitgevoerd:* gecontroleerd dat er geen inline scripts of handlers bij zijn gekomen; niet in een browserconsole bekeken.

  Commit: `Show AI result reset on the answers page`.

## Fase 4: Resultatenpagina (per student in één keer)

- [x] **4.1 Gegevens voor de view.** Bepaal in `DocentController::viewExamResults()` per rij of de reset kan: zet in de lus over `$studentExams` een veld `can_reset_ai` = `$canEdit && aiResetBlockedReason($se, $exam, !empty($se['integration_name'])) === null`. Geen extra queries: `completed_at` en `integration_name` zitten al in de rij.
  *Klaar als:* syntaxcheck slaagt.
- [x] **4.2 Knop per student.** Voeg in `app/views/docent/exam_results.php` in de kolom "Acties", naast "Verwijderen", alleen bij `$se['can_reset_ai']` een klein POST-formulier toe (`class="d-inline"`) naar `ai_results_reset_attempt` met `<?= csrfInput() ?>`, verborgen `student_exam_id` en `return` = `exam_results`, en een knop "AI opnieuw" (`btn btn-sm btn-outline-warning ms-1`) met `data-confirm="Alle AI-resultaten van <naam> bij deze toets verwijderen en opnieuw laten uitvoeren? De docentbeoordeling blijft staan."` (naam via `e()`). Gebruik een formulier, geen muterende link.
  *Klaar als:* de knop staat bij ingeleverde pogingen van een toets met AI-beoordeling aan, niet bij een koppelingspoging of een niet-ingeleverde poging, en niet bij een docent van een gedeelde toets.
- [x] **4.3 Melding tonen.** De layout (`layouts/main.php`) toont `$_SESSION['success_message']` en `$_SESSION['error']` al op elke pagina; er hoeft niets bij.
  *Klaar als:* na een klik op "AI opnieuw" kom je terug op de resultatenpagina met de melding.

  Commit: `Add AI result reset to the exam results page`.

## Fase 5: Rooktest

Doorloop elke stap met de testdata uit fase 0 en beide workers aan.

- [x] **5.1 Gewoon antwoord.** Reset het antwoord zonder rubric. *Klaar als:* de AI-feedback is weg, binnen een poll weer gevuld (nieuwe tekst, nieuwe `ai_updated_at`), en de docentscore is onveranderd.
- [x] **5.2 Rubric-antwoord.** Reset het rubric-antwoord. *Klaar als:* de oude run staat op "Vervangen", er is direct een nieuwe run "Wordt beoordeeld" (zonder te wachten op een poll), die wordt "Beoordeeld door AI", en het antwoord krijgt **geen** gewone AI-feedback.
- [x] **5.3 Poging via de antwoordenpagina.** Reset de poging van student 1. *Klaar als:* beide antwoorden worden opnieuw beoordeeld, de melding noemt de juiste aantallen, en de poging van student 2 is onaangeroerd.
- [x] **5.4 Poging via de resultatenpagina.** Klik bij student 2 op "AI opnieuw". *Klaar als:* je komt terug op de resultatenpagina met de melding, alleen de antwoorden van student 2 worden opnieuw beoordeeld, en de audit log heeft **één** regel `ai_results_reset` met scope `attempt`.
- [x] **5.5 Mislukte run.** Zet met `sqlite3` een run op `failed` en laat de AI-worker de fallback-feedback schrijven. Reset het antwoord. *Klaar als:* AI-feedback en run zijn weg en er start opnieuw een agentic run (niet de fallback).
- [x] **5.6 Reset tijdens het rekenen.** Reset een rubric-antwoord en direct daarna nog een keer, terwijl de worker aan de eerste run werkt. *Klaar als:* de worker logt "verouderd, overgeslagen" (409) en alleen de nieuwste run krijgt een resultaat.
- [x] **5.7 Automatiek uit.** Zet `AGENTIC_AUTO_ASSESSMENT = false`, herbouw, reset het rubric-antwoord. *Klaar als:* het antwoord krijgt gewone AI-feedback (net als na inleveren). Zet de vlag daarna terug.
- [x] **5.8 Voorwaarden.** *Klaar als:* bij AI-beoordeling uit, een niet-ingeleverde poging en een koppelingspoging (via de demo-site in `docs/integration-demo/`) de knoppen op beide pagina's ontbreken, de reden op de antwoordenpagina zichtbaar is, en een handmatige POST (met geldig CSRF-token) de foutmelding geeft zonder iets te wissen.
  *Uitgevoerd:* de koppelingspoging is direct in de database aangemaakt (`integration_attempts`), niet via de demo-site.
- [x] **5.9 Autorisatie.** *Klaar als:* een GET op beide actions 405 geeft; de tweede docent (gedeelde, niet-eigen toets) ziet geen knoppen en krijgt 403 op een handmatige POST; een beoordelaar en een student worden door `requireRole` geweigerd; een admin mag het; een `student_answer_id` of `student_exam_id` van een andere toets helpt niet (de toets komt uit de database).
- [x] **5.10 Redirect.** *Klaar als:* een POST met `return=https://example.com` of een andere onbekende waarde gewoon naar de antwoordenpagina gaat.
- [x] **5.11 Rate limit.** Zet `AI_RESULTS_RESET_MAX_PER_HOUR` tijdelijk op 2. *Klaar als:* de derde reset de melding geeft, ook vanaf de resultatenpagina. Zet de waarde terug.
- [x] **5.12 Student en audit.** *Klaar als:* de student ziet na de reset tijdelijk geen AI-resultaat en daarna het nieuwe; de audit log toont `ai_results_reset` met scope, aantallen en de oude scores; "Vergelijk AI" en de AI-gemiddelden tonen de nieuwe scores.

  Commit (als er fixes waren): `Fix AI result reset edge cases`.

## Fase 6: Documentatie

- [x] **6.1 `MANUAL.md`.** Onder *Resultaten & Beoordelen* of *Antwoorden agentic beoordelen* een korte alinea **"AI opnieuw laten beoordelen"**: wat er verdwijnt (AI-feedback en agentic beoordeling), wat blijft (je docentbeoordeling, de geschiedenis van agentic runs), waar de knoppen staan (per antwoord en per poging op "Bekijken", en "AI opnieuw" per student bij "Resultaten"), dat de AI daarna vanzelf opnieuw beoordeelt, wanneer het niet kan (AI-beoordeling uit, niet ingeleverd, externe koppeling, niet de eigenaar) en dat het aantal per uur begrensd is.
- [x] **6.2 `ARCHITECTURE.md`.** §3.3: de twee nieuwe routes. §6.1: de reset als manier om antwoorden terug in de wachtrij te zetten. §6.8: de automatische start kijkt alleen naar runs die niet `superseded` zijn, en waarom (reset). §6.9: koppelingspogingen kunnen niet gereset worden (contract 9).
- [x] **6.3 `docs/security-issues.txt`.** Nieuwe muterende actions: CSRF, schrijfrecht op de toets, toets-id uit de database, alleen een vaste `return`-waarde (geen open redirect), rate limit via de audit log, docentscore onaangeroerd, geen reset bij koppelingspogingen.
- [x] **6.4 `CLAUDE.md`.** Bij *Valkuilen*: "Een reset van AI-resultaten zet runs op `superseded`; `autoEligibleSql()` telt die niet mee. Laat `superseded` dus nooit een eindtoestand zonder nieuwere run zijn, **tenzij** dat een bewuste reset is."
- [x] **6.5 `docs/integration-api.md`.** Geen wijziging nodig (koppelingspogingen zijn uitgesloten). Controleer dat er niets staat dat dit tegenspreekt.

  Commit: `Document AI result reset`.

## Fase 7: Afronding

- [x] **7.1** PHP- en Python-syntaxcheck slagen (zie *Commando's* in CLAUDE.md).
- [x] **7.2** Alle drie de mocktestsuites slagen (er is aan de worker niets veranderd, dus dit moet ongewijzigd groen zijn).
- [x] **7.3** Rooktest met een **nieuwe** database (`docker compose down -v`) en met een **bestaande** database (de query uit 1.2 geeft 0).
  *Uitgevoerd:* rooktest op een nieuwe database; daarnaast een kopie van de bestaande dev-database opgestart met de nieuwe code: query 1.2 geeft 0 en de AI-wachtrij werkt.
- [x] **7.4** Loop de [merge-checklist in CLAUDE.md](CLAUDE.md#checklist-voor-een-merge-naar-main) na.
- [x] **7.5** (concept, nog niet gepusht) PR-beschrijving: alleen de webapp wordt uitgerold, geen schemawijziging, geen wijziging aan workers of `config.py`, contract 9 ongewijzigd (koppelingspogingen uitgesloten), en de wijziging aan `autoEligibleSql()` met de uitleg waarom die veilig is voor bestaande data.

## Fase 8 (optioneel): Hele toets

Alleen als per poging in de praktijk te omslachtig blijkt. Hergebruikt `performAiReset()` uit 2.3.

- [x] **8.1 Action `ai_results_reset_exam`.** `validateCsrfToken()`, `requireRole('docent')`, `exam_id` via `requestInt($_POST, …)`, `checkExamOwnership($examId, true)`, `ai_grading_enabled` moet 1 zijn, rate limit. Verdeel de pogingen uit `StudentExam::findWithStudentDetailsByExam()` met `aiResetBlockedReason()` in mee en overgeslagen (met reden), haal per poging de antwoord-id's op en roep **één** keer `performAiReset('exam', $examId, $answerIdsByAttempt, ['skipped' => …])` aan. Melding met het aantal pogingen en de overgeslagen pogingen (onder andere koppelingspogingen). Voeg een `case` toe.
- [x] **8.2 Knop.** Op `exam_results.php` boven de tabel een knop "AI-resultaten van alle pogingen opnieuw laten uitvoeren" voor de eigenaar, met een `data-confirm` die het aantal pogingen noemt.
- [x] **8.3 Rooktest.** Zoals fase 5 met drie pogingen, waarvan één via de koppeling (die wordt overgeslagen).

  Commit: `Let teachers reset AI results of an exam`.

---

## Later

- **Koppelingspogingen resetten.** Kan alleen additief: bijvoorbeeld een nieuw event `attempt.regraded` (opnieuw verstuurd na elke nieuwe AI-beoordeling) en in `docs/integration-api.md` vastleggen dat de status van `graded` terug naar `grading` kan gaan. Eerst afstemmen met de externe partijen.
- **Alle toetsen van een student tegelijk.** Vraagt een studentenoverzicht voor docenten (nu ziet alleen de admin een gebruikerslijst) en een filter per poging op schrijfrecht.
- **Kiezen wat je reset:** alleen de AI-feedback, of alleen de agentic beoordeling.
- **Alleen verwijderen zonder opnieuw uitvoeren** (bijvoorbeeld bij een fout model), los van `ai_grading_enabled`.
- **Oude AI-feedback bewaren** in een eigen geschiedenistabel, zoals `answer_assessments` dat al doet voor agentic runs.
- **Per vraag resetten** over alle pogingen heen (na een aanpassing van de criteria van één vraag).
