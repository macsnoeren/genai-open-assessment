# Docker-omgeving

Deze map bevat alles om de webapplicatie (map `htdocs/` in de repo-root) lokaal te **testen** met Apache + PHP, zonder dat je zelf PHP of een webserver hoeft te installeren.

## Inhoud
- **Dockerfile** — `php:8.2-apache` image met de `pdo_sqlite`-extensie en document root ingesteld op `htdocs/`.
- **docker-compose.yml** — bouwt de image en start de container, met poort `8080` op de host en een named volume voor de database.
- **entrypoint.sh** — draait bij het opstarten van de container; initialiseert de SQLite-database via `setup/init_db.php` als die nog niet bestaat, en zet de juiste eigenaar (`www-data`) op de database-map.
- **start.sh** — wrapper rondom `docker compose up --build` die altijd vanuit deze map draait, zodat de build-context (de repo-root, nodig omdat de Dockerfile ook `app/` en `config/` moet kunnen kopiëren) klopt, ongeacht vanaf welke directory je het script aanroept.
- **Dockerfile.worker** — `python:3.12-slim` image met de drie AI-workers uit `bin/` (zie [AI-workers](#ai-workers) hieronder).
- **worker-entrypoint.sh** — start `process_ai_feedback.py`, `process_design_jobs.py` en `process_assessment_jobs.py` naast elkaar en stopt de container zodra één ervan stopt.

De build-context is bewust de **repo-root** (`context: ..` in `docker-compose.yml`), omdat de Dockerfile de hele applicatie kopieert (`app/`, `config/`, `htdocs/`), niet alleen deze map.

## Starten
```bash
./docker/start.sh
```
Dit script bouwt altijd zonder cache (`docker compose build --no-cache`) voordat het de container start, zodat een aanpassing in de Dockerfile nooit per ongeluk wordt overgeslagen door een oude, mislukte build-laag uit de cache.

Wil je liever handmatig werken (met caching, dus sneller bij herhaalde builds):
```bash
cd docker
docker compose up --build
```

De applicatie is daarna bereikbaar op [http://localhost:8080](http://localhost:8080).

Bij de eerste start wordt de SQLite-database automatisch aangemaakt (in een Docker volume, zodat je data bewaard blijft tussen herstarts) met een standaard admin-account:
- **E-mail:** `admin@school.nl`
- **Wachtwoord:** `admin123`

> Wijzig dit wachtwoord na het inloggen, of gebruik dit account uitsluitend voor lokaal testen.

## Stoppen
```bash
cd docker
docker compose down
```
Voeg `-v` toe (`docker compose down -v`) als je ook de database-volume wilt verwijderen en met een schone database opnieuw wilt beginnen.

## Zonder Docker Compose
```bash
docker build -t genai-open-assessment -f docker/Dockerfile .
docker run -p 8080:80 -v genai_db:/var/www/html/database genai-open-assessment
```
Let op: de build hier moet vanuit de repo-root draaien (`.` als context), niet vanuit `docker/`.

## AI-workers
De drie workers uit `bin/` (AI-feedback, vraagontwerper en agentic beoordelen, zie [../bin/README.md](../bin/README.md)) draaien samen in één aparte container, de service `worker`. Die staat achter het profiel `worker`, zodat `./docker/start.sh` zonder `config.py` gewoon alleen de webapp start.

```bash
cd docker
docker compose --profile worker up --build        # webapp + workers
docker compose --profile worker up --build worker # alleen de workers
WORKER_CONFIG=/pad/naar/config.py docker compose --profile worker up --build worker
```

- **Configuratie:** de worker leest dezelfde `config.py` als zonder Docker (zie `bin/config.py.sample`). Standaard wordt `bin/config.py` gemount, met `WORKER_CONFIG` kies je een ander bestand. Het bestand zit niet in de image (de API-key staat erin); de entrypoint kopieert het bij elke start, dus na een wijziging volstaat een herstart (`docker compose restart worker`), geen nieuwe build. Bestaat het bestand niet, dan weigert compose de container te starten.
- **Webapp in dezelfde compose:** zet `BASE_URL = "http://web/api/index.php"` en `ALLOW_INSECURE_BASE_URL = True` (de worker eist anders https buiten localhost). Gebruik een API-key van het type *Worker* uit het beheerpaneel van deze lokale webapp.
- **Ollama op de host:** `localhost` is in de container de container zelf. Zet `OLLAMA_URL = "http://host.docker.internal:11434/api/chat"`. Ollama luistert standaard alleen op `127.0.0.1`; start het met `OLLAMA_HOST=0.0.0.0` (bij de systemd-service via `systemctl edit ollama`) en zorg dat poort 11434 niet vanaf het netwerk bereikbaar is. Cloud-modellen (`-cloud`) vereisen een `ollama signin` op de host.
- **Logs:** `docker compose logs -f worker`. Elke regel begint met de naam van de worker, bijvoorbeeld `[process_design_jobs]`.
- **Een worker stopt:** dan stopt de entrypoint de andere twee ook en eindigt de container met een foutcode; `restart: unless-stopped` start ze samen opnieuw. Een job die halverwege werd afgebroken, blijft in de wachtrij van de webapp staan en wordt opnieuw opgehaald.
- **Mocktests in de container:** `docker compose --profile worker run --rm --entrypoint sh worker -c 'cp /config/config.py . && python -m unittest test_design_agents test_rubric_grading test_assessment_agents'`.
- De workers draaien als gebruiker `worker` (uid 10001), niet als root.

Zonder compose (vanuit de repo-root):
```bash
docker build -t genai-open-assessment-worker -f docker/Dockerfile.worker .
docker run -d --restart unless-stopped -v "$PWD/bin/config.py:/config/config.py:ro" genai-open-assessment-worker
```

## Beperkingen
- Deze opzet is bedoeld om de webapplicatie te **testen**, niet als productie-deployment. De worker-image is wel geschikt om de workers op een server te draaien, mits die een bereikbare Ollama heeft.
