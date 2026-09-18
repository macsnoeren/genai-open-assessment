# Nieuwe versie activeren op de live server

Deze handleiding beschrijft hoe je de versie vanaf commit `5565d2a`
("Implementing security measures and improved prompting") in gebruik neemt
op de live server, en wat je daarna op de machine met de AI-feedbackservice
(`bin/process_ai_feedback.py`) moet aanpassen.

## Wat er verandert en waarom dit niet zomaar kan

De webapplicatie en de AI-feedbackservice zijn **twee kanten van dezelfde
wijziging**. Ze moeten in dezelfde versie draaien, anders wijst de server
elke API-aanroep af met `401 Unauthorized: Invalid or missing API Key` –
ook al is de key correct.

| | Oude code | Nieuwe code |
|---|---|---|
| Waar leest de server de API-key? | alleen uit de URL: `?api_key=…` | alleen uit de header: `Authorization: Bearer …` |
| Waar stuurt de service de key heen? | in de URL | in de header |
| Opslag van de key in de database | platte tekst | SHA-256-hash (omzetting gebeurt vanzelf bij eerste gebruik) |
| `BASE_URL` in `bin/config.py` | http of https | buiten localhost **verplicht https** |

Reden voor de wijziging: een key in de URL belandt in de access log van de
webserver (bij nginx: `access.log`). Met de header gebeurt dat niet.

Zolang de server nog de oude code draait, kun je de service laten werken
met `LEGACY_API_KEY_IN_QUERY = True` in `bin/config.py`. Dat is een
overbrugging, geen oplossing: de key staat dan weer in de access log. Zet
hem uit zodra stap 4 hieronder is afgerond.

## Voordat je begint

1. **Zorg dat de ruwe API-key in `bin/config.py` staat.** Na de eerste
   geslaagde aanroep met de nieuwe code bewaart de database alleen nog de
   hash. De ruwe key is dan **niet meer uit de database te halen**. Is hij
   kwijt, dan moet je in de beheeromgeving een nieuwe key aanmaken en die in
   `bin/config.py` zetten.
2. **Maak een backup van de database** (het SQLite-bestand in `database/`).
   Dit is je enige weg terug als je toch naar de oude code wilt – zie
   "Terugdraaien" onderaan.
3. Controleer welke machine de service draait en hoe die wordt gestart
   (handmatig, `systemd`, `screen`, …), zodat je hem in stap 4 kunt
   herstarten.

## Stap 1 – Code op de live server bijwerken

Haal de code binnen op de manier die je gewend bent, bijvoorbeeld:

```bash
git pull
```

Controleer dat je minimaal op commit `5565d2a` zit:

```bash
git log --oneline -1
```

De database hoeft niet gemigreerd te worden. Bestaande keys in platte tekst
worden bij het eerste gebruik automatisch omgezet naar een hash
(`ApiKey::findActiveByKey()` in `app/models/ApiKey.php`).

## Stap 2 – Controleren dat de nieuwe code actief is

Doe een aanroep **zonder** key. De nieuwe code stuurt bij een 401 altijd een
`WWW-Authenticate: Bearer`-header mee; de oude code niet.

```bash
curl -sI "https://test.jmnl.nl/api/index.php?action=open_student_answers" | grep -i "HTTP\|WWW-Authenticate"
```

Verwacht:

```
HTTP/2 401
www-authenticate: Bearer
```

Ontbreekt de `www-authenticate`-regel, dan draait de server nog de oude
code (bijvoorbeeld door een opcache die nog niet is geleegd – herstart dan
PHP-FPM).

Test daarna mét key, via de header:

```bash
curl -s -H "Authorization: Bearer <JOUW_KEY>" "https://test.jmnl.nl/api/index.php?action=open_student_answers&limit=1"
```

Verwacht: HTTP 200 met JSON (`{"answers": [...]}`). Hiermee is de key in de
database ook meteen omgezet naar een hash.

## Stap 3 – `bin/config.py` bijwerken op de machine met de service

Controleer in `bin/config.py`:

```python
BASE_URL = "https://test.jmnl.nl/api/index.php"   # https is verplicht
LEGACY_API_KEY_IN_QUERY = False                   # brug weer uit
```

Ontbreekt `LEGACY_API_KEY_IN_QUERY`, dan staat hij standaard uit; dat is
goed. Vergelijk voor de overige instellingen met `bin/config.py.sample`
(modellen, `THINK_LEVELS`, tokenbudgetten, feedbacklengte).

Voor de cloud-modellen (`gpt-oss:*-cloud`) moet op deze machine eenmalig
zijn ingelogd bij Ollama:

```bash
ollama signin
```

## Stap 4 – Service herstarten en controleren

Herstart `bin/process_ai_feedback.py` op de manier waarop hij normaal
draait. Controleer in de uitvoer:

- Er verschijnt **geen** regel `API-key geweigerd (401)`.
- Er verschijnt **geen** regel `LET OP: LEGACY_API_KEY_IN_QUERY staat aan`.
  Zie je die wel, dan staat de brug nog aan – zet hem uit in `config.py`.
- Je ziet `Geen nieuwe studentantwoorden.` of `N nieuwe antwoorden gevonden.`
  Beide betekenen dat de API-aanroep is geslaagd.

Op de server schrijft elke geslaagde `open_student_answers`-aanroep een
tijdstempel naar `database/last_api_ping.txt`; het dashboard van de
beheeromgeving gebruikt dat om te tonen of de service actief is.

## Als het misgaat

**401 blijft, ook met de nieuwe code en een correcte key.** De
`Authorization`-header bereikt PHP dan niet. nginx geeft die standaard door
aan PHP-FPM; controleer of er geen tussenliggende proxy is die hem
verwijdert. Als de header wél bij nginx aankomt maar niet bij PHP, voeg dan
toe aan de `location`-blok voor PHP:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

en herlaad nginx.

**De service stopt direct met een melding over HTTPS.** `BASE_URL` gebruikt
`http://` buiten localhost. Gebruik `https://`. Alleen voor een lokale
testomgeving zonder TLS mag `ALLOW_INSECURE_BASE_URL = True`.

**Key kwijt.** Maak in de beheeromgeving een nieuwe API-key aan, zet die in
`bin/config.py` en herstart de service. De oude key kun je daar
deactiveren of verwijderen.

## Terugdraaien naar de oude code

Let op de valkuil: zodra een key met de nieuwe code is gebruikt, staat hij
gehasht in de database. **De oude code vergelijkt de ruwe key met die
hash en keurt hem af.** Terugdraaien van alleen de code is dus niet genoeg.

Twee opties:

1. Zet de databasebackup van vóór de uitrol terug (keys weer in platte
   tekst), en daarna de oude code.
2. Of: zet de oude code terug en maak in de (oude) beheeromgeving een
   nieuwe key aan; zet die in `bin/config.py` met
   `LEGACY_API_KEY_IN_QUERY = True`.

## Lokale Docker-testomgeving

De testcontainer (`docker/`) kopieert de code tijdens het bouwen; er is geen
koppeling met je werkmap. Na een codewijziging moet het image dus opnieuw
gebouwd worden:

```bash
./docker/start.sh
```

De database staat in een Docker-volume en blijft daarbij behouden. Omdat
deze omgeving op `localhost` draait, mag `BASE_URL` hier `http://` zijn.

## Checklist

- [ ] Ruwe API-key staat in `bin/config.py`
- [ ] Databasebackup gemaakt
- [ ] Code op de live server op `5565d2a` of nieuwer
- [ ] Keyloze aanroep geeft `401` **met** `www-authenticate: Bearer`
- [ ] Aanroep met `Authorization: Bearer` geeft `200`
- [ ] `BASE_URL` is `https://`
- [ ] `LEGACY_API_KEY_IN_QUERY = False` (of weggelaten)
- [ ] `ollama signin` gedaan op de machine met de service
- [ ] Service herstart, geen `401` en geen `LET OP`-regel in de uitvoer
