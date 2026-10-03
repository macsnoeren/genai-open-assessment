# Externe koppeling in gebruik nemen

Deze handleiding beschrijft hoe je de externe koppeling (branch `dev-external-integration`) uitrolt. Daarmee laat een andere website (een leeromgeving of cursusplatform) haar deelnemers een toets uit deze applicatie maken. De technische beschrijving voor die website staat in [integration-api.md](integration-api.md).

## Wat er verandert

De wijziging is **volledig additief** en raakt **alleen de webapp**. De workers op de Windows-machine blijven ongewijzigd.

| Onderdeel | Wijziging | Gevolg voor bestaande onderdelen |
|---|---|---|
| Database | Kolom `api_keys.scope` (default `worker`) en vier nieuwe tabellen: `integrations`, `integration_exams`, `integration_attempts`, `integration_events` | Wordt automatisch aangemaakt door `Database::migrate()` bij de eerste request. **Bestaande keys krijgen scope `worker`** en blijven werken |
| API | Vijf nieuwe endpoints (`integration_*`, alleen met een key van een koppeling). De zes worker-endpoints eisen nu een key met scope `worker` | Het contract met de workers is ongewijzigd. Webhooks gaan aan het eind van `open_student_answers` en `open_assessment_jobs`, **na** het antwoord aan de worker |
| Webapp | Admin-menu **Koppelingen**, kolom *Type* bij API Keys, startpagina voor deelnemers, badge bij pogingen via een koppeling | Gewone gastpogingen en ingelogde studenten werken als voorheen |
| Workers | Geen | `process_ai_feedback.py`, `process_design_jobs.py` en `process_assessment_jobs.py` en hun `config.py` blijven zoals ze zijn |

Er is **geen overgangsvlag** nodig en de volgorde maakt niet uit: de workers merken niets van de nieuwe code.

## Eisen aan de webserver

1. **`php-curl`**: de webapp verstuurt de webhooks met curl. Controleer met `php -m | grep curl` (PHP-FPM en CLI kunnen verschillen; kijk bij twijfel in `phpinfo()` van de webserver). Installeer anders bijvoorbeeld `php8.2-curl` en herstart PHP-FPM.
2. **Uitgaand HTTPS** van de webserver naar de webhookhosts van de externe websites (poort 443). Pas zo nodig de firewall aan. Zonder uitgaand verkeer werkt alles behalve de webhooks; die worden na 8 pogingen opgegeven en zijn te zien op de detailpagina van de koppeling.
3. **Zet `INTEGRATION_ALLOW_HTTP` niet** in productie. Die omgevingsvariabele is alleen voor de Docker-dev (http naar localhost). In productie zijn terugkeer- en webhook-URL's alleen `https`.
4. Met PHP-FPM stuurt `fastcgi_finish_request()` het antwoord aan de worker af voordat de webhooks gaan. Onder Apache met mod_php gebeurt dat met `Content-Length` en `Connection: close`; ook daar wacht de worker niet op trage ontvangers.

## Voordat je begint

1. **Maak een backup van de database** (het SQLite-bestand in `database/`).
2. Controleer dat de workers nu goed draaien (geen `401` in de uitvoer).

## Stap 1 – Webapp bijwerken

Haal de code binnen op de live server, bijvoorbeeld met `git pull`, en herstart PHP-FPM als er een opcache actief is. Open daarna een pagina waarvoor je ingelogd moet zijn: die request voert de migratie uit. Controleren:

```bash
sqlite3 database/database.sqlite "SELECT id, name, scope FROM api_keys;"
sqlite3 database/database.sqlite ".tables" | tr -s ' ' '\n' | grep integration
```

Verwacht: alle bestaande keys met scope `worker`, en de vier `integration*`-tabellen.

## Stap 2 – Controleren

1. **De workers werken nog:** in de uitvoer van de drie workers verschijnt geen `401` of `403`, en nieuwe antwoorden worden nagekeken.
2. **Scope:** een workerkey op een integratie-endpoint geeft `403`:

   ```bash
   curl -s -o /dev/null -w "%{http_code}\n" -H "Authorization: Bearer <WORKERKEY>" \
     "https://test.jmnl.nl/api/index.php?action=integration_exams"
   ```

3. **Beheer:** log in als admin. Onder **Koppelingen** kun je een koppeling aanmaken; de API-key en het webhookgeheim worden één keer getoond. Bij **API Keys** staat de kolom *Type*.
4. **Een proefkoppeling** (optioneel, met een eigen https-ontvanger, bijvoorbeeld de demo-site achter https): start een poging met `integration_attempt_start`, doorloop de toets, en controleer dat de webhook `attempt.submitted` aankomt zodra een worker pollt. Op de detailpagina van de koppeling staat het event dan als afgeleverd.

## Terugdraaien

- **Alleen de functie uitzetten:** schakel de koppelingen uit (**Koppelingen** → *Uitschakelen*). De externe websites krijgen dan `401`, startlinks werken niet meer en er gaan geen webhooks. De rest van de applicatie merkt er niets van. Lopende pogingen blijven als gastpogingen zichtbaar voor de docent.
- **De code terugzetten:** dat kan zonder databaseherstel. De kolom `scope` en de tabellen mogen blijven staan; de oude code negeert ze. Let op: de oude code kent geen scope, dus een key van een koppeling werkt dan weer op de worker-endpoints (alle studentantwoorden). **Verwijder of schakel daarom eerst alle koppelingen uit** voordat je de code terugzet.

## Checklist

- [ ] Databasebackup gemaakt
- [ ] `php-curl` actief in de webserver, uitgaand HTTPS naar de webhookhosts toegestaan
- [ ] `INTEGRATION_ALLOW_HTTP` niet gezet
- [ ] Webapp bijgewerkt, bestaande keys hebben scope `worker`, de vier tabellen bestaan
- [ ] De drie workers draaien zonder `401`/`403`, antwoorden worden nagekeken
- [ ] Workerkey op `integration_exams` geeft `403`
- [ ] Menu **Koppelingen** zichtbaar voor de admin
