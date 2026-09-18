# AI Feedback Processor

This component of the application is responsible for the asynchronous processing of student answers using Generative AI (LLMs). 

## Overview
The `process_ai_feedback.py` script acts as a background worker. It ensures the web server is not burdened with heavy AI computations when students submit an exam.

### How it works
1. **Poll**: The script periodically requests new, ungraded student answers via the API (`action=open_student_answers`).
2. **Processing**: For each answer, the specific exam prompt (or a fallback) is combined with the question and criteria into the *system* message. The student's answer is sent separately as the *user* message, wrapped in `<student_answer>` tags and truncated to `MAX_ANSWER_CHARS`, so it is treated as data rather than instructions.
3. **Injection check** (optional): a model configured via `INJECTION_CHECK_MODEL` first screens the answer for prompt-injection attempts (instructions aimed at the AI). If flagged, a warning is prepended to the AI feedback and, with `INJECTION_ZERO_SCORE`, the recorded AI score is set to 0 while the model's own score stays visible in the feedback text. Testing showed that small models (e.g. qwen3:4b) still follow injected instructions during grading despite role separation and repeated instructions, so this override is what keeps manipulated scores out of the statistics. The teacher's grade is never affected.
4. **AI Assessment**: The data is sent to a local Ollama server with an enforced JSON schema. Multiple models can be consulted for comparison. The output is validated: the score must be one of 0, 1, 5 or 10, and the feedback text is length-limited and stripped of labels that the web app's parser relies on.
5. **Storage**: The validated output (score and feedback) is sent back to the web application via the API (`action=submit_ai_feedback`). Answers that fail `MAX_ATTEMPTS` times are marked as not assessable so the queue does not stall.

### Prompt injection
Student answers are untrusted input. The worker mitigates prompt injection in layers: role separation and delimiting (system vs. user message), truncation and an explicit `num_ctx` (so a long answer cannot push the instructions out of the context window), a schema-enforced and validated output, and an optional AI-based screening step. With small local models none of these is watertight on its own; the AI scores remain an aid for the teacher, not a final grade.

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
INJECTION_CHECK_MODEL = "llama3" # Model that screens answers for prompt injection (None disables)
INJECTION_ZERO_SCORE = True # Record an AI score of 0 when injection is suspected (original score stays visible)
MAX_ATTEMPTS = 3 # Attempts before an answer is marked as not assessable
NUM_CTX = 8192 # Context window passed to Ollama
MAX_ANSWER_CHARS = 4000 # Student answers longer than this are truncated
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