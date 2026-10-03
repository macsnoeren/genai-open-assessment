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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Voorkom dat een docent wordt verwijderd als er nog toetsen aan gekoppeld zijn.
    FOREIGN KEY (docent_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE SET NULL
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
    -- Als een student of toets wordt verwijderd, worden de pogingen ook verwijderd.
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Studentantwoorden: de daadwerkelijke antwoorden van een student op vragen per toetspoging.
CREATE TABLE IF NOT EXISTS student_answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_exam_id INTEGER NOT NULL,
    question_id INTEGER NOT NULL,
    answer TEXT,
    ai_feedback TEXT,
    ai_updated_at DATETIME,
    teacher_score INTEGER,
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
    final_score INTEGER,                   -- AI-score van de agentic beoordeling (0, 1, 5 of 10), uit decision
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
