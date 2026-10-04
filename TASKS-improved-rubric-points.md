# TASKS: Beoordelen met niveaus en puntenschema's

Takenlijst voor de branch `dev-rubric-levels`.

**Zo gebruik je deze lijst:**

- Werk de stappen in volgorde af en vink ze af. Elke stap is klein en eindigt met een controle (*Klaar als*).
- Commit aan het eind van elke fase (kort, Engels, zoals de bestaande historie).
- Lees vooraf [CLAUDE.md](CLAUDE.md) (verplichte beveiligingspatronen, contracten 1, 4, 7, 8 en 9) en [ARCHITECTURE.md](ARCHITECTURE.md). Alles daarin geldt hier ook.
- PHP-controle tussendoor (er is lokaal geen PHP):
  `docker run --rm -v "$PWD":/app -w /app php:8.2-cli php -r 'require "config/app.php"; require "app/models/Grading.php"; var_dump(Grading::grade(["goed","uitstekend"], ["points_voldoende"=>3,"points_goed"=>4,"points_uitstekend"=>5]));'`
  (pas de `require`s en de aanroep aan per stap).

---

## Wat we bouwen

Nu krijgt elk antwoord een score: de AI geeft 0, 1, 5 of 10, de docent 0 t/m 10, en het eindcijfer is het gemiddelde van de docentscores. Dat wordt:

1. **Per vraag een niveau:** onvoldoende, voldoende, goed of uitstekend. Dat geldt voor de AI en voor de docent. Een niveau per vraag is **geen** woordbeoordeling van het eindcijfer.
2. **Een puntenschema per toets** zet de niveaus om in punten. Onvoldoende is altijd 0 punten. Docenten maken zelf schema's (bijvoorbeeld 3/4/5 of 7/9/10) en kiezen er één per toets.
3. **Eindcijfer 0–10:** `10 × som van de punten / (aantal vragen × punten voor uitstekend)`. Alles uitstekend geeft dus altijd een 10.
4. **Woordbeoordeling (optioneel per toets):** het eindcijfer wordt omgezet naar onvoldoende (0–5), voldoende (6–7), goed (8–9) of uitstekend (10).
5. **Eindcijfer handmatig aanpassen** met een verplichte reden, door iedereen die de toets nakijkt.

**Rekenvoorbeeld** (schema 3/4/5, vier vragen): uitstekend, goed, voldoende en onvoldoende geeft 5 + 4 + 3 + 0 = 12 van de 20 punten, dus een **6,0**, met als woord *voldoende*. Met schema 7/9/10 geeft alles voldoende een 7,0 en alles goed een 9,0.

---

## Ontwerpbeslissingen

**B1. Schaal per toets.** `exams.grading_scale` is `points` (het huidige systeem, ongewijzigd) of `levels` (nieuw). Bestaande toetsen blijven `points`. Zodra een toets ingeleverde pogingen heeft, kun je de schaal niet meer wijzigen. Het `points`-pad blijft in de hele code werken zoals nu.

**B2. Niveaus.** De waarden in database en JSON zijn `onvoldoende`, `voldoende`, `goed` en `uitstekend`. Dat zijn Nederlandse enum-waarden, net als `voldaan`/`deels`/`niet` in contract 8.

**B3. Wanneer welk niveau** (bij een rubric met essentiële en aanvullende criteria):

| Niveau | Regel |
|---|---|
| Onvoldoende | niet alle essentiële criteria zijn `voldaan` (ook `deels` telt als niet voldaan) |
| Voldoende | alle essentiële criteria `voldaan`, geen enkel aanvullend criterium `voldaan` |
| Goed | alle essentiële criteria `voldaan`, minstens één aanvullend criterium `voldaan`, maar niet allemaal |
| Uitstekend | alle essentiële en alle aanvullende criteria `voldaan` |

De AI beoordeelt per criterium. **Het niveau volgt deterministisch** uit die statussen, via `level_from_statuses()` in de worker. Het model kiest het niveau dus niet zelf. Een rubric zonder aanvullende criteria komt hooguit op *voldoende* uit. Bij een vraag zonder rubric-opbouw kiest de AI het niveau direct (enum).

**B4. Puntenschema's.**
- Tabel `grading_schemes` met een naam en punten voor voldoende, goed en uitstekend (gehele getallen, `0 < voldoende < goed < uitstekend ≤ 100`).
- Een combinatie van punten bestaat maar één keer (UNIQUE). Wie een bestaande combinatie invoert, krijgt een melding met de naam van het bestaande schema.
- Iedereen met rol `docent` kan elk schema kiezen en zelf schema's maken. Alleen de maker (en de admin) kan een eigen schema wijzigen of verwijderen.
- Wijzigen kan alleen zolang geen toets met een ingeleverde poging het schema gebruikt. Verwijderen kan alleen als geen enkele toets het gebruikt.
- Er is één systeemschema "Standaard (3/4/5)" zonder eigenaar, dat alleen de admin kan wijzigen.

**B5. Een ander schema kiezen** voor een toets met resultaten mag wel: de cijfers worden opnieuw berekend en de wijziging komt in de audit log. Er hoeft niets opnieuw beoordeeld te worden, want punten worden pas bij het tonen berekend.

**B6. Cijferberekening.**
- Het cijfer heeft één decimaal (half naar boven afgerond) en is minimaal 0.
- Het aantal vragen is het aantal `student_answers` van de poging. Een leeg antwoord telt mee.
- Het docentcijfer wordt pas getoond als **elk** antwoord een docentniveau heeft. Daarvoor staat er "x van y beoordeeld".
- Het AI-cijfer per model wordt berekend over de antwoorden waarvoor dat model een niveau heeft (zoals het gemiddelde per model nu).

**B7. Woordbeoordeling.** `exams.show_grade_label` (0/1). Rond eerst het cijfer af op een geheel getal (half naar boven), daarna geldt: 0–5 onvoldoende, 6–7 voldoende, 8–9 goed, 10 uitstekend. Die mapping is vast en geldt voor alle toetsen. Met het vinkje aan ziet de student het woord, de docent ziet het woord en het cijfer.

**B8. Handmatig eindcijfer.**
- Iedereen die de toets mag nakijken (`checkGradingPermission()`: docenten en beoordelaars) kan het eindcijfer aanpassen.
- Toets zonder woordbeoordeling: een cijfer 0–10 met één decimaal. Toets met woordbeoordeling: een van de vier woorden.
- De reden is verplicht. Het berekende cijfer op het moment van aanpassen wordt mee bewaard (`grade_override_basis`).
- Is het berekende cijfer later anders, dan ziet de docent een waarschuwing. De aanpassing blijft staan.
- De student ziet alleen het uiteindelijke cijfer (of woord), zonder reden en zonder te zien dat het is aangepast.
- Dit werkt bij beide schalen.

**B9. Prompts per schaal.** Een prompt van een docent noemt vaak "10 punten / 5 punten". Daarom krijgt `prompts` een `grading_scale`. Bij een toets kies je alleen een prompt met dezelfde schaal.

**B10. Overgangsvlag.** `LEVELS_AI_ENABLED` in `config/app.php` (standaard `false`). Zolang die uit staat, gaan `levels`-toetsen **niet** naar de AI-worker en de assessment-worker. Docenten beoordelen dan zelf. Zet de vlag pas aan als de nieuwe worker draait (zie [Uitrol](#uitrol)).

---

## Contracten (wat verandert, alles additief)

| Contract | Wijziging |
|---|---|
| 1. `ai_feedback`-tekst | Bij `levels` schrijft de worker `Niveau: <niveau>` in plaats van `Aantal punten: N`. Nieuwe parser `StudentAnswer::aiLevels()` naast `aiScores()`; `clean_output_text()` neutraliseert ook `Niveau:` |
| 2. Worker-API | `open_student_answers`, `open_assessment_jobs` en `open_design_jobs` krijgen per job het veld `grading_scale`. Een worker zonder dat veld gaat uit van `points` |
| 4. Scores | Naast `{0, 1, 5, 10}` (points) de niveau-enum (levels). Docent: `teacher_score` 0–10 (points) of `teacher_level` (levels) |
| 7. Rubric-tekst | Nieuw kopje `Niveaus:` met de regels `Uitstekend:`, `Goed:`, `Voldoende:` en `Onvoldoende:`. De parser herkent het oude formaat (`Puntentoekenning:`) en het nieuwe |
| 8. Assessment-agents | `score` wordt per schaal een getal of een niveau; `decision` krijgt `level`; kolom `answer_assessments.final_level` |
| 9. Integratie-API | Alleen velden erbij: `grading_scale`, `level`, `grade`, `grade_label` en `grade_overridden`. Review met `level` voor `levels`-toetsen. Webhooks blijven ongewijzigd |

## Uitrol

1. Webapp (fases 1–9) met `LEVELS_AI_ENABLED = false`. Veilig met de oude worker, die `levels`-toetsen nooit te zien krijgt.
2. Worker (fases 10–12). Werkt met de oude én de nieuwe webapp (zonder het veld: `points`).
3. Zet `LEVELS_AI_ENABLED = true` in de webapp.
4. Beschrijf dit in `docs/rollout-level-grading.md` (fase 14).

---

## Fase 0: Voorbereiding

- [x] **0.1** Rond het werk op `dev-dashboard-filter` af of commit het. Tak daarna af van `main`: `git checkout main && git pull && git checkout -b dev-rubric-levels`.
- [x] **0.2** Controleer dat de drie mocktestsuites slagen voordat je begint (zie de commando's in CLAUDE.md).
  *Klaar als:* alle drie de suites groen zijn.
- [x] **0.3** Lees `StudentAnswer::aiScores()`, `DocentController::viewStudentAnswers()`, `StudentExamController::viewResults()` en `IntegrationAttempt::answerResult()` door. Daar zit nu de score-aggregatie.

## Fase 1: Datamodel

- [x] **1.1 Tabel `grading_schemes` in `setup/schema.sql`:** `id`, `name` (TEXT NOT NULL), `points_voldoende`, `points_goed`, `points_uitstekend` (INTEGER NOT NULL), `owner_id` (FK `users` ON DELETE SET NULL, NULL = systeemschema), `created_at`, `updated_at`, `UNIQUE (points_voldoende, points_goed, points_uitstekend)` en `CHECK (points_voldoende > 0 AND points_voldoende < points_goed AND points_goed < points_uitstekend AND points_uitstekend <= 100)`. Zet commentaar bij de kolommen.
  *Klaar als:* het bestand in een lege SQLite-database zonder fouten draait.
- [x] **1.2 Systeemschema in `schema.sql`:** `INSERT OR IGNORE` van "Standaard (3/4/5)" met `owner_id` NULL.
- [x] **1.3 Kolommen in `exams` (`schema.sql`):** `grading_scale TEXT NOT NULL DEFAULT 'points'` (`points` | `levels`), `grading_scheme_id INTEGER` (FK `grading_schemes` ON DELETE RESTRICT) en `show_grade_label INTEGER DEFAULT 0`.
- [x] **1.4 Kolom in `student_answers` (`schema.sql`):** `teacher_level TEXT` (NULL, of een van de vier niveaus).
- [x] **1.5 Kolommen in `student_exams` (`schema.sql`):** `grade_override REAL`, `grade_override_label TEXT`, `grade_override_reason TEXT`, `grade_override_by INTEGER` (FK `users` ON DELETE SET NULL), `grade_override_at DATETIME` en `grade_override_basis REAL`.
- [x] **1.6 Kolom in `answer_assessments` (`schema.sql`):** `final_level TEXT`, naast `final_score`.
- [x] **1.7 Kolom in `prompts` (`schema.sql`):** `grading_scale TEXT NOT NULL DEFAULT 'points'`.
- [x] **1.8 Migratie: de tabel.** Voeg in `Database::migrate()` (`config/database.php`) `CREATE TABLE IF NOT EXISTS grading_schemes` toe, plus dezelfde `INSERT OR IGNORE` als in 1.2.
- [x] **1.9 Migratie: de kolommen.** Voeg de kolommen uit 1.3–1.7 toe met `ALTER TABLE … ADD COLUMN`, elk na een controle via `PRAGMA table_info`, zoals de bestaande stappen. Lukt een `CHECK` niet bij `ADD COLUMN`, laat die dan weg in de migratie: de PHP-validatie (fase 2) bewaakt de waarden.
  *Klaar als:* de kolommen en het systeemschema bestaan in een **nieuwe** database (`cd docker && docker compose down -v` en daarna `./docker/start.sh`) **en** in een **bestaande** database (rebuild zonder `-v`). Controle: `docker compose exec web php -r 'print_r((new PDO("sqlite:/var/www/html/database/database.sqlite"))->query("SELECT * FROM grading_schemes")->fetchAll());'`
- [x] **1.10 Instellingen in `config/app.php`:** `LEVELS_AI_ENABLED = false` (met commentaar over de uitrol) en `MAX_GRADE_OVERRIDE_REASON = 1000`.
  *Klaar als (hele fase):* de PHP-syntaxcheck slaagt. Commit: `Add grading schemes and level columns`.

## Fase 2: Rekenkern (`app/models/Grading.php`)

Alle rekenregels staan op één plek, zodat de aggregatie niet opnieuw op meerdere plekken gedupliceerd raakt.

- [x] **2.1 Constanten.** Maak `class Grading` met `SCALE_POINTS = 'points'`, `SCALE_LEVELS = 'levels'` en `LEVELS = ['onvoldoende', 'voldoende', 'goed', 'uitstekend']`, in volgorde van laag naar hoog.
- [x] **2.2 Labels.** `levelLabel(string $level): string` ("Onvoldoende" …) en `levelClass(string $level): string` (Bootstrap-badgekleur: danger, warning, info, success).
- [x] **2.3 Punten.** `pointsFor(string $level, array $scheme): int`: onvoldoende geeft 0, de rest komt uit `points_<niveau>`.
- [x] **2.4 Cijfer.** `grade(array $levels, array $scheme): ?float`: `null` als de lijst leeg is of een `null` bevat, anders `round(10 * som / (aantal * points_uitstekend), 1)`.
  *Klaar als:* met schema 3/4/5 `["uitstekend","goed","voldoende","onvoldoende"]` 6.0 geeft, vier keer uitstekend 10.0, vier keer onvoldoende 0.0, en `["goed", null]` `null`.
- [x] **2.5 Woord.** `gradeLabel(float $grade): string`: rond af met `(int)floor($grade + 0.5)`, daarna 0–5 onvoldoende, 6–7 voldoende, 8–9 goed, 10 uitstekend.
  *Klaar als:* 5.4 onvoldoende geeft, 5.5 voldoende, 7.4 voldoende, 7.5 goed, 9.4 goed en 9.5 uitstekend.
- [x] **2.6 Opmaak.** `formatGrade(float $grade): string` geeft een Nederlandse notatie met één decimaal ("7,5").
- [x] **2.7 Schema valideren.** `validateScheme(string $name, $v, $g, $u): ?string` geeft een Nederlandse foutmelding of `null`. Regels: naam 1–100 tekens, gehele getallen, `0 < v < g < u ≤ 100`.
- [x] **2.8 Niveau valideren.** `isLevel($value): bool`.
  *Klaar als (hele fase):* de syntaxcheck slaagt en de controles uit 2.4 en 2.5 kloppen (via het `docker run`-commando bovenaan). Commit: `Add grading calculation helpers`.

## Fase 3: Puntenschema's beheren

- [x] **3.1 Model `app/models/GradingScheme.php`:** `all()` (op naam, met de naam van de eigenaar), `find($id)`, `findByPoints($v, $g, $u)`, `create($name, $v, $g, $u, $ownerId): int`, `update($id, $name, $v, $g, $u)` en `delete($id)`. Alleen prepared statements.
- [x] **3.2 In gebruik?** `usageCount($id): int` (toetsen met dit schema) en `isLocked($id): bool` (minstens één toets met dit schema heeft een poging met `completed_at` gevuld).
- [x] **3.3 Mag deze gebruiker wijzigen?** `canManage(array $scheme): bool`: admin altijd; anders alleen als `owner_id` gelijk is aan de huidige gebruiker. Een systeemschema (`owner_id` NULL) alleen door de admin.
- [x] **3.4 Controller `app/controllers/GradingSchemeController.php`** met `index()` voor action `grading_schemes`, `requireRole('docent')`. De view `app/views/docent/grading_schemes.php` toont een tabel met naam, punten (V/G/U), eigenaar en het aantal toetsen, plus knoppen Wijzigen/Verwijderen alleen als `canManage()`.
- [x] **3.5 Cases** in `htdocs/index.php` voor `grading_schemes`, `create_grading_scheme`, `store_grading_scheme`, `edit_grading_scheme`, `update_grading_scheme` en `delete_grading_scheme`.
- [x] **3.6 Formulier** `app/views/docent/grading_scheme_form.php` (voor aanmaken én wijzigen): naam en drie getallen, de vaste regel "Onvoldoende = 0 punten" als tekst, `csrfInput()` en alles via `e()`. Zet er een rekenvoorbeeld bij: "Alles voldoende = 10 × V / U".
- [x] **3.7 `store()`:** `validateCsrfToken()`, `requireRole('docent')`, invoer via `requestString`/`requestInt`, daarna `Grading::validateScheme()`. Bestaat de combinatie al (`findByPoints`), meld dan: "Dit puntenschema bestaat al: <naam>." Na `create()`: `AuditLog::log('grading_scheme_create', …)` en een redirect naar `grading_schemes`.
- [x] **3.8 `edit()`/`update()`:** zelfde patroon, plus `canManage()` (anders 403) en `isLocked()` (anders een melding: "Dit schema wordt gebruikt door een toets met resultaten. Maak een nieuw schema."). Bij `update` nogmaals de UNIQUE-controle, met uitzondering van het schema zelf. Daarna `AuditLog::log('grading_scheme_update', [old/new])`.
- [x] **3.9 `delete()`:** POST via een link met `data-confirm`, `canManage()`, en alleen als `usageCount() === 0`, anders een melding. Daarna `AuditLog::log('grading_scheme_delete', …)`.
- [x] **3.10 Navigatie:** een link "Puntenschema's" in `layouts/main.php` voor docent en admin.
  *Klaar als:* een docent in Docker een schema maakt, wijzigt en verwijdert; een dubbele combinatie een melding geeft; een tweede docent het schema ziet maar niet kan wijzigen (ook niet via een handmatige POST: 403); en een GET op `store_grading_scheme` een 405 geeft. Commit: `Manage grading schemes`.

## Fase 4: Toetsinstellingen

- [x] **4.1 `Exam::create()` en `Exam::update()`** krijgen `$gradingScale`, `$gradingSchemeId` en `$showGradeLabel`.
- [x] **4.2 `Exam::hasSubmittedAttempts($id): bool`** (een poging met `completed_at` gevuld).
- [x] **4.3 Toetsformulier (`exam_form.php`):** keuzerondjes "Scoren met punten (0–10, huidige manier)" en "Beoordelen met niveaus"; een selectbox met alle puntenschema's (standaard het systeemschema); een vinkje "Toon het eindcijfer als woord". Het schema en het vinkje zijn alleen zichtbaar bij niveaus (met een event listener en een nonce-script, geen inline handlers). Zet een link naar "Puntenschema's" bij de selectbox.
- [x] **4.4 Schaal vastzetten.** Bij `hasSubmittedAttempts()` toont het formulier de schaal alleen ter informatie, met de uitleg "Kan niet meer wijzigen: er zijn al resultaten".
- [x] **4.5 `storeExam()`:** valideer de schaal (`points`|`levels`, anders 400). Bij `levels` moet het schema bestaan (anders 400); bij `points` wordt `grading_scheme_id` NULL en `show_grade_label` 0.
- [x] **4.6 `updateExam()`:** dezelfde validatie. Is de schaal gewijzigd terwijl `hasSubmittedAttempts()` waar is, geef dan `abort(400, …)`. Neem een gewijzigd schema of vinkje op in de bestaande `AuditLog::log()` van de update (oud/nieuw).
- [x] **4.7 Promptkeuze filteren:** het formulier toont alleen prompts met dezelfde `grading_scale` als de toets. De controller controleert dat ook (een prompt met een andere schaal geeft 400). Bij een wisseling van schaal in het formulier wordt de promptlijst gefilterd (script).
- [x] **4.8 `Exam::duplicate()`:** kopieert `grading_scale`, `grading_scheme_id` en `show_grade_label`, plus bij de pogingen `teacher_level` en de zes `grade_override*`-kolommen.
- [x] **4.9 Prompts:** `prompt_form.php` krijgt de keuze voor de schaal; `PromptController` (of de huidige plek) valideert en bewaart die. `prompt_help.php` krijgt een voorbeeld voor niveaus naast het puntenvoorbeeld.
  *Klaar als:* een nieuwe toets met niveaus en schema 3/4/5 wordt bewaard; een bestaande toets blijft `points`; de schaal is niet te wijzigen na een ingeleverde poging (ook niet via een handmatige POST); en een gedupliceerde toets neemt alles over. Commit: `Choose grading scale and scheme per exam`.

## Fase 5: De docent beoordeelt per niveau

- [x] **5.1 `StudentAnswer::updateTeacherLevel($id, ?string $level, $feedback)`:** schrijft `teacher_level` en `teacher_feedback` en laat `teacher_score` NULL.
- [x] **5.2 Toets meesturen:** zorg dat `gradeStudentExam()` en `viewStudentAnswers()` de toets (met `grading_scale`) aan de view geven en dat de query's `teacher_level` meenemen.
- [x] **5.3 `grade_exam.php`:** bij `levels` vier keuzerondjes (Onvoldoende/Voldoende/Goed/Uitstekend) plus "Nog niet beoordeeld" in plaats van het getalveld. Bij `points` blijft alles zoals het is.
- [x] **5.4 `student_answers.php`:** hetzelfde voor het docentformulier, en de badge "Docentscore" wordt bij `levels` "Docentniveau: <label>" met `levelClass()`.
- [x] **5.5 `saveTeacherFeedback()`:** haal de schaal van de toets op via `StudentAnswer::findWithExam()`, dus uit de database en niet uit de POST. Bij `levels`: `teacher_level` uit de POST, leeg of `Grading::isLevel()` (anders 400 "Ongeldig niveau."), daarna `updateTeacherLevel()`. Bij `points`: de huidige code.
- [x] **5.6 Audit:** neem in `teacher_grade` bij `levels` `teacher_level` (oud/nieuw) op in plaats van `teacher_score`.
- [x] **5.7 `pendingAssessments()`:** "x / y beoordeeld" telt bij `levels` `teacher_level` in plaats van `teacher_score` (`COUNT(COALESCE(sa.teacher_score, sa.teacher_level))`).
  *Klaar als:* een docent en een beoordelaar in Docker per antwoord een niveau kunnen geven en weer leegmaken, een ongeldig niveau via een handmatige POST een 400 geeft, en een `points`-toets onveranderd werkt. Commit: `Grade answers with levels`.

## Fase 6: Eindcijfer berekenen en tonen

- [x] **6.1 `Grading::attemptResult(int $studentExamId): array`**, de enige plek voor het eindresultaat van een poging. Geeft terug: `scale`, `graded` (aantal beoordeeld), `total`, `computed` (float of null), `override` (cijfer of woord, of null), `final` (`override` als die er is, anders `computed`), `label` (woord als `show_grade_label` aan staat, voor een override het opgegeven woord) en `override_outdated` (`grade_override_basis` ≠ `computed`).
  Bij `points` is `computed` het huidige gemiddelde van de docentscores, zodat beide schalen dezelfde vorm hebben.
- [x] **6.2 `StudentAnswer::aiLevels(?string $aiFeedback, ?string $agenticLevel): array`:** zoals `aiScores()`, maar met de regex `/Model:\s+(.+?)\s+.*?Niveau:\s+(onvoldoende|voldoende|goed|uitstekend)/is` en de bron `Agentic AI` voor het agentic niveau. Nog niet gebruikt door de worker, wel al door de views.
- [x] **6.3 `AnswerAssessment::agenticLevelSql()`:** zoals `agenticScoreSql()`, maar voor `final_level`.
- [x] **6.4 `Grading::aiGrades(array $answers, array $scheme): array`:** per bron (model of "Agentic AI") het cijfer over de antwoorden waarvoor die bron een niveau heeft.
- [x] **6.5 `viewStudentAnswers()`:** gebruik bij `levels` `attemptResult()` en `aiGrades()` in plaats van de lus met `$totalScore`/`$finalAiScores`. Toon per antwoord de AI-niveaus als badges.
- [x] **6.6 `student_answers.php`:** een kaart "Eindcijfer" met het berekende cijfer, het woord (als dat aan staat) of "x van y beoordeeld", en de AI-cijfers per model.
- [x] **6.7 `StudentExamController::viewResults()` en `view_results.php`:** per vraag het docentniveau (label) en de feedback; bovenaan `final` (of `label`). **Niet** tonen: `computed`, de reden of dat het cijfer is aangepast (B8). Het tonen van AI-resultaten blijft zoals het nu is.
- [x] **6.8 `viewExamResults()` en `exam_results.php`:** een kolom "Eindcijfer" per poging via `attemptResult()`, met een markering "aangepast" als er een override is.
  *Klaar als:* bij een toets met vier vragen en schema 3/4/5 de niveaus U/G/V/O overal 6,0 tonen (docentweergave, resultatenlijst, student); na een wissel naar schema 7/9/10 klopt het nieuwe cijfer; en het woord verschijnt alleen als het vinkje aan staat. Commit: `Calculate final grade from levels`.

## Fase 7: Eindcijfer handmatig aanpassen

- [x] **7.1 Model:** `StudentExam`-functies (of in `Grading`): `setOverride($id, ?float $grade, ?string $label, string $reason, int $userId, ?float $basis)` en `clearOverride($id)`.
- [x] **7.2 Formulier in `student_answers.php`** onder de kaart "Eindcijfer": bij `show_grade_label` een keuze uit vier woorden, anders een getalveld (0–10, stap 0,1), en een verplicht tekstveld "Reden". Een bestaande aanpassing toon je met wie, wanneer, de reden en het berekende cijfer op dat moment, plus een knop "Aanpassing verwijderen" (`data-confirm`).
- [x] **7.3 Waarschuwing:** bij `override_outdated` een gele melding "Het berekende cijfer is gewijzigd sinds de aanpassing (toen x, nu y)."
- [x] **7.4 Action `override_final_grade`** (`DocentController`) met een `case` in `index.php`: `validateCsrfToken()`, `requireRole('beoordelaar')`, `requestInt($_POST, 'student_exam_id')`, de poging uit de database ophalen (anders 404) en `checkGradingPermission($examId)`.
- [x] **7.5 Validatie:** de reden is 1–`MAX_GRADE_OVERRIDE_REASON` tekens (anders 400). Cijfer: getal 0–10 met hooguit één decimaal, een komma mag (`str_replace(',', '.', …)`), afronden op 0,1. Woord: `Grading::isLevel()`. Welk van de twee bepaalt `show_grade_label` van de toets uit de database.
- [x] **7.6 Opslaan en loggen:** `setOverride()` met `basis = attemptResult()['computed']`, daarna `AuditLog::log('final_grade_override', [student_exam_id, old, new, reason, computed])` en een redirect terug.
- [x] **7.7 Action `clear_final_grade_override`:** zelfde checks, `clearOverride()` en `AuditLog::log('final_grade_override_clear', …)`.
- [x] **7.8 Ook in de blinde beoordeling** (`grade_exam.php`) voor beoordelaars, met hetzelfde formulier (gedeelde partial).
  *Klaar als:* een docent en een beoordelaar het cijfer kunnen aanpassen en terugzetten; zonder reden komt er een foutmelding; de student ziet alleen het nieuwe cijfer; de waarschuwing verschijnt na een gewijzigd niveau; een docent zonder toegang tot de toets een 403 krijgt; en een GET een 405 geeft. Commit: `Allow manual final grade with reason`.

## Fase 8: Vergelijking en export

- [ ] **8.1 `compareExamResults()`:** voor `levels` rijen met `teacher_level` en de AI-niveaus. Voeg per model een 4×4-kruistabel toe (docentniveau tegen AI-niveau), plus het percentage exact gelijk en het percentage gelijk op voldoende/onvoldoende.
- [ ] **8.2 `exam_comparison.php`:** toon de kruistabellen bij `levels`. De scatterplot gebruikt de niveau-index 0–3 als as, met labels. `points` blijft ongewijzigd.
- [ ] **8.3 `exportExamComparison()`:** bij `levels` de kolommen docentniveau, het niveau per model en "gelijk (ja/nee)". Tekst altijd via `csvSafe()`.
- [ ] **8.4 Dashboard:** controleer of het dashboard scores toont (er staan wijzigingen klaar op `dev-dashboard-filter`) en gebruik daar `attemptResult()`.
  *Klaar als:* de vergelijkingspagina en de CSV-export kloppen voor een `levels`-toets met een paar handmatig ingevulde AI-niveaus, en een `points`-toets er hetzelfde uitziet als vroeger. Commit: `Compare levels between teacher and AI`.

## Fase 9: Worker-contract, webapp-kant

- [ ] **9.1 `getPendingAiGrading()`:** selecteer `e.grading_scale` mee. Sla `levels`-toetsen over zolang `LEVELS_AI_ENABLED` uit staat (`AND (e.grading_scale = 'points' OR ?)`).
- [ ] **9.2 `getOpenAnswers()`:** elk antwoord krijgt het veld `grading_scale`, dat al uit de query in 9.1 komt.
- [ ] **9.3 `AnswerAssessment::createAutomaticRuns()` en `getPendingJobs()`:** dezelfde filter op de vlag. `getOpenAssessmentJobs()` stuurt `grading_scale` mee.
- [ ] **9.4 `getOpenDesignJobs()`:** stuur `grading_scale` van de toets mee. Hier is geen vlag nodig: de parser accepteert beide rubricformaten (fase 12).
- [ ] **9.5 `AnswerAssessment::normalizeDecision()`:** accepteer optioneel `level` (enum). `normalizeAssessment()`/`normalizeValidation()` accepteren bij `levels` een niveau als `score`. Geef de schaal mee als parameter, uit de run of de toets in de database, niet uit de body.
- [ ] **9.6 `AnswerAssessment::saveResult()`:** schrijf bij `levels` `final_level` en laat `final_score` NULL.
- [ ] **9.7 `AnswerAssessment::studentSummary()`:** geeft bij `levels` `level` terug in plaats van `score`.
- [ ] **9.8 `QuestionDesign::rubricToCriteriaText()`:** krijgt de schaal mee. Bij `levels` komt er het kopje `Niveaus:` met `Uitstekend:` / `Goed:` / `Voldoende:` / `Onvoldoende:`. `normalize*()` accepteert bij `levels` de sleutels `level_uitstekend`, `level_goed`, `level_voldoende` en `level_onvoldoende`, met dezelfde limieten als nu. `question_design_rubric.php` toont de juiste labels.
  *Klaar als:* met de vlag uit verschijnen `levels`-antwoorden niet in `open_student_answers` (controleer met `curl` en een worker-key); met de vlag aan wel, met `"grading_scale": "levels"`; en `points`-antwoorden blijven gelijk. Commit: `Send grading scale to workers`.

## Fase 10: AI-worker (`bin/process_ai_feedback.py`)

- [ ] **10.1 Constanten:** `LEVELS = ["onvoldoende", "voldoende", "goed", "uitstekend"]` en `LEVELS_FEEDBACK_SCHEMA` (zoals `FEEDBACK_SCHEMA`, maar met `level` als enum in plaats van `score`).
- [ ] **10.2 Schaal lezen:** `job_scale(q) -> str` geeft `q.get("grading_scale")`, of `"points"` als die ontbreekt of onbekend is.
- [ ] **10.3 `validate_level_feedback()`:** zoals `validate_feedback()`, maar `level` moet in `LEVELS` staan.
- [ ] **10.4 `DEFAULT_LEVELS_PROMPT`:** zoals `DEFAULT_SYSTEM_PROMPT`, met de vier niveaus en hun betekenis: onvoldoende = de essentie ontbreekt; voldoende = de essentie is er; goed en uitstekend = de student laat meer zien. Geen punten noemen.
- [ ] **10.5 `level_from_statuses(criteria) -> str`:** de regels van B3. Schrijf eerst de test (10.10).
- [ ] **10.6 Rubric-pad bij `levels`:** het model geeft alleen statussen per criterium en feedback, geen score. Het niveau komt uit `level_from_statuses()`. Pas `rubric_feedback_schema()` en `build_rubric_prompts()` aan met een parameter voor de schaal; het `points`-pad blijft gelijk.
- [ ] **10.7 Pad zonder rubric bij `levels`:** het model kiest het niveau met `LEVELS_FEEDBACK_SCHEMA` en `DEFAULT_LEVELS_PROMPT`, of met de prompt van de toets (B9).
- [ ] **10.8 Uitvoer in `process_answer()`:** bij `levels` `Niveau: <niveau>` in plaats van `Aantal punten: N`. Een vermoedelijke injectie met `INJECTION_ZERO_SCORE` geeft `onvoldoende`.
- [ ] **10.9 `clean_output_text()`:** voeg `Niveau` toe aan de geneutraliseerde labels (tegen spoofing).
- [ ] **10.10 Tests in `bin/test_rubric_grading.py`:** `level_from_statuses()` voor elke rij van B3, plus een rubric zonder aanvullende criteria (hooguit voldoende); een `levels`-job met een gemockte `call_ollama` geeft `Niveau: goed`; een job zonder `grading_scale` geeft `Aantal punten:` zoals voorheen; en `Niveau:` in modeluitvoer wordt geneutraliseerd.
  *Klaar als:* `cd bin && python3 -m unittest test_rubric_grading -v` slaagt en de Python-syntaxcheck ook. Commit: `Grade with levels in AI worker`.

## Fase 11: Assessment-agents (`bin/assessment_agents.py`)

- [ ] **11.1 Schemas per schaal:** `assessment_schema()` en `validation_schema()` krijgen de schaal. Bij `levels` is `score` de niveau-enum. `validate_assessment()`/`validate_validation()` doen hetzelfde.
- [ ] **11.2 Prompts:** `ASSESSMENT_PROMPT` en `VALIDATION_PROMPT` krijgen een niveauvariant (stap 5 "Kies daarna de score…" wordt "Het niveau volgt uit de statussen: …" met de regels van B3).
- [ ] **11.3 `format_rubric()`:** toont bij `levels` de niveaus in plaats van de puntentoekenning.
- [ ] **11.4 `decide()` bij `levels`:** het niveau is `level_from_statuses()` op de statussen uit de laatste validatie. Wijkt het niveau dat het model noemt daarvan af, dan komt er een reden bij in `reasons` en wint het berekende niveau. Een essentieel criterium op `deels` geeft de reden "Grensgeval voldoende/onvoldoende" (dus menselijke beoordeling). `decision` krijgt `level`.
- [ ] **11.5 Orchestrator:** leest `grading_scale` uit de job (standaard `points`) en geeft die door aan de agents en aan `decide()`.
- [ ] **11.6 Fixtures** in `bin/fixtures/assessment/`: voeg een `levels`-variant toe; de bestaande blijven.
- [ ] **11.7 Tests in `bin/test_assessment_agents.py`:** `decide()` voor elk niveau, een afwijkend modelniveau (berekend niveau wint, met een reden), het grensgeval `deels`, en een job zonder `grading_scale` die werkt zoals voorheen.
  *Klaar als:* `cd bin && python3 -m unittest test_assessment_agents -v` slaagt. Commit: `Assess with levels in agentic worker`.

## Fase 12: Rubricformaat en vraagontwerper

- [ ] **12.1 `parse_rubric_criteria()`:** herken naast `Puntentoekenning:` ook `Niveaus:` met `Uitstekend:` / `Goed:` / `Voldoende:` / `Onvoldoende:` (regex zoals `_RUBRIC_LEVEL`). Het resultaat krijgt `levels_format: "points"|"levels"`. Voor de beoordeling bij `levels` tellen alleen de criteria (B3); de niveauteksten zijn toelichting.
- [ ] **12.2 Fixture** `bin/fixtures/criteria_rubric_levels.txt`, aangemaakt met de nieuwe `rubricToCriteriaText()` (stap 9.8). Het bestaande `criteria_rubric.txt` blijft.
- [ ] **12.3 Tests:** de parser accepteert beide formaten en weigert een mengsel van beide.
- [ ] **12.4 `bin/design_agents.py`:** `LEVELS` per schaal (`level_10`… of `level_uitstekend`…). De prompt voor het rubricvoorstel krijgt bij `levels` de uitleg uit B3 en de eis van minstens één aanvullend criterium. `validate_*()` volgt de schaal uit de job.
- [ ] **12.5 Validatiecheck `levels`** (bestaande check): bij `levels` betekent die "minstens één aanvullend criterium en de niveaus volgen uit de criteria".
- [ ] **12.6 Tests in `bin/test_design_agents.py`:** een `levels`-job levert de nieuwe sleutels, een job zonder schaal levert de oude.
  *Klaar als:* alle drie de mocktestsuites slagen en een goedgekeurd ontwerp in een `levels`-toets in Docker criteria met `Niveaus:` oplevert. Commit: `Support levels in rubric format and designer`.

## Fase 13: Integratie-API (contract 9, alleen toevoegingen)

- [ ] **13.1 `integration_exams`:** per toets het veld `grading_scale`.
- [ ] **13.2 `IntegrationAttempt::answerResult()`:** bij `levels` `level` (per model in `model_levels`, of agentic) in plaats van `score`/`model_scores`. Confidence volgens de regels hieronder (13.3).
- [ ] **13.3 Confidence bij niveaus:** hoog als alle modellen hetzelfde niveau geven; **laag en altijd review** als het ene model onvoldoende geeft en een ander voldoende of hoger; middel bij andere verschillen of bij één model.
- [ ] **13.4 `IntegrationAttempt::summary()`:** voeg `grade` (`final` uit `attemptResult()`), `grade_label` (of null) en `grade_overridden` (true/false) toe. `ai_score` en `teacher_score` blijven bij `points` gelijk en zijn bij `levels` null.
- [ ] **13.5 `integration_attempt_review`:** bij `levels` verwacht `grades[]` `level` in plaats van `score` (anders 400 met `Invalid level in grades[i]`). Bij `points` blijft alles gelijk. Gebruik `StudentAnswer::updateTeacherLevel()` in `saveReview()`.
- [ ] **13.6 Webhooks:** controleer dat de body niet verandert (geen scores of cijfers).
- [ ] **13.7 Demo** (`docs/integration-demo/demo_site.py`): toon `grade`/`grade_label` als die er zijn.
  *Klaar als:* met de demo-site een `points`-toets precies dezelfde JSON geeft als vroeger (alleen nieuwe velden erbij), en een `levels`-toets het cijfer en de niveaus toont en een review met `level` accepteert. Commit: `Expose levels and final grade in integration API`.

## Fase 14: Documentatie

- [ ] **14.1 `MANUAL.md`:** puntenschema's maken, schaal en schema kiezen bij een toets, beoordelen per niveau, de woordbeoordeling en het eindcijfer handmatig aanpassen (met het rekenvoorbeeld).
- [ ] **14.2 `ARCHITECTURE.md`:** het nieuwe datamodel, `Grading::attemptResult()` als enige plek voor het eindcijfer, en contracten 1, 2, 4, 7, 8 en 9.
- [ ] **14.3 `CLAUDE.md`:** werk de contracten 1, 4, 7, 8 en 9 en de valkuil over de score-aggregatie bij (nu één plek: `Grading`).
- [ ] **14.4 `docs/integration-api.md`:** de nieuwe velden, `level` bij een review en de confidence-regels voor niveaus.
- [ ] **14.5 `bin/README.md`:** het veld `grading_scale` en het gedrag bij `levels`. Er zijn geen nieuwe instellingen in `config.py`; vermeld dat expliciet.
- [ ] **14.6 `docs/rollout-level-grading.md`** volgens het voorbeeld van `docs/rollout-new-version.md`: eerst de webapp met de vlag uit, dan de worker, dan de vlag aan, en hoe je teruggaat.
- [ ] **14.7 `docs/security-issues.txt`:** de nieuwe muterende actions (schema's, override) met hun autorisatie, en de neutralisatie van het label `Niveau:`.

## Fase 15: Afronding

- [ ] **15.1** De PHP- en Python-syntaxcheck en alle drie de mocktestsuites slagen.
- [ ] **15.2** Rooktest met een **nieuwe** database (`docker compose down -v`) en met een **bestaande** database met oude `points`-toetsen: die moeten er precies zo uitzien als vroeger.
- [ ] **15.3** Een volledige ronde met de vlag aan en een cloud-model (bijvoorbeeld `gpt-oss:120b-cloud`, **geen lokaal model**): een `levels`-toets met een rubric maken, als student maken, de AI-niveaus en het cijfer controleren, een niveau aanpassen en het eindcijfer handmatig aanpassen.
- [ ] **15.4** Loop de [merge-checklist in CLAUDE.md](CLAUDE.md#checklist-voor-een-merge-naar-main) na.
- [ ] **15.5** De PR-beschrijving vermeldt de uitrolvolgorde (webapp met `LEVELS_AI_ENABLED = false`, worker, vlag aan) en dat er op de workermachine geen `config.py`-wijziging nodig is.

---

## Later (buiten deze branch)

- Een weging per vraag (een vraag telt dubbel).
- Een bestaande `points`-toets omzetten naar `levels` (met een expliciete mapping en opnieuw beoordelen).
- `bin/dataset_import.py` laten importeren met niveaus.
- Het eindcijfer aanpassen via de integratie-API door de beoordelaar van de externe website.
- Per criterium `deels` laten meetellen voor *goed* (nu telt alleen `voldaan`).
