# AI Feedback Processor

Dit onderdeel van de applicatie is verantwoordelijk voor het asynchroon verwerken van studentantwoorden met behulp van Generatieve AI (LLM's). 

## Overzicht
Het script `process_ai_feedback.py` fungeert als een 'worker' die op de achtergrond draait. Het zorgt ervoor dat de webserver niet wordt belast met zware AI-berekeningen tijdens het inleveren van een toets door studenten.

### Werking
1. **Poll**: Het script vraagt periodiek via de API (`action=open_student_answers`) of er nieuwe, onbeoordeelde studentantwoorden zijn.
2. **Verwerking**: Voor elk antwoord wordt de specifieke prompt van de toets (of een fallback) gecombineerd met de vraag en het antwoord.
3. **AI Beoordeling**: De data wordt naar een lokale Ollama-server gestuurd. Er kunnen meerdere modellen tegelijkertijd worden geraadpleegd voor vergelijking.
4. **Opslag**: De JSON-output van de AI (score en feedback) wordt via de API (`action=submit_ai_feedback`) teruggestuurd naar de webapplicatie.

## Vereisten
- Python 3.x
- Ollama (geïnstalleerd en draaiend)
- De Python `requests` library: `pip install requests`

## Installatie & Configuratie

1. **Configuratie**: Maak een bestand `config.py` aan in deze map (`bin/`) met de volgende inhoud:

```python
API_KEY = "jouw_api_key" # Genereer deze in het Admin paneel van de webapp
BASE_URL = "http://localhost/index.php"
OLLAMA_URL = "http://localhost:11434/api/generate"
LLM_MODELS = ["llama3", "phi3"] # Lijst met modellen die je wilt gebruiken
POLL_INTERVAL = 30 # Aantal seconden tussen checks
```

2. **Modellen**: Zorg dat de geconfigureerde modellen aanwezig zijn in Ollama:
   `ollama pull llama3`

## Gebruik

Start de feedback processor met het volgende commando:
```bash
python process_ai_feedback.py
```

## Dataset Import
Het script `dataset_import.py` kan worden gebruikt om de **Mohler ASAG** dataset (van HuggingFace) te importeren in de database. Dit is nuttig voor testdoeleinden en om de nauwkeurigheid van de AI te valideren tegenover menselijke scores.

Hiervoor is de `datasets` library vereist:
`pip install datasets`

---
*Copyright (C) 2025 JMNL Innovation.*