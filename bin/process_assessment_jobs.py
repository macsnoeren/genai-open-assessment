# Copyright (C) 2025 JMNL Innovation.
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

"""
Assessment-worker voor het agentic beoordelen van studentantwoorden.

Haalt de runs op die een docent heeft gestart (open_assessment_jobs), laat de
agents uit assessment_agents.py ze beoordelen en stuurt het resultaat terug
(submit_assessment_result). Draait als eigen proces naast process_ai_feedback.py
en process_design_jobs.py, zodat een bulkrun van een hele klas een docent die
een vraag ontwerpt niet laat wachten.

Starten:  cd bin && python process_assessment_jobs.py
"""

import json
import time
from typing import Dict, List, Optional

import requests

import config
from config import BASE_URL
from process_ai_feedback import API_HEADERS, LEGACY_API_KEY_IN_QUERY, INJECTION_CHECK_MODEL, api_params, check_base_url
from assessment_agents import (ASSESSMENT_MAX_EXTRA_ROUNDS, ASSESSMENT_MODEL, ASSESSMENT_VALIDATION_MODEL, STALE,
                               AssessmentOrchestrator)

# =========================
# INSTELLINGEN (optioneel in config.py)
# =========================

# Seconden tussen twee keer jobs ophalen.
ASSESSMENT_POLL_INTERVAL = getattr(config, "ASSESSMENT_POLL_INTERVAL", 15)

# Aantal pogingen per run voordat die op "mislukt" komt. De docent kan het
# antwoord daarna opnieuw laten beoordelen.
ASSESSMENT_MAX_ATTEMPTS = getattr(config, "ASSESSMENT_MAX_ATTEMPTS", 3)

# Aantal jobs per keer ophalen (de API staat 1 tot 10 toe).
ASSESSMENT_JOBS_LIMIT = 3

# =========================
# API
# =========================


def fetch_open_assessment_jobs() -> List[Dict]:
    """Haalt de open agentic beoordelingen op uit de API."""
    response = requests.get(
        BASE_URL,
        params=api_params(action="open_assessment_jobs", limit=ASSESSMENT_JOBS_LIMIT),
        headers=API_HEADERS,
        timeout=30,
    )

    if response.status_code == 401:
        print("API-key geweigerd (401). Controleer API_KEY in config.py en of de key actief is.")
        if not LEGACY_API_KEY_IN_QUERY:
            print("Draait de server nog de oude code (key alleen via ?api_key=)? "
                  "Zet dan tijdelijk LEGACY_API_KEY_IN_QUERY = True in config.py, "
                  "zie docs/rollout-new-version.md.")
        return []
    if response.status_code == 404:
        print("Server kent open_assessment_jobs nog niet; rol eerst de nieuwe webapp uit.")
        return []
    try:
        data = response.json()
    except json.JSONDecodeError:
        print(f"Onverwacht antwoord van de API (status {response.status_code}).")
        return []
    if isinstance(data, dict) and isinstance(data.get("jobs"), list):
        return data["jobs"]
    print("Kan de agentic beoordelingen niet ophalen:", data)
    return []


def submit_assessment_result(job: Dict, result: Optional[Dict] = None,
                             error: Optional[str] = None) -> Optional[Dict]:
    """
    Stuurt het resultaat (of een fout) van een run naar de API.

    :return: het JSON-antwoord, STALE bij een 409 (verouderd: de docent heeft
             intussen opnieuw gestart), of None bij een fout
    """
    payload = {"assessment_id": job["assessment_id"]}
    if error is not None:
        payload["error"] = error
    else:
        payload["result"] = result

    try:
        response = requests.post(
            BASE_URL,
            params=api_params(action="submit_assessment_result"),
            json=payload,
            headers=API_HEADERS,
            timeout=60,
        )
    except requests.RequestException as e:
        print(f"Beoordeling {job['assessment_id']}: versturen mislukt:", e)
        return None

    if response.status_code == 409:
        print(f"Beoordeling {job['assessment_id']}: verouderd, overgeslagen.")
        return STALE
    if response.status_code != 200:
        print(f"Beoordeling {job['assessment_id']}: versturen mislukt, status {response.status_code} - {response.text[:200]}")
        return None
    try:
        return response.json()
    except json.JSONDecodeError:
        print(f"Beoordeling {job['assessment_id']}: onverwacht antwoord van de API.")
        return None

# =========================
# HOOFDLOOP
# =========================


def run():
    """
    Hoofdproces:
    - haalt elke ASSESSMENT_POLL_INTERVAL seconden open runs op
    - laat de orchestrator elke run beoordelen en insturen
    - stuurt na ASSESSMENT_MAX_ATTEMPTS mislukte pogingen een fout in, zodat de
      run op "mislukt" komt en de docent opnieuw kan starten
    """
    check_base_url()
    if LEGACY_API_KEY_IN_QUERY:
        print("LET OP: LEGACY_API_KEY_IN_QUERY staat aan. De API-key wordt ook als "
              "?api_key= meegestuurd en belandt in de access log van de server. "
              "Zet dit uit zodra de nieuwe versie is uitgerold (docs/rollout-new-version.md).")
    print(f"AI-beoordelingsagents gestart (model {ASSESSMENT_MODEL}, validatie {ASSESSMENT_VALIDATION_MODEL}, "
          f"injection-controle {INJECTION_CHECK_MODEL or 'uit'}, extra rondes {ASSESSMENT_MAX_EXTRA_ROUNDS})...")

    orchestrator = AssessmentOrchestrator(submit_assessment_result)
    # Pogingen per assessment_id. Staat in het geheugen: na een herstart begint de teller opnieuw.
    attempts: Dict[int, int] = {}

    while True:
        try:
            jobs = fetch_open_assessment_jobs()
            if jobs:
                print(f"{len(jobs)} open agentic beoordeling(en) gevonden.")

            for job in jobs:
                key = job["assessment_id"]
                attempts[key] = attempts.get(key, 0) + 1
                print(f"Beoordeling {key} (poging {attempts[key]}/{ASSESSMENT_MAX_ATTEMPTS})")

                if orchestrator.handle(job):
                    attempts.pop(key, None)
                    continue

                if attempts[key] >= ASSESSMENT_MAX_ATTEMPTS:
                    print(f"Beoordeling {key}: {attempts[key]} keer mislukt, wordt als mislukt gemarkeerd.")
                    reason = (f"De AI-agents konden na {attempts[key]} pogingen geen bruikbare beoordeling maken. "
                              "Start opnieuw of beoordeel dit antwoord zelf.")
                    if submit_assessment_result(job, error=reason) is not None:
                        attempts.pop(key, None)
                else:
                    print(f"Beoordeling {key} wordt later opnieuw geprobeerd.")

        except Exception as e:
            print("Onverwachte fout:", e)

        time.sleep(ASSESSMENT_POLL_INTERVAL)


if __name__ == "__main__":
    run()
