# Demo: een externe website koppelen

`demo_site.py` is een minimale "externe website" (leeromgeving) die de hele flow van de externe koppeling laat zien: een poging starten aan de serverkant, de deelnemer doorsturen, terugkomen na het inleveren, webhooks ontvangen en controleren, de open pogingen bekijken en een menselijke beoordeling terugmelden.

Het is een **voorbeeld, niet voor productie** (geen login, geen CSRF-bescherming, ontdubbelen alleen in het geheugen). De technische beschrijving van de API staat in [../integration-api.md](../integration-api.md).

Alleen Python 3 met de standaardbibliotheek is nodig.

## Stappen

1. **Start de applicatie in Docker** (vanuit de repo-root):

   ```bash
   ./docker/start.sh
   ```

   De Docker-omgeving zet `INTEGRATION_ALLOW_HTTP=1` en `host.docker.internal`, zodat de terugkeer-URL en de webhooks over `http` naar je eigen machine mogen. In productie is alleen `https` toegestaan.

2. **Maak een toets met AI-beoordeling aan.** Log in als admin (`admin@school.nl` / `admin123`, daarna wachtwoord wijzigen), maak een toets aan met *AI Beoordeling inschakelen* en voeg minstens één vraag toe.

3. **Maak een koppeling aan** via *Koppelingen* → *Nieuwe koppeling*:

   | Veld | Waarde |
   |---|---|
   | Naam | `Demo LMS` |
   | Origin van de terugkeer-URL | `http://localhost:9000` |
   | Webhook-URL | `http://host.docker.internal:9000/webhook` |
   | Drempel | `hoog` (standaard) |
   | Toetsen | de toets uit stap 2 |

   Kopieer de **API-key** en het **webhookgeheim**: ze worden maar één keer getoond.

4. **Start de demo:**

   ```bash
   INTEGRATION_KEY=<api-key> WEBHOOK_SECRET=<geheim> python3 docs/integration-demo/demo_site.py
   ```

   Optioneel: `APP_URL` (standaard `http://localhost:8080`), `PORT` (standaard `9000`) en `WEBHOOK_FAIL=1` (de webhook antwoordt dan 500, om de herhaalpogingen te zien op de detailpagina van de koppeling).

5. **Start de workers** (in `bin/`, met een `config.py` met een workerkey en een cloud-model), zodat de toets wordt nagekeken en de webhooks worden verstuurd. Webhooks gaan alleen tijdens de polls van de workers.

   ```bash
   cd bin && python process_ai_feedback.py        # vrije criteria
   cd bin && python process_assessment_jobs.py    # rubric-vragen (agentic)
   ```

6. **Doorloop de flow:** open <http://localhost:9000>, kies de toets en klik op *Start de toets*. Je komt op de landingspagina van de toetsapplicatie, maakt de toets en levert in. Daarna ben je terug op `http://localhost:9000/return?...&status=submitted`.

   In de uitvoer van de demo zie je de webhooks `attempt.submitted` en (na het nakijken) `attempt.graded`. Ververs `/return?attempt_id=N` om het resultaat te zien. Bij `graded` en `reviewed` staat daar een formulier om scores terug te melden. Daarna volgt `attempt.reviewed`. `/open` toont de pogingen die nog aandacht nodig hebben.

## Terugmelden met curl

```bash
curl -s -X POST -H "Authorization: Bearer $INTEGRATION_KEY" -H "Content-Type: application/json" \
  --data '{"attempt_id": 12, "reviewer": "Demo-docent", "grades": [{"question_id": 3, "score": 7, "feedback": "Goed"}]}' \
  "http://localhost:8080/api/index.php?action=integration_attempt_review"
```
