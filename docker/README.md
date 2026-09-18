# Docker-omgeving

Deze map bevat alles om de webapplicatie (map `htdocs/` in de repo-root) lokaal te **testen** met Apache + PHP, zonder dat je zelf PHP of een webserver hoeft te installeren.

## Inhoud
- **Dockerfile** — `php:8.2-apache` image met de `pdo_sqlite`-extensie en document root ingesteld op `htdocs/`.
- **docker-compose.yml** — bouwt de image en start de container, met poort `8080` op de host en een named volume voor de database.
- **entrypoint.sh** — draait bij het opstarten van de container; initialiseert de SQLite-database via `setup/init_db.php` als die nog niet bestaat, en zet de juiste eigenaar (`www-data`) op de database-map.
- **start.sh** — wrapper rondom `docker compose up --build` die altijd vanuit deze map draait, zodat de build-context (de repo-root, nodig omdat de Dockerfile ook `app/` en `config/` moet kunnen kopiëren) klopt, ongeacht vanaf welke directory je het script aanroept.

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

## Beperkingen
- Deze opzet is bedoeld om de webapplicatie te **testen**, niet als productie-deployment.
- De achtergrondservice die AI-beoordelingen verwerkt (`bin/process_ai_feedback.py`, zie [../bin/README.md](../bin/README.md)) draait **niet** in deze container en moet apart (op je eigen machine, met een draaiende Ollama-instantie) worden gestart als je AI-feedback wilt testen.
