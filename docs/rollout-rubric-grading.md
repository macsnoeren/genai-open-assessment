# Beoordelen met de rubric uitrollen

Deze handleiding beschrijft hoe je de beoordeling per criterium (branch
`dev-rubric-grading`) uitrolt. Het gaat bijna alleen om de AI-feedbackservice
op de Windows-machine.

## Wat er verandert

| Onderdeel | Wijziging | Gevolg |
|---|---|---|
| Worker | `process_ai_feedback.py` herkent rubric-criteria (de opbouw van de AI-vraagontwerper) en beoordeelt dan per criterium | Andere criteria worden beoordeeld zoals voorheen |
| `ai_feedback` | Per model een extra blok `Criteria:` onder `Feedback:` | De scores (`Model:` … `Aantal punten:`) worden ongewijzigd uitgelezen |
| Webapp | Alleen hulpteksten bij de criteria (goedkeuren en vraag bewerken) | Geen schema- of API-wijziging |

Er is **geen overgangsvlag** nodig en de volgorde maakt niet uit: de nieuwe
worker werkt met de oude webapp en omgekeerd.

## Stap 1 – Worker bijwerken (Windows-machine)

1. Vervang `bin\process_ai_feedback.py`. Optioneel (alleen voor tests):
   `test_rubric_grading.py` en `fixtures\criteria_rubric.txt`.
2. **Optioneel**, in de **eigen** `config.py` op die machine (staat niet in git;
   zonder deze regels gelden de defaults):

   ```python
   # RUBRIC_GRADING = True   # False = altijd de oude beoordeling
   # RUBRIC_NUM_CTX = 16384  # contextvenster voor rubric-beoordelingen
   ```

3. Herstart `python process_ai_feedback.py`. Herstart ook
   `process_design_jobs.py`: die importeert `process_ai_feedback.py`.

## Stap 2 – Webapp bijwerken

`git pull` op de server (alleen hulpteksten), eventueel PHP-FPM herstarten
vanwege de opcache.

## Stap 3 – Controleren

Laat een student (of een testaccount) een toets maken met een vraag die via
"Vraag ontwerpen met AI" is goedgekeurd. In de worker-uitvoer staat
`Antwoord N: rubric herkend (… criteria), beoordeling per criterium.` In de
AI-feedback staat per model een blok `Criteria:` met per criterium
*voldaan*, *deels voldaan* of *niet voldaan*. De AI-scores verschijnen zoals
altijd in de overzichten.

Een vraag met zelfgeschreven criteria geeft geen "rubric herkend" en geen
`Criteria:`-blok.

## Terugdraaien

Zet `RUBRIC_GRADING = False` in `config.py` en herstart de worker, of zet de
oude `process_ai_feedback.py` terug. Al opgeslagen feedback met een
`Criteria:`-blok blijft gewoon leesbaar; de scores worden op dezelfde manier
uitgelezen.
