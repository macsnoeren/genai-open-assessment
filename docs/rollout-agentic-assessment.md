# Agentic beoordelen in gebruik nemen

Deze handleiding beschrijft hoe je agentic beoordelen (branch
`dev-agentic-assessment`) uitrolt: eerst de webapplicatie, daarna de
assessment-worker op de Windows-machine waar ook de AI-feedbackservice en de
ontwerp-worker draaien.

## Wat er verandert

De wijziging is **volledig additief**. Er breekt geen bestaand contract:

| Onderdeel | Wijziging | Gevolg voor bestaande onderdelen |
|---|---|---|
| Database | Nieuwe tabel `answer_assessments` (+ index) | Wordt automatisch aangemaakt door `Database::migrate()` bij de eerste request |
| Webapp | Nieuwe pagina's `answer_assessment_*`, knoppen "Agentic beoordelen" op de antwoordenpagina van een poging, **automatisch agentic beoordelen** van antwoorden op rubric-vragen (`AGENTIC_AUTO_ASSESSMENT`) | Antwoorden op rubric-vragen (bij toetsen met AI-beoordeling aan) gaan niet meer naar de AI-feedbackservice, behalve als de agentic run mislukt. Het formaat van `ai_feedback` verandert niet |
| API | Nieuwe endpoints `open_assessment_jobs` en `submit_assessment_result` | Bestaande endpoints ongewijzigd |
| Worker | Nieuwe bestanden `bin/process_assessment_jobs.py` en `bin/assessment_agents.py`; `bin/design_agents.py` gewijzigd (`Agent` herbruikbaar gemaakt, gedrag gelijk) | `process_ai_feedback.py` ongewijzigd; de ontwerp-worker gedraagt zich hetzelfde maar moet wel herstart worden |

Er is **geen overgangsvlag** nodig. De volgorde is wel van belang: rol eerst
de webapp uit, en **start de assessment-worker direct daarna**. Vanaf het moment
dat de nieuwe webapp draait, krijgt de AI-feedbackservice geen antwoorden op
rubric-vragen meer; die wachten op de assessment-worker. Wil je dat (tijdelijk)
niet, zet dan `AGENTIC_AUTO_ASSESSMENT = false` in `config/app.php`: dan werkt
agentic beoordelen alleen handmatig en krijgt de AI-feedbackservice alle
antwoorden weer, behalve die met een handmatig gestarte agentic run.

Bestaande antwoorden die al AI-feedback hebben, worden niet automatisch agentic
beoordeeld. Wel antwoorden op rubric-vragen die nog op AI-feedback wachten. Start je de assessment-worker tegen een server met de oude code,
dan meldt hij "Server kent open_assessment_jobs nog niet; rol eerst de nieuwe
webapp uit." en doet hij verder niets.

## Voordat je begint

1. **Maak een backup van de database** (het SQLite-bestand in `database/`).
2. Controleer dat de AI-feedbackservice nu goed draait (geen `401` in de
   uitvoer). De assessment-worker gebruikt dezelfde `config.py` en API-key.

## Stap 1 – Webapp bijwerken

Haal de code binnen op de live server, bijvoorbeeld met `git pull`, en
herstart PHP-FPM als er een opcache actief is.

Open daarna een pagina waarvoor je ingelogd moet zijn: die request maakt de
tabel `answer_assessments` aan. Controleren kan met:

```bash
sqlite3 database/database.sqlite "PRAGMA table_info(answer_assessments);"
```

## Stap 2 – Webapp controleren

```bash
curl -s -H "Authorization: Bearer <JOUW_KEY>" "https://test.jmnl.nl/api/index.php?action=open_assessment_jobs"
```

Verwacht: HTTP 200 met `{"jobs":[]}`. Een `404` met `Unknown endpoint`
betekent dat de nieuwe code nog niet actief is.

Log in als docent, open bij "Resultaten" een ingeleverde poging van een toets
met AI-beoordeling aan, en controleer dat de knoppen **"Agentic beoordelen"**
en **"Alle antwoorden agentic beoordelen"** zichtbaar zijn.

## Stap 3 – Assessment-worker op de Windows-machine

1. Zet de nieuwe en gewijzigde bestanden in `bin\` op de workermachine:
   - `process_assessment_jobs.py` (nieuw)
   - `assessment_agents.py` (nieuw)
   - `design_agents.py` (gewijzigd: `Agent` herbruikbaar gemaakt)
   - optioneel `test_assessment_agents.py` en de map `fixtures\assessment\`
     (alleen voor tests)

   **Herstart daarna ook de ontwerp-worker** (`process_design_jobs.py`), zodat
   die de bijgewerkte `design_agents.py` gebruikt. De AI-feedbackservice hoeft
   niet herstart te worden.

2. **Optioneel:** zet de `ASSESSMENT_*`-instellingen in de **eigen** `config.py`
   op die machine. `config.py` staat niet in git; een wijziging elders komt
   daar niet aan. Zonder deze regels gelden de defaults hieronder; het model is
   dan `DESIGN_MODEL` (en anders het laatste model uit `LLM_MODELS`).

   ```python
   ASSESSMENT_MODEL = "gpt-oss:120b-cloud"   # default: DESIGN_MODEL
   # ASSESSMENT_VALIDATION_MODEL = "..."     # default: ASSESSMENT_MODEL
   # NUM_PREDICT_ASSESSMENT = 6000           # wordt begrensd op NUM_PREDICT_MAX
   # ASSESSMENT_NUM_CTX = 16384              # default: max(RUBRIC_NUM_CTX, 16384)
   # ASSESSMENT_MAX_EXTRA_ROUNDS = 1         # 0 tot 2
   # ASSESSMENT_POLL_INTERVAL = 15           # seconden
   # ASSESSMENT_MAX_ATTEMPTS = 3
   ```

   De worker gebruikt ook de bestaande `INJECTION_CHECK_MODEL` voor de
   voorcontrole op prompt injection. Gebruik geen klein lokaal model (zoals
   `qwen3:4b`): de agents krijgen lange invoer en de machine loopt dan vast.

3. Start de assessment-worker als **derde proces**, naast de
   AI-feedbackservice en de ontwerp-worker:

   ```bat
   cd C:\Data\projects\genai-open-assessment\bin
   python process_assessment_jobs.py
   ```

## Stap 4 – Controleren

In de uitvoer van de assessment-worker:

- `AI-beoordelingsagents gestart (model …, validatie …, injection-controle …, extra rondes 1)...`
  met de verwachte modellen.
- **Geen** `API-key geweigerd (401)` en geen melding dat de server
  `open_assessment_jobs` niet kent.

Klik daarna als docent bij een antwoord op een vraag met rubric-criteria op
**"Agentic beoordelen"**. Binnen ongeveer een halve minuut staat de run op
"Klaar voor controle". In de worker-uitvoer zie je
`[Evidence/…] Klaar in …s.`, `[Assessment/…]`, `[Validation/…]` en
`Beoordeling N: score …, confidence …, menselijke beoordeling nodig: …`.

De pagina van de run meldt "De AI-beoordelingsagents zijn op dit moment niet
actief" als de worker langer dan 120 seconden niet heeft gepold. Die melding
moet verdwijnen zodra de worker draait.

## Terugdraaien

- **Alleen de functie uitzetten:** zet `AGENTIC_AUTO_ASSESSMENT = false` in
  `config/app.php` (dan gaan rubric-antwoorden weer naar de AI-feedbackservice)
  en stop het proces `process_assessment_jobs.py`. Alleen het proces stoppen is
  niet genoeg: rubric-antwoorden blijven dan op de agents wachten en de docent
  ziet de melding dat de agents niet actief zijn. De rest van de applicatie
  merkt er niets van, en docentscores die al via een goedgekeurde agentic
  beoordeling zijn gezet, blijven gewone docentscores.
- **De code terugzetten:** dat kan zonder databaseherstel. De tabel
  `answer_assessments` blijft dan ongebruikt staan; de oude code negeert hem.
  De oude `design_agents.py` werkt ook met de nieuwe webapp.

## Checklist

- [ ] Databasebackup gemaakt
- [ ] Webapp bijgewerkt, tabel `answer_assessments` bestaat
- [ ] `open_assessment_jobs` met key geeft `200 {"jobs":[]}`
- [ ] Knoppen "Agentic beoordelen" zichtbaar bij een toets met AI-beoordeling aan
- [ ] Nieuwe en gewijzigde bestanden in `bin\` op de workermachine, ontwerp-worker herstart
- [ ] (Optioneel) `ASSESSMENT_*`-instellingen in de eigen `config.py`, geen lokaal model
- [ ] `process_assessment_jobs.py` draait als derde proces, zonder `401`
- [ ] Testbeoordeling loopt door tot "Klaar voor controle"
