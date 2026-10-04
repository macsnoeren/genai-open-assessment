#!/usr/bin/env python3
"""
Demo-"externe website" voor de externe koppeling van genai-open-assessment.

VOORBEELD, NIET VOOR PRODUCTIE: geen eigen login, geen CSRF-bescherming, de
ontdubbeling van webhooks staat alleen in het geheugen en de API-key staat in
een omgevingsvariabele van dit proces. Gebruik het om de flow te zien en als
startpunt voor een echte integratie (zie docs/integration-api.md).

Alleen de standaardbibliotheek. Instellingen via omgevingsvariabelen:

    APP_URL          basis-URL van de toetsapplicatie   (standaard http://localhost:8080)
    INTEGRATION_KEY  API-key van de koppeling          (verplicht)
    WEBHOOK_SECRET   webhookgeheim van de koppeling    (verplicht voor /webhook)
    PORT             poort van deze demo                (standaard 9000)
    WEBHOOK_FAIL     "1" = /webhook antwoordt 500 (om de backoff te testen)

Routes:
    GET  /          formulier: toets, ref en naam; POST start de poging aan de
                    serverkant en stuurt de browser door naar de launch_url
    GET  /return    terugkeer na inleveren: toont integration_attempt als JSON
    POST /review    meldt een menselijke beoordeling terug (vanaf /return)
    POST /webhook   controleert handtekening en tijdstempel, logt, antwoordt 204
    GET  /open      de lijst met openstaande pogingen
"""

import hashlib
import hmac
import html
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

APP_URL = os.environ.get("APP_URL", "http://localhost:8080").rstrip("/")
API_URL = APP_URL + "/api/index.php"
INTEGRATION_KEY = os.environ.get("INTEGRATION_KEY", "")
WEBHOOK_SECRET = os.environ.get("WEBHOOK_SECRET", "")
PORT = int(os.environ.get("PORT", "9000"))
WEBHOOK_FAIL = os.environ.get("WEBHOOK_FAIL") == "1"
MAX_WEBHOOK_AGE = 300  # seconden

SEEN_EVENT_IDS = set()  # ontdubbelen (at-least-once); in productie in de database


def api(method, action, body=None, **params):
    """Roept de integratie-API aan (server-to-server, Bearer-key). Geeft (status, json)."""
    query = urllib.parse.urlencode({"action": action, **params})
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(f"{API_URL}?{query}", data=data, method=method)
    req.add_header("Authorization", "Bearer " + INTEGRATION_KEY)
    if data is not None:
        req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            return resp.status, json.loads(resp.read() or b"{}")
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read() or b"{}")
        except ValueError:
            return e.code, {"error": "geen JSON"}


def verify_webhook(headers, body):
    """Controleert de HMAC-handtekening (constant-time) en de leeftijd van de tijdstempel."""
    ts = headers.get("X-Assessment-Timestamp", "")
    sig = headers.get("X-Assessment-Signature", "")
    if not ts.isdigit() or abs(time.time() - int(ts)) > MAX_WEBHOOK_AGE:
        return False
    expected = "sha256=" + hmac.new(WEBHOOK_SECRET.encode(), ts.encode() + b"." + body,
                                    hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, sig)


def page(title, body):
    return (f"<!doctype html><html lang='nl'><head><meta charset='utf-8'><title>{html.escape(title)}</title>"
            "<style>body{font-family:system-ui,sans-serif;max-width:60rem;margin:2rem auto;padding:0 1rem}"
            "pre{background:#f4f4f4;padding:1rem;overflow:auto}label{display:block;margin:.5rem 0}"
            "input,select{padding:.3rem}nav a{margin-right:1rem}</style></head><body>"
            "<nav><a href='/'>Start</a><a href='/open'>Open pogingen</a></nav>"
            f"<h1>{html.escape(title)}</h1>{body}</body></html>")


class Handler(BaseHTTPRequestHandler):

    def send_html(self, status, content):
        data = content.encode()
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def redirect(self, url):
        self.send_response(302)
        self.send_header("Location", url)
        self.end_headers()

    def read_form(self):
        length = int(self.headers.get("Content-Length") or 0)
        return urllib.parse.parse_qs(self.rfile.read(length).decode())

    def do_GET(self):
        url = urllib.parse.urlparse(self.path)
        query = urllib.parse.parse_qs(url.query)
        if url.path == "/":
            status, data = api("GET", "integration_exams")
            if status != 200:
                return self.send_html(502, page("Fout", f"<pre>{html.escape(json.dumps(data))}</pre>"))
            options = "".join(f"<option value='{e['exam_id']}'>{html.escape(e['title'])} "
                              f"({e['question_count']} vragen"
                              f"{', niveaus' if e.get('grading_scale') == 'levels' else ''})</option>"
                              for e in data["exams"])
            ref = f"demo-{int(time.time())}"
            form = ("<form method='post' action='/start'>"
                    f"<label>Toets <select name='exam_id'>{options}</select></label>"
                    f"<label>Eigen referentie <input name='external_ref' value='{ref}'></label>"
                    "<label>Naam deelnemer <input name='display_name' value='Sam'></label>"
                    "<button>Start de toets</button></form>")
            return self.send_html(200, page("Demo-leeromgeving", form))

        if url.path == "/return":
            attempt_id = (query.get("attempt_id") or [""])[0]
            status, data = api("GET", "integration_attempt", attempt_id=attempt_id)
            body = (f"<p>Terug van de toets (status in de URL: {html.escape((query.get('status') or [''])[0])}; "
                    "die URL is niet ondertekend, dus de echte status komt van de API).</p>"
                    f"<pre>{html.escape(json.dumps(data, indent=2, ensure_ascii=False))}</pre>")
            # Eindcijfer (nieuwere webapp): grade en, bij een woordbeoordeling, grade_label
            if status == 200 and (data.get("grade") is not None or data.get("grade_label")):
                grade = f"{data['grade']:.1f}".replace(".", ",") if data.get("grade") is not None else ""
                label = data.get("grade_label") or ""
                body = (f"<p><strong>Eindcijfer:</strong> {html.escape(' · '.join(x for x in (grade, label) if x))}"
                        f"{' (handmatig aangepast)' if data.get('grade_overridden') else ''}</p>") + body
            levels = data.get("grading_scale") == "levels" if status == 200 else False
            if status == 200 and data.get("status") in ("graded", "reviewed"):
                if levels:
                    # Toets met niveaus: per vraag een niveau (level) in plaats van een score
                    def level_select(a):
                        current = (a["ai"] or {}).get("level") or ""
                        opts = "".join(f"<option{' selected' if lv == current else ''}>{lv}</option>"
                                       for lv in ("", "onvoldoende", "voldoende", "goed", "uitstekend"))
                        return f"<select name='level_{a['question_id']}'>{opts}</select>"
                    rows = "".join(
                        f"<label>Vraag {a['nr']}: niveau {level_select(a)} "
                        f"feedback <input name='feedback_{a['question_id']}' size='40'></label>"
                        for a in data["answers"])
                else:
                    rows = "".join(
                        f"<label>Vraag {a['nr']}: score <input name='score_{a['question_id']}' size='3' "
                        f"value='{'' if a['ai'] is None or a['ai']['score'] is None else round(a['ai']['score'])}'> "
                        f"feedback <input name='feedback_{a['question_id']}' size='40'></label>"
                        for a in data["answers"])
                body += ("<h2>Menselijke beoordeling terugmelden</h2>"
                         f"<form method='post' action='/review'><input type='hidden' name='attempt_id' value='{data['attempt_id']}'>"
                         "<label>Beoordelaar <input name='reviewer' value='Demo-docent'></label>"
                         f"{rows}<button>Terugmelden</button></form>")
            return self.send_html(status if status != 200 else 200, page(f"Poging {attempt_id}", body))

        if url.path == "/open":
            status, data = api("GET", "integration_attempts", filter=(query.get("filter") or ["open"])[0])
            rows = "".join(f"<li><a href='/return?attempt_id={a['attempt_id']}'>{a['attempt_id']} "
                           f"({html.escape(a['external_ref'])})</a>: {a['status']}"
                           f"{' — review nodig' if a['review_needed'] else ''}</li>"
                           for a in data.get("attempts", []))
            return self.send_html(200, page("Open pogingen", f"<ul>{rows or '<li>Geen</li>'}</ul>"))

        self.send_html(404, page("Niet gevonden", ""))

    def do_POST(self):
        url = urllib.parse.urlparse(self.path)
        if url.path == "/webhook":
            body = self.rfile.read(int(self.headers.get("Content-Length") or 0))
            if not verify_webhook(self.headers, body):
                print("WEBHOOK GEWEIGERD: ongeldige handtekening of tijdstempel", flush=True)
                self.send_response(401)
                self.end_headers()
                return
            event = json.loads(body)
            duplicate = event.get("event_id") in SEEN_EVENT_IDS
            SEEN_EVENT_IDS.add(event.get("event_id"))
            print(f"WEBHOOK {'(dubbel) ' if duplicate else ''}{self.headers.get('X-Assessment-Event')}: "
                  f"{json.dumps(event, ensure_ascii=False)}", flush=True)
            self.send_response(500 if WEBHOOK_FAIL else 204)
            self.end_headers()
            return

        form = self.read_form()
        field = lambda name: (form.get(name) or [""])[0]
        if url.path == "/start":
            status, data = api("POST", "integration_attempt_start", {
                "exam_id": int(field("exam_id") or 0),
                "external_ref": field("external_ref"),
                "display_name": field("display_name"),
                "return_url": f"http://localhost:{PORT}/return",
            })
            if status in (200, 201):
                return self.redirect(data["launch_url"])
            return self.send_html(status, page("Starten mislukt", f"<pre>{html.escape(json.dumps(data))}</pre>"))

        if url.path == "/review":
            grades = []
            for key, values in form.items():
                if key.startswith("score_") and values[0].strip() != "":
                    qid = int(key[len("score_"):])
                    grades.append({"question_id": qid, "score": int(values[0]),
                                   "feedback": field(f"feedback_{qid}")})
                elif key.startswith("level_") and values[0].strip() != "":
                    qid = int(key[len("level_"):])
                    grades.append({"question_id": qid, "level": values[0].strip(),
                                   "feedback": field(f"feedback_{qid}")})
            status, data = api("POST", "integration_attempt_review", {
                "attempt_id": int(field("attempt_id")), "reviewer": field("reviewer"), "grades": grades})
            if status == 200:
                return self.redirect(f"/return?attempt_id={int(field('attempt_id'))}")
            return self.send_html(status, page("Terugmelden mislukt", f"<pre>{html.escape(json.dumps(data))}</pre>"))

        self.send_html(404, page("Niet gevonden", ""))

    def log_message(self, fmt, *args):
        sys.stderr.write("%s %s\n" % (self.address_string(), fmt % args))


if __name__ == "__main__":
    if not INTEGRATION_KEY:
        sys.exit("Zet INTEGRATION_KEY (en WEBHOOK_SECRET) in de omgeving.")
    print(f"Demo-site op http://localhost:{PORT} (app: {APP_URL})", flush=True)
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()
