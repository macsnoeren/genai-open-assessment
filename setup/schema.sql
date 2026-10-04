-- Gebruikerstabel: bevat zowel studenten als docenten.
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT CHECK(role IN ('student', 'docent', 'admin', 'beoordelaar')) NOT NULL,
    force_password_change INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Puntenschema's: zetten de niveaus van een levels-toets om in punten (zie Grading).
-- Onvoldoende is altijd 0 punten. Een combinatie van punten bestaat maar één keer.
CREATE TABLE IF NOT EXISTS grading_schemes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,                   -- Naam, bijvoorbeeld "Standaard (3/4/5)"
    points_voldoende INTEGER NOT NULL,    -- Punten voor voldoende
    points_goed INTEGER NOT NULL,         -- Punten voor goed
    points_uitstekend INTEGER NOT NULL,   -- Punten voor uitstekend (ook de noemer van het cijfer)
    owner_id INTEGER,                     -- Docent die het schema maakte; NULL = systeemschema (alleen de admin wijzigt het)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (points_voldoende, points_goed, points_uitstekend),
    CHECK (points_voldoende > 0 AND points_voldoende < points_goed AND points_goed < points_uitstekend AND points_uitstekend <= 100),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);
INSERT OR IGNORE INTO grading_schemes (name, points_voldoende, points_goed, points_uitstekend, owner_id)
VALUES ('Standaard (3/4/5)', 3, 4, 5, NULL);

-- Toetsen (voorheen exams): hoofd-entiteit voor een toets.
CREATE TABLE IF NOT EXISTS exams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    docent_id INTEGER NOT NULL,
    public_token TEXT UNIQUE, -- Unieke token voor de publieke link
    prompt_id INTEGER, -- Gekoppelde prompt voor AI beoordeling
    ai_grading_enabled INTEGER DEFAULT 0, -- AI beoordeling aan/uit (0=uit, 1=aan)
    shared INTEGER DEFAULT 0, -- Gedeeld met andere docenten (0=nee, 1=ja): inzien en beoordelen, niet wijzigen
    published INTEGER DEFAULT 0, -- Zichtbaar voor ingelogde studenten (0=nee, 1=ja)
    grading_scale TEXT NOT NULL DEFAULT 'points', -- points (score 0-10 per antwoord) | levels (niveau per antwoord); zie Grading::SCALE_*
    grading_scheme_id INTEGER, -- Puntenschema bij levels (NULL bij points)
    show_grade_label INTEGER DEFAULT 0, -- Eindcijfer als woord tonen (0=nee, 1=ja), alleen bij levels
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Voorkom dat een docent wordt verwijderd als er nog toetsen aan gekoppeld zijn.
    FOREIGN KEY (docent_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE SET NULL,
    -- Een puntenschema dat een toets gebruikt, kan niet worden verwijderd.
    FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes(id) ON DELETE RESTRICT
);

-- Vragen per toets.
CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    exam_id INTEGER NOT NULL,
    question_text TEXT NOT NULL,
    criteria TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Als een toets wordt verwijderd, worden alle bijbehorende vragen ook verwijderd.
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Toetspogingen: koppelt een student aan een specifieke gestarte toets.
CREATE TABLE IF NOT EXISTS student_exams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER, -- Mag NULL zijn voor gasten
    guest_name TEXT,    -- Naam van de gaststudent
    exam_id INTEGER NOT NULL,
    unique_id TEXT NOT NULL,
    access_token TEXT UNIQUE, -- Token voor de cookie om sessie te herstellen
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    -- Handmatig aangepast eindcijfer (zie Grading::attemptResult()); alle NULL = geen aanpassing
    grade_override REAL,              -- Cijfer 0-10 (één decimaal), bij een toets zonder woordbeoordeling
    grade_override_label TEXT,        -- Woord (onvoldoende|voldoende|goed|uitstekend), bij een toets met woordbeoordeling
    grade_override_reason TEXT,       -- Verplichte reden (alleen zichtbaar voor docenten en beoordelaars)
    grade_override_by INTEGER,        -- Wie het cijfer aanpaste
    grade_override_at DATETIME,       -- Wanneer
    grade_override_basis REAL,        -- Berekend cijfer op het moment van aanpassen (voor de waarschuwing als dat later verandert)
    -- Als een student of toets wordt verwijderd, worden de pogingen ook verwijderd.
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY(grade_override_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Studentantwoorden: de daadwerkelijke antwoorden van een student op vragen per toetspoging.
CREATE TABLE IF NOT EXISTS student_answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_exam_id INTEGER NOT NULL,
    question_id INTEGER NOT NULL,
    answer TEXT,
    ai_feedback TEXT,
    ai_updated_at DATETIME,
    teacher_score INTEGER,  -- Docentscore 0-10 (toets met grading_scale points)
    teacher_level TEXT,     -- Docentniveau onvoldoende|voldoende|goed|uitstekend (toets met grading_scale levels)
    teacher_feedback TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Als een toetspoging of vraag wordt verwijderd, worden de antwoorden ook verwijderd.
    FOREIGN KEY(student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE,
    FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- API sleutels voor externe services (zoals de AI feedback script).
CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    api_key TEXT UNIQUE NOT NULL, -- SHA-256 hash van de key
    active INTEGER DEFAULT 1,
    scope TEXT NOT NULL DEFAULT 'worker', -- worker (AI-workers) | integration (externe koppeling); zie ApiKey::SCOPE_*
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Audit log voor het bijhouden van acties in het systeem.
CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    user_name TEXT,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Als een gebruiker wordt verwijderd, blijft de log bestaan maar wordt de user_id op NULL gezet.
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Prompts voor AI beoordeling
CREATE TABLE IF NOT EXISTS prompts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    prompt_text TEXT NOT NULL,
    grading_scale TEXT NOT NULL DEFAULT 'points', -- points | levels: een toets kiest alleen een prompt met dezelfde schaal
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- AI-vraagontwerpen: een docent laat een open vraag + gewenst antwoord door de
-- ontwerp-agents uitwerken tot een rubric. De tabel is ook de wachtrij voor de
-- ontwerp-worker (status analysis_pending of assessment_pending).
-- Geen CHECK op status: de geldige waarden staan als constanten in QuestionDesign.
CREATE TABLE IF NOT EXISTS question_designs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    exam_id INTEGER NOT NULL,             -- Toets waarin de goedgekeurde vraag komt
    docent_id INTEGER,                    -- Docent die het ontwerp startte (NULL als het account is verwijderd)
    question_text TEXT NOT NULL,          -- Oorspronkelijke vraag van de docent
    model_answer TEXT NOT NULL,           -- Gewenst antwoord van de docent
    status TEXT NOT NULL DEFAULT 'analysis_pending', -- Zie QuestionDesign::STATUS_*
    revision INTEGER NOT NULL DEFAULT 1,  -- Omhoog bij elke docentactie; de worker stuurt hem terug (tegen verouderde resultaten)
    analysis TEXT,                        -- JSON: uitvoer van de Analysis Agent
    teacher_answers TEXT,                 -- JSON: [{question, why, answer}] op de verduidelijkende vragen
    assessment TEXT,                      -- JSON: uitvoer van de Assessment Agent (rubric + uitleg)
    validation TEXT,                      -- JSON: uitvoer van de Validation Agent (controles + verbeterde rubric)
    teacher_feedback TEXT,                -- Laatste bijsturing van de docent
    error_message TEXT,                   -- Reden waarom de worker opgaf (status failed)
    question_id INTEGER,                  -- Vraag die bij goedkeuring is aangemaakt
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME,
    -- Een verwijderde toets verwijdert zijn ontwerpen; een verwijderde docent of vraag laat het ontwerp staan.
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (docent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL
);

-- Agentic beoordelingen van studentantwoorden: de assessment-agents (Evidence,
-- Assessment, Validation) beoordelen een antwoord op een vraag met rubric. Dit is
-- een AI-beoordeling naast ai_feedback; het resultaat komt nooit in teacher_score. Elke start is een nieuwe rij (geschiedenis); een
-- eerdere open of afgeronde run van hetzelfde antwoord krijgt status superseded.
-- De tabel is ook de wachtrij voor de assessment-worker (status pending).
-- Geen CHECK op status: de geldige waarden staan als constanten in AnswerAssessment.
CREATE TABLE IF NOT EXISTS answer_assessments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_answer_id INTEGER NOT NULL,    -- Beoordeeld antwoord
    requested_by INTEGER,                  -- Docent die de run startte (NULL: automatisch gestart of account verwijderd)
    status TEXT NOT NULL DEFAULT 'pending', -- Zie AnswerAssessment::STATUS_*
    question_snapshot TEXT NOT NULL,       -- Vraagtekst bij het starten
    criteria_snapshot TEXT NOT NULL,       -- questions.criteria bij het starten (de worker parseert hieruit de rubric)
    answer_snapshot TEXT NOT NULL,         -- Studentantwoord bij het starten
    rubric TEXT,                           -- JSON: de door de worker geparste rubric (vastlegging)
    evidence TEXT,                         -- JSON: uitvoer van de Evidence Agent
    rounds TEXT,                           -- JSON: [{assessment, validation}] per ronde (1-3)
    decision TEXT,                         -- JSON: beslissing van de orchestrator (deterministisch)
    run_log TEXT,                          -- JSON: modellen, tijdsduren, injection-vermoeden, start- en eindtijd
    final_score INTEGER,                   -- AI-score van de agentic beoordeling (0, 1, 5 of 10), uit decision (grading_scale points)
    final_level TEXT,                      -- AI-niveau van de agentic beoordeling, uit decision (grading_scale levels)
    human_review_needed INTEGER NOT NULL DEFAULT 0, -- 1 = de AI is onzeker; menselijke controle nodig (uit decision)
    error_message TEXT,                    -- Reden waarom de worker opgaf (status failed)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Een verwijderd antwoord (of de poging, vraag of toets erboven) verwijdert de runs;
    -- een verwijderde docent laat de run staan.
    FOREIGN KEY (student_answer_id) REFERENCES student_answers(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_answer_assessments_answer_status ON answer_assessments (student_answer_id, status);

-- Externe koppelingen: een andere website (leeromgeving, cursusplatform) laat haar
-- eigen deelnemers hier een toets maken. Beheerd door de admin. De API-key (scope
-- integration) hoort bij precies één koppeling; wordt de key verwijderd, dan
-- verdwijnt de koppeling mee. Aan- en uitzetten gaat via api_keys.active.
CREATE TABLE IF NOT EXISTS integrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,                   -- Naam van de externe website (uniek, gecontroleerd in Integration::nameExists())
    api_key_id INTEGER NOT NULL UNIQUE,   -- API-key van de koppeling (scope integration)
    return_origin TEXT NOT NULL,          -- scheme://host[:port]; de terugkeer-URL moet precies deze origin hebben
    webhook_url TEXT,                     -- HTTPS-URL voor webhooks; NULL = geen webhooks
    webhook_secret TEXT NOT NULL,         -- Geheim voor de HMAC-SHA256-handtekening (in platte tekst: nodig om te ondertekenen)
    min_confidence TEXT NOT NULL DEFAULT 'hoog', -- hoog|middel|laag: onder deze confidence is review_needed waar
    created_by INTEGER,                   -- Admin die de koppeling aanmaakte
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Toetsen die een koppeling mag gebruiken (alleen met ai_grading_enabled = 1 te starten).
CREATE TABLE IF NOT EXISTS integration_exams (
    integration_id INTEGER NOT NULL,
    exam_id INTEGER NOT NULL,
    PRIMARY KEY (integration_id, exam_id),
    FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Pogingen via een koppeling: een gewone gastpoging (student_exams, student_id NULL)
-- met deze extra rij. De status wordt niet opgeslagen maar berekend
-- (IntegrationAttempt::summary()). Een verwijderde koppeling verwijdert deze rij;
-- de poging blijft dan als gewone gastpoging bestaan.
CREATE TABLE IF NOT EXISTS integration_attempts (
    student_exam_id INTEGER PRIMARY KEY,  -- De toetspoging (ook het attempt_id in de API)
    integration_id INTEGER NOT NULL,      -- Koppeling die de poging startte
    external_ref TEXT NOT NULL,           -- Eigen referentie van de externe website (uniek per koppeling)
    return_url TEXT NOT NULL,             -- Terugkeer-URL na inleveren (zelfde origin als integrations.return_origin)
    launch_token_hash TEXT UNIQUE,        -- SHA-256 van de eenmalige startlink
    launch_expires_at DATETIME,           -- Verlooptijd van de startlink (UTC)
    launch_used_at DATETIME,              -- Moment waarop de startlink is gebruikt (NULL = nog niet gestart)
    reviewed_at DATETIME,                 -- Gezet door integration_attempt_review (menselijke beoordeling afgerond)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (integration_id, external_ref),
    FOREIGN KEY (student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE
);

-- Outbox voor webhooks: één rij per (poging, event), at-least-once afgeleverd
-- tijdens de polls van de workers (IntegrationEvent::deliverDue()).
CREATE TABLE IF NOT EXISTS integration_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,  -- Ook het event_id in de payload (om te ontdubbelen)
    integration_id INTEGER NOT NULL,
    student_exam_id INTEGER NOT NULL,
    event TEXT NOT NULL,                   -- attempt.submitted | attempt.graded | attempt.reviewed
    payload TEXT NOT NULL,                 -- JSON zonder event_id (wordt bij het versturen toegevoegd); geen toetsinhoud
    attempts INTEGER NOT NULL DEFAULT 0,   -- Aantal mislukte afleverpogingen
    next_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- Volgende poging (NULL = opgegeven)
    delivered_at DATETIME,                 -- Moment van een 2xx-antwoord
    last_status INTEGER,                   -- Laatste HTTP-status (0 = geen antwoord)
    last_error TEXT,                       -- Laatste fout (max. 300 tekens)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_exam_id, event),
    FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE,
    FOREIGN KEY (student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_integration_events_due ON integration_events (delivered_at, next_attempt_at);
