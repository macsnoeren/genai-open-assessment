# AI Feedback Processor

This component of the application is responsible for the asynchronous processing of student answers using Generative AI (LLMs). 

## Overview
The `process_ai_feedback.py` script acts as a background worker. It ensures the web server is not burdened with heavy AI computations when students submit an exam.

### How it works
1. **Poll**: The script periodically requests new, ungraded student answers via the API (`action=open_student_answers`). The API key is sent as `Authorization: Bearer <key>`; keys are stored hashed in the web application, so a lost key must be regenerated in the admin panel.
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
BASE_URL = "http://localhost:8080/api/index.php" # Buiten localhost is https:// verplicht
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

## AI-vraagontwerper (`process_design_jobs.py`)

A second, separate worker supports teachers who design a new open question. The teacher enters a question and the desired answer in the web app; this worker lets three agents work it out and sends the result back. It runs as its own process so a teacher who is waiting interactively does not queue behind student answers, and it can be started and stopped independently.

- `process_design_jobs.py`: the main loop. Every `DESIGN_POLL_INTERVAL` seconds it fetches open designs (`GET action=open_design_jobs`), lets the orchestrator handle each job, and posts the result (`POST action=submit_design_result`). A `409` means the teacher changed something in the meantime: the result is stale and skipped. After `DESIGN_MAX_ATTEMPTS` failures for the same step it posts an `error`, so the design shows as failed and the teacher can retry.
- `design_agents.py`: the agents and the orchestrator, without network code towards the web app. *Analysis Agent* (essential elements, clarity, issues, clarifying questions for the teacher), *Assessment Agent* (rubric: criteria marked essentieel/aanvullend, levels for 10/5/1/0 points, alternative answers) and *Validation Agent* (six critical checks and an improved rubric). Output is enforced with a JSON schema and validated with `validate_*()`, which mirror `QuestionDesign::normalize*()` in the web app (keep the limits equal on both sides). All teacher text goes into the user message as labelled blocks (`<vraag>`, `<gewenst_antwoord>`, …), treated as data.
- It reuses `call_ollama()`, `check_base_url()`, `API_HEADERS` and `api_params()` from `process_ai_feedback.py`, so it uses the same `config.py`, API key and Ollama.

Start it next to the feedback processor:
```bash
python process_design_jobs.py
```

Optional settings in `config.py` (all have defaults, an existing `config.py` keeps working):

```python
DESIGN_MODEL = "gpt-oss:120b-cloud"            # Model for all agents (default: last entry of LLM_MODELS)
DESIGN_VALIDATION_MODEL = "gpt-oss:120b-cloud" # Separate model for the Validation Agent (default: DESIGN_MODEL)
DESIGN_POLL_INTERVAL = 10                      # Seconds between polls (teachers are waiting)
DESIGN_MAX_ATTEMPTS = 3                        # Attempts per step before the design is marked as failed
NUM_PREDICT_DESIGN = 6000                      # Token budget per agent call; capped at NUM_PREDICT_MAX
DESIGN_NUM_CTX = 16384                         # Context window for the agents (larger than NUM_CTX)
```

Tests (mocked `call_ollama()`, nothing is sent to Ollama or the web app; requires a `config.py`):
```bash
python3 -m unittest test_design_agents -v
```
The fixtures in `fixtures/` (PLC example) are also usable for manual `curl` tests against the API. For live tests use a cloud model such as `gpt-oss:120b-cloud`, not a local model.

## Rubric grading (`process_ai_feedback.py`)

When a question was designed with the question designer, its criteria contain the rubric in a fixed text layout (written by `QuestionDesign::rubricToCriteriaText()` in the web app: the headings `Beoordelingscriteria:` and `Puntentoekenning:`, lines like `- [essentieel] name: description` and `10 punten: …` up to `0 punten: …`, optionally `Modelantwoord:` and `Ook correct:`). `parse_rubric_criteria()` recognises that layout. The feedback processor then grades per criterion:

- The model gets a dedicated rubric prompt (question, model answer, numbered criteria, alternative answers, the four levels) and a JSON schema with `criteria` before `score`, so it first judges every criterion (`voldaan`, `deels`, `niet`, with a short explanation) and only then picks the score. The exam's custom prompt is not used for these questions.
- `validate_rubric_feedback()` requires every criterion exactly once. Cloud models do not enforce `minItems`, so an incomplete judgement gets one targeted correction attempt. A score of 10 while an essential criterion is not fully met becomes 5.
- The `ai_feedback` block gets an extra `Criteria:` section below `Feedback:` with one line per criterion. The scores are read by the web app exactly as before.
- Criteria in any other form (hand-written, or a rubric whose layout the teacher broke) are graded the old way, with the text as `{{criteria}}`.

Optional settings in `config.py`:

```python
RUBRIC_GRADING = True   # False = always grade the old way, criteria as plain text
RUBRIC_NUM_CTX = 16384  # Context window for rubric grading (the rubric makes the prompt longer)
```

Tests (mocked `call_ollama()`, requires a `config.py`):
```bash
python3 -m unittest test_rubric_grading -v
```
`fixtures/criteria_rubric.txt` is the real output of `rubricToCriteriaText()` for the rubric in `fixtures/validation.json`. If that PHP function changes, regenerate it from the repository root:
```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli php -r 'require "app/models/QuestionDesign.php"; $v = json_decode(file_get_contents("bin/fixtures/validation.json"), true); echo QuestionDesign::rubricToCriteriaText("PLC'"'"'s zijn slecht beveiligd; een aanvaller kan het proces verstoren. Zet ze achter een firewall.", $v["rubric"]);' > bin/fixtures/criteria_rubric.txt
```

## Dataset Import
Het script `dataset_import.py` kan worden gebruikt om de **Mohler ASAG** dataset (van HuggingFace) te importeren in de database. Dit is nuttig voor testdoeleinden en om de nauwkeurigheid van de AI te valideren tegenover menselijke scores.

Hiervoor is de `datasets` library vereist:
`pip install datasets`

---
*Copyright (C) 2025 JMNL Innovation.*