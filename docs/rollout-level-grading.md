# Beoordelen met niveaus in gebruik nemen

Deze handleiding beschrijft hoe je beoordelen met niveaus en puntenschema's
(branch `dev-rubric-levels`) uitrolt: eerst de webapplicatie met de
overgangsvlag uit, daarna de drie workers op de worker-machine (Windows), en
pas dan de vlag aan.

## Wat er verandert

Bestaande toetsen blijven `points` en werken precies zoals vroeger. Alle
contracten veranderen alleen **additief**:

| Onderdeel | Wijziging | Gevolg voor bestaande onderdelen |
|---|---|---|
| Database | Nieuwe tabel `grading_schemes` (met het systeemschema "Standaard (3/4/5)"); nieuwe kolommen `exams.grading_scale` (standaard `points`), `exams.grading_scheme_id`, `exams.show_grade_label`, `student_answers.teacher_level`, zes `student_exams.grade_override*`-kolommen, `answer_assessments.final_level` en `prompts.grading_scale` (standaard `points`) | Wordt automatisch aangemaakt door `Database::migrate()` bij de eerste request. Bestaande rijen krijgen veilige defaults |
| Webapp | Toetsen met **"Beoordelen met niveaus"**, beheer van puntenschema's, eindcijfer per poging (`Grading::attemptResult()`), woordbeoordeling, eindcijfer handmatig aanpassen, kruistabellen in "Vergelijk AI" | Een `points`-toets ziet er hetzelfde uit; nieuw zijn de kolom *Eindcijfer* in de resultatenlijst en de kaart om het eindcijfer handmatig aan te passen (dat kan bij beide schalen) |
| Worker-API (contract 2) | Elke job van `open_student_answers`, `open_assessment_jobs` en `open_design_jobs` krijgt het veld `grading_scale` | Een oude worker negeert het veld. Een nieuwe worker zonder het veld gaat uit van `points` |
| `ai_feedback` (contract 1) | Bij `levels` schrijft de worker `Niveau: <niveau>` in plaats van `Aantal punten: N` | De webapp leest beide (`aiScores()` en `aiLevels()`) |
| Rubric-tekst (contract 7) | Nieuw kopje `Niveaus:` naast `Puntentoekenning:` | De nieuwe parser herkent beide; de oude kent `Niveaus:` niet |
| Integratie-API (contract 9) | Alleen nieuwe velden: `grading_scale`, `level`, `grade`, `grade_label`, `grade_overridden`; een review met `level` bij een `levels`-toets | Een `points`-toets geeft precies dezelfde JSON plus de nieuwe velden. **De webhooks veranderen niet** |
| Worker | `process_ai_feedback.py`, `assessment_agents.py` en `design_agents.py` begrijpen `grading_scale` | **Geen nieuwe instellingen in `bin/config.py`** |

### Waarom een overgangsvlag

Een **oude worker** kent `grading_scale` niet. Krijgt hij een antwoord van een
`levels`-toets, dan beoordeelt hij het met punten (`Aantal punten: 5`) en dat
niveau ziet de webapp nooit. Daarom staat in `config/app.php`:

```php
const LEVELS_AI_ENABLED = false;
```

Zolang die vlag uit staat, gaan `levels`-toetsen **niet** naar de AI-worker en
**niet** naar de assessment-worker (ook niet handmatig). Docenten kunnen
`levels`-toetsen dan wel al maken en zelf beoordelen. Toetsen met `points`
merken niets van de vlag.

De volgorde is dus:

1. Webapp met `LEVELS_AI_ENABLED = false`. Veilig met de oude worker.
2. Workers. Werken met de oude én de nieuwe webapp.
3. `LEVELS_AI_ENABLED = true` in de webapp.

## Voordat je begint

1. **Maak een backup van de database** (het SQLite-bestand in `database/`).
2. Controleer dat de drie workers nu goed draaien (geen `401` in de uitvoer).

## Stap 1 – Webapp bijwerken (vlag uit)

Haal de code binnen op de live server, bijvoorbeeld met `git pull`, en
controleer dat in `config/app.php` staat:

```php
const LEVELS_AI_ENABLED = false;
```

Laat de pagina één keer laden (bijvoorbeeld het dashboard); dan voert
`Database::migrate()` de migratie uit. Controleer:

- Het menu toont **Puntenschema's** met het schema "Standaard (3/4/5)".
- Een bestaande toets toont bij *Bewerken* "Scoren met punten" (of, met
  resultaten, die keuze als vaste tekst).
- `open_student_answers` geeft voor elk antwoord `"grading_scale": "points"`:

```bash
curl -s -H "Authorization: Bearer <WORKER_KEY>" "https://<server>/api/index.php?action=open_student_answers&limit=1"
```

- Een proef-toets met niveaus: maak er een, lever een poging in en controleer
  dat dat antwoord **niet** in `open_student_answers` staat.

De oude workers draaien in deze stap gewoon door.

## Stap 2 – Workers bijwerken

Op de worker-machine:

1. Haal de nieuwe code binnen (`bin/process_ai_feedback.py`,
   `bin/assessment_agents.py`, `bin/design_agents.py` en de tests).
2. **`bin/config.py` hoeft niet te veranderen.** Er zijn geen nieuwe
   instellingen; alles werkt met de bestaande `config.py`.
3. Draai de mocktests (die sturen niets naar Ollama of de webapp):

```bash
cd bin
python -m unittest test_rubric_grading test_assessment_agents test_design_agents
```

4. Herstart de drie workers (`process_ai_feedback.py`,
   `process_design_jobs.py`, `process_assessment_jobs.py`) op de manier
   waarop ze normaal draaien. Controleer dat er geen `401` of foutmeldingen
   in de uitvoer staan en dat `points`-antwoorden zoals altijd
   `Aantal punten:` krijgen.

## Stap 3 – Vlag aanzetten

Zet in `config/app.php` op de live server:

```php
const LEVELS_AI_ENABLED = true;
```

(Leeg zo nodig de opcache, bijvoorbeeld door PHP-FPM te herstarten.)
Controleer daarna:

- Het antwoord van de proef-toets met niveaus staat nu in
  `open_student_answers`, met `"grading_scale": "levels"` (of, bij een
  rubric-vraag, in `open_assessment_jobs`).
- Na de volgende ronde van de worker toont de antwoordenpagina bij dat
  antwoord AI-niveaus (badges) en staat in de AI-feedback `Niveau: …`.
- Een `points`-toets ziet er nog precies zo uit als voor de uitrol.

## Terugdraaien

**Alleen de AI voor niveaus stoppen:** zet `LEVELS_AI_ENABLED` weer op
`false`. `Levels`-toetsen gaan dan niet meer naar de workers; docenten
beoordelen zelf. Bestaande AI-niveaus blijven staan.

**De worker terugzetten naar de oude code:** kan alleen veilig met
`LEVELS_AI_ENABLED = false` (anders beoordeelt de oude worker
`levels`-antwoorden met punten). Zet dus eerst de vlag uit, dan de oude worker.

**De webapp terugzetten naar de oude code:** de nieuwe tabel en kolommen
blijven bestaan; de oude code negeert ze. Let op:

- De oude webapp kent geen niveaus: bij een `levels`-toets ziet hij geen
  docentniveaus, geen eindcijfer en geen handmatige aanpassing, en hij
  **stuurt `levels`-antwoorden naar de worker als `points`**. Zet daarom
  vóór het terugzetten bij elke toets met niveaus *AI-beoordeling* uit.
- Wil je alles echt terug zoals het was, zet dan de databasebackup van vóór
  de uitrol terug. Wat docenten sindsdien met niveaus hebben beoordeeld, ben
  je dan kwijt.

Ga je daarna weer naar de nieuwe code, dan werkt alles zonder extra stappen:
de migratie is idempotent.

## Lokale Docker-testomgeving

De testcontainer (`docker/`) kopieert de code tijdens het bouwen; na een
codewijziging opnieuw bouwen met `./docker/start.sh`. Om de AI voor niveaus
lokaal te testen, zet je `LEVELS_AI_ENABLED` tijdelijk op `true` en bouw je
opnieuw. Test live alleen met een cloud-model (bijvoorbeeld
`gpt-oss:120b-cloud`), niet met een lokaal model.

## Checklist

- [ ] Databasebackup gemaakt
- [ ] Webapp bijgewerkt met `LEVELS_AI_ENABLED = false`
- [ ] Menu *Puntenschema's* zichtbaar met "Standaard (3/4/5)"
- [ ] `open_student_answers` geeft `"grading_scale": "points"`; een `levels`-antwoord staat er (nog) niet in
- [ ] Workers bijgewerkt en herstart; mocktests geslaagd; `bin/config.py` ongewijzigd
- [ ] `LEVELS_AI_ENABLED = true` gezet
- [ ] Proef-toets met niveaus krijgt AI-niveaus (`Niveau: …`)
- [ ] Een bestaande `points`-toets ziet er hetzelfde uit als voor de uitrol
