# Copyright (C) 2025 JMNL Innovation.
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

"""
Ontwerp-worker van de AI-vraagontwerper.

Haalt open vraagontwerpen op (open_design_jobs), laat de agents uit
design_agents.py ze uitwerken en stuurt het resultaat terug
(submit_design_result). Draait als eigen proces naast process_ai_feedback.py,
zodat een docent die interactief wacht niet achter de wachtrij met
studentantwoorden aansluit.

Starten:  cd bin && python process_design_jobs.py
"""

import json
import time
from typing import Dict, List, Optional, Tuple

import requests

import config
from config import BASE_URL
from process_ai_feedback import API_HEADERS, LEGACY_API_KEY_IN_QUERY, api_params, check_base_url
from design_agents import DESIGN_MODEL, DESIGN_VALIDATION_MODEL, STALE, Orchestrator

# =========================
# INSTELLINGEN (optioneel in config.py)
# =========================

# Seconden tussen twee keer jobs ophalen. Kort, want de docent wacht.
DESIGN_POLL_INTERVAL = getattr(config, "DESIGN_POLL_INTERVAL", 10)

# Aantal pogingen per stap voordat het ontwerp op "mislukt" komt. De docent
# kan het daarna zelf opnieuw laten proberen.
DESIGN_MAX_ATTEMPTS = getattr(config, "DESIGN_MAX_ATTEMPTS", 3)

# Aantal jobs per keer ophalen (de API staat 1 tot 10 toe).
DESIGN_JOBS_LIMIT = 3

# =========================
# API
# =========================


def fetch_open_design_jobs() -> List[Dict]:
    """Haalt de open vraagontwerpen op uit de API."""
    response = requests.get(
        BASE_URL,
        params=api_params(action="open_design_jobs", limit=DESIGN_JOBS_LIMIT),
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
        print("Server kent open_design_jobs nog niet; rol eerst de nieuwe webapp uit.")
        return []
    try:
        data = response.json()
    except json.JSONDecodeError:
        print(f"Onverwacht antwoord van de API (status {response.status_code}).")
        return []
    if isinstance(data, dict) and isinstance(data.get("jobs"), list):
        return data["jobs"]
    print("Kan de vraagontwerpen niet ophalen:", data)
    return []


def submit_design_result(job: Dict, step: str, result: Optional[Dict] = None,
                         error: Optional[str] = None) -> Optional[Dict]:
    """
    Stuurt het resultaat (of een fout) van een stap naar de API.

    :return: het JSON-antwoord (met next_status), STALE bij een 409 (verouderd
             resultaat: de docent heeft intussen iets gewijzigd), of None bij een fout
    """
    payload = {"design_id": job["design_id"], "revision": job["revision"], "step": step}
    if error is not None:
        payload["error"] = error
    else:
        payload["result"] = result

    try:
        response = requests.post(
            BASE_URL,
            params=api_params(action="submit_design_result"),
            json=payload,
            headers=API_HEADERS,
            timeout=60,
        )
    except requests.RequestException as e:
        print(f"Ontwerp {job['design_id']}: versturen mislukt:", e)
        return None

    if response.status_code == 409:
        print(f"Ontwerp {job['design_id']} (revision {job['revision']}, {step}): verouderd, overgeslagen.")
        return STALE
    if response.status_code != 200:
        print(f"Ontwerp {job['design_id']}: versturen mislukt, status {response.status_code} - {response.text[:200]}")
        return None
    try:
        return response.json()
    except json.JSONDecodeError:
        print(f"Ontwerp {job['design_id']}: onverwacht antwoord van de API.")
        return None

# =========================
# HOOFDLOOP
# =========================


def run():
    """
    Hoofdproces:
    - haalt elke DESIGN_POLL_INTERVAL seconden open vraagontwerpen op
    - laat de orchestrator elke job uitwerken en insturen
    - stuurt na DESIGN_MAX_ATTEMPTS mislukte pogingen een fout in, zodat het
      ontwerp op "mislukt" komt en de docent het opnieuw kan proberen
    """
    check_base_url()
    if LEGACY_API_KEY_IN_QUERY:
        print("LET OP: LEGACY_API_KEY_IN_QUERY staat aan. De API-key wordt ook als "
              "?api_key= meegestuurd en belandt in de access log van de server. "
              "Zet dit uit zodra de nieuwe versie is uitgerold (docs/rollout-new-version.md).")
    print(f"AI-ontwerpassistent gestart (model {DESIGN_MODEL}, validatie {DESIGN_VALIDATION_MODEL})...")

    orchestrator = Orchestrator(submit_design_result)
    # Pogingen per (design_id, revision, step). Staat in het geheugen: na een
    # herstart begint de teller opnieuw.
    attempts: Dict[Tuple[int, int, str], int] = {}

    while True:
        try:
            jobs = fetch_open_design_jobs()
            if jobs:
                print(f"{len(jobs)} open vraagontwerp(en) gevonden.")

            for job in jobs:
                key = (job["design_id"], job["revision"], job["step"])
                attempts[key] = attempts.get(key, 0) + 1
                print(f"Ontwerp {key[0]}, revision {key[1]}, stap {key[2]} (poging {attempts[key]}/{DESIGN_MAX_ATTEMPTS})")

                if orchestrator.handle(job):
                    attempts.pop(key, None)
                    continue

                if attempts[key] >= DESIGN_MAX_ATTEMPTS:
                    print(f"Ontwerp {key[0]}: {attempts[key]} keer mislukt, wordt als mislukt gemarkeerd.")
                    reason = (f"De AI kon na {attempts[key]} pogingen geen bruikbaar resultaat maken. "
                              "Probeer het opnieuw of pas de vraag of het gewenste antwoord aan.")
                    if submit_design_result(job, job["step"], error=reason) is not None:
                        attempts.pop(key, None)
                else:
                    print(f"Ontwerp {key[0]} wordt later opnieuw geprobeerd.")

        except Exception as e:
            print("Onverwachte fout:", e)

        time.sleep(DESIGN_POLL_INTERVAL)


if __name__ == "__main__":
    run()
