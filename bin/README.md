# AI Feedback Processor

This component of the application is responsible for the asynchronous processing of student answers using Generative AI (LLMs). 

## Overview
The `process_ai_feedback.py` script acts as a background worker. It ensures the web server is not burdened with heavy AI computations when students submit an exam.

### How it works
1. **Poll**: The script periodically requests new, ungraded student answers via the API (`action=open_student_answers`).
2. **Processing**: For each answer, the specific exam prompt (or a fallback) is combined with the question and the student's response.
3. **AI Assessment**: The data is sent to a local Ollama server. Multiple models can be consulted simultaneously for comparison.
4. **Storage**: The AI's JSON output (score and feedback) is sent back to the web application via the API (`action=submit_ai_feedback`).

## Requirements
- Python 3.x
- Ollama (installed and running)
- The Python `requests` library: `pip install requests`

## Installation & Configuration

1. **Configuration**: Create a `config.py` file in this directory (`bin/`) with the following content:

```python
API_KEY = "your_api_key" # Generate this in the Admin panel of the webapp
BASE_URL = "http://localhost/index.php"
OLLAMA_URL = "http://localhost:11434/api/generate"
LLM_MODELS = ["llama3", "phi3"] # List of models you want to use
POLL_INTERVAL = 30 # Interval in seconds between checks
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