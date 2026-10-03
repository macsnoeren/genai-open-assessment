# AI-vraagontwerper in gebruik nemen

Deze handleiding beschrijft hoe je de AI-vraagontwerper (branch
`dev-agentic-exam-ai`) uitrolt: eerst de webapplicatie, daarna de
ontwerp-worker op de Windows-machine waar ook de AI-feedbackservice draait.

## Wat er verandert

De wijziging is **volledig additief**. Er breekt geen bestaand contract:

| Onderdeel | Wijziging | Gevolg voor bestaande onderdelen |
|---|---|---|
| Database | Nieuwe tabel `question_designs` | Wordt automatisch aangemaakt door `Database::migrate()` bij de eerste request |
| Webapp | Nieuwe pagina's `question_design_*`, knop "Vraag ontwerpen met AI" op de vragenpagina | Bestaande pagina's werken ongewijzigd |
| API | Nieuwe endpoints `open_design_jobs` en `submit_design_result` | `open_student_answers` en `submit_ai_feedback` ongewijzigd |
| Worker | Nieuwe bestanden `bin/process_design_jobs.py` en `bin/design_agents.py`; `call_ollama()` kreeg een optionele parameter `num_ctx` | `process_ai_feedback.py` gedraagt zich hetzelfde |

Er is **geen overgangsvlag** nodig. De volgorde is wel van belang: rol eerst
de webapp uit. Start je de ontwerp-worker tegen een server met de oude code,
dan meldt hij "Server kent open_design_jobs nog niet; rol eerst de nieuwe
webapp uit." en doet hij verder niets.

## Voordat je begint

1. **Maak een backup van de database** (het SQLite-bestand in `database/`).
2. Controleer dat de AI-feedbackservice nu goed draait (geen `401` in de
   uitvoer). De ontwerp-worker gebruikt dezelfde `config.py` en API-key.

## Stap 1 – Webapp bijwerken

Haal de code binnen op de live server, bijvoorbeeld met `git pull`, en
herstart PHP-FPM als er een opcache actief is.

Open daarna een willekeurige pagina: de eerste request maakt de tabel
`question_designs` aan. Controleren kan met:

```bash
sqlite3 database/database.sqlite "PRAGMA table_info(question_designs);"
```

## Stap 2 – Webapp controleren

```bash
curl -s -H "Authorization: Bearer <JOUW_KEY>" "https://test.jmnl.nl/api/index.php?action=open_design_jobs"
```

Verwacht: HTTP 200 met `{"jobs":[]}`. Een `404` met `Unknown endpoint`
betekent dat de nieuwe code nog niet actief is.

Log in als docent, open de vragen van een eigen toets en controleer dat de
knop **"Vraag ontwerpen met AI"** zichtbaar is.

## Stap 3 – Ontwerp-worker op de Windows-machine

1. Zet de nieuwe en gewijzigde bestanden in `bin\` op de workermachine:
   - `process_design_jobs.py` (nieuw)
   - `design_agents.py` (nieuw)
   - `process_ai_feedback.py` (gewijzigd: optionele `num_ctx`)
   - optioneel `test_design_agents.py` en de map `fixtures\` (alleen voor tests)

   Herstart daarna ook de AI-feedbackservice, zodat die de bijgewerkte
   `process_ai_feedback.py` gebruikt.

2. **Optioneel:** zet de `DESIGN_*`-instellingen in de **eigen** `config.py`
   op die machine. `config.py` staat niet in git; een wijziging elders komt
   daar niet aan. Zonder deze regels gelden de defaults hieronder, die met de
   huidige cloud-configuratie goed werken.

   ```python
   DESIGN_MODEL = "gpt-oss:120b-cloud"   # default: laatste model uit LLM_MODELS
   # DESIGN_VALIDATION_MODEL = "..."     # default: DESIGN_MODEL
   # DESIGN_POLL_INTERVAL = 10           # seconden
   # DESIGN_MAX_ATTEMPTS = 3
   # NUM_PREDICT_DESIGN = 6000           # wordt begrensd op NUM_PREDICT_MAX
   # DESIGN_NUM_CTX = 16384
   ```

   Gebruik geen klein lokaal model (zoals `qwen3:4b`): de agents krijgen
   lange invoer en de machine loopt dan vast.

3. Start de ontwerp-worker als **tweede proces**, naast de
   AI-feedbackservice:

   ```bat
   cd C:\Data\projects\genai-open-assessment\bin
   python process_design_jobs.py
   ```

## Stap 4 – Controleren

In de uitvoer van de ontwerp-worker:

- `AI-ontwerpassistent gestart (model …, validatie …)...` met de verwachte modellen.
- **Geen** `API-key geweigerd (401)` en geen melding dat de server
  `open_design_jobs` niet kent.

Start daarna als docent een ontwerp met een korte vraag en een gewenst
antwoord. Binnen ongeveer een halve minuut verschijnt de analyse (en
eventueel verduidelijkende vragen) op de ontwerppagina. In de worker-uitvoer
zie je `[Analysis/…] Klaar in …s.`

De ontwerppagina meldt "De AI-ontwerpassistent is op dit moment niet
actief" als de worker langer dan 120 seconden niet heeft gepold. Die
melding moet verdwijnen zodra de worker draait.

## Terugdraaien

- **Alleen de functie uitzetten:** stop het proces `process_design_jobs.py`.
  Dat is voldoende: ontwerpen blijven dan in de wachtrij staan en de
  docent ziet de melding dat de assistent niet actief is. De rest van de
  applicatie merkt er niets van.
- **De code terugzetten:** dat kan zonder databaseherstel. De tabel
  `question_designs` blijft dan ongebruikt staan; de oude code negeert hem.
  Vragen die al via de ontwerper zijn goedgekeurd, zijn gewone vragen en
  blijven werken. De oude `process_ai_feedback.py` werkt ook met de nieuwe
  webapp.

## Checklist

- [ ] Databasebackup gemaakt
- [ ] Webapp bijgewerkt, tabel `question_designs` bestaat
- [ ] `open_design_jobs` met key geeft `200 {"jobs":[]}`
- [ ] Knop "Vraag ontwerpen met AI" zichtbaar voor de eigenaar van een toets
- [ ] Nieuwe bestanden in `bin\` op de workermachine, AI-feedbackservice herstart
- [ ] (Optioneel) `DESIGN_*`-instellingen in de eigen `config.py`, geen lokaal model
- [ ] `process_design_jobs.py` draait als tweede proces, zonder `401`
- [ ] Testontwerp loopt door tot "Klaar voor beoordeling"
