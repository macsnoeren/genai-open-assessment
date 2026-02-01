# Copyright (C) 2025 JMNL Innovation.
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

import sqlite3
import os
import secrets
from datasets import load_dataset

# Database configuratie
DB_PATH = os.path.join(os.path.dirname(__file__), '..', 'database', 'database.sqlite')

def get_db_connection():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def import_dataset():
    print("Dataset laden...")
    # Login using e.g. `huggingface-cli login` to access this dataset if needed
    ds = load_dataset("nkazi/MohlerASAG", "cleaned")
    
    if not os.path.exists(DB_PATH):
        print(f"Fout: Database niet gevonden op {DB_PATH}")
        return

    conn = get_db_connection()
    cursor = conn.cursor()

    # 1. Zoek een docent/admin gebruiker om de toets aan te koppelen
    cursor.execute("SELECT id FROM users WHERE role IN ('docent', 'admin') LIMIT 1")
    user = cursor.fetchone()
    
    if not user:
        print("Fout: Geen docent of admin gebruiker gevonden in de database. Maak eerst een gebruiker aan.")
        conn.close()
        return

    docent_id = user['id']
    print(f"Toets wordt gekoppeld aan gebruiker ID: {docent_id}")

    # 2. Maak de toets aan
    exam_title = "Mohler ASAG Dataset Import"
    exam_description = "Geïmporteerde vragen uit de Mohler ASAG dataset (cleaned)."
    public_token = secrets.token_hex(16)
    
    # We zetten AI grading standaard uit voor deze import
    cursor.execute("""
        INSERT INTO exams (title, description, docent_id, public_token, ai_grading_enabled)
        VALUES (?, ?, ?, ?, 0)
    """, (exam_title, exam_description, docent_id, public_token))
    
    exam_id = cursor.lastrowid
    print(f"Toets '{exam_title}' aangemaakt met ID: {exam_id}")

    # 3. Verwerk dataset en voeg vragen toe
    # De dataset is waarschijnlijk een DatasetDict (met 'train') of direct een Dataset.
    # Als het een DatasetDict is (heeft keys die splits zijn), pak de eerste split als 'train' ontbreekt.
    if hasattr(ds, 'column_names') and isinstance(ds.column_names, dict):
        # Het is een DatasetDict
        if 'train' in ds:
            data = ds['train']
        else:
            data = ds[list(ds.keys())[0]]
    else:
        data = ds
    
    # We moeten unieke vragen filteren, want de dataset bevat meerdere rijen per vraag (voor verschillende studentantwoorden)
    unique_questions = {}
    all_answers_by_question = {}
    
    print("Vragen verwerken...")
    if len(data) > 0:
        print(f"Beschikbare kolommen: {list(data[0].keys())}")

    for row in data:
        q_text = row.get('question')
        ref_answer = row.get('desired_answer') or row.get('reference_answer') or row.get('instructor_answer')
        
        if not q_text or not ref_answer:
            continue
        
        # Clean text
        q_text = q_text.replace("<STOP>", "").strip()
        ref_answer = ref_answer.replace("<STOP>", "").strip()

        # Gebruik de vraagtekst als sleutel om dubbelen te voorkomen
        if q_text not in unique_questions:
            unique_questions[q_text] = ref_answer
            all_answers_by_question[q_text] = []

        # Store student answer for later grouping
        student_ans = row.get('student_answer')
        if student_ans:
            student_ans = student_ans.replace("<STOP>", "").strip()
            raw_score = row.get('score_mean') or row.get('score_avg') or 0
            teacher_score = int(round(float(raw_score) * 2))
            all_answers_by_question[q_text].append({
                'answer': student_ans,
                'score': teacher_score
            })

    print(f"{len(unique_questions)} unieke vragen gevonden.")

    # 4. Vragen in database invoegen
    count = 0
    question_ids = {} # Map om vraagtekst aan ID te koppelen
    for q_text, criteria in unique_questions.items():
        # We gebruiken het 'desired_answer' als beoordelingscriteria
        cursor.execute("""
            INSERT INTO questions (exam_id, question_text, criteria)
            VALUES (?, ?, ?)
        """, (exam_id, q_text, criteria))
        question_ids[q_text] = cursor.lastrowid
        count += 1

    print(f"Succesvol {count} vragen geïmporteerd.")

    # 5. Student antwoorden en beoordelingen importeren
    print("Studentantwoorden importeren...")
    
    # Bepaal hoeveel studenten we kunnen simuleren (max aantal antwoorden per vraag)
    max_students = 0
    for q in all_answers_by_question:
        max_students = max(max_students, len(all_answers_by_question[q]))
    
    print(f"Er worden {max_students} virtuele studenten aangemaakt...")
    
    total_answers_inserted = 0
    
    for i in range(max_students):
        # Maak een unieke gast-poging aan voor deze student
        unique_id = f"IMP-{secrets.token_hex(4)}"
        access_token = secrets.token_hex(16)
        guest_name = f"Student {i + 1}"
        
        cursor.execute("""
            INSERT INTO student_exams (exam_id, unique_id, guest_name, access_token, started_at, completed_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        """, (exam_id, unique_id, guest_name, access_token))
        
        student_exam_id = cursor.lastrowid
        
        # Loop door alle vragen van de toets
        for q_text, q_id in question_ids.items():
            answers_list = all_answers_by_question.get(q_text, [])
            
            # Als er een antwoord is op index i, voeg toe
            if i < len(answers_list):
                ans_data = answers_list[i]
                cursor.execute("""
                    INSERT INTO student_answers (student_exam_id, question_id, answer, teacher_score, teacher_feedback)
                    VALUES (?, ?, ?, ?, ?)
                """, (student_exam_id, q_id, ans_data['answer'], ans_data['score'], "Geïmporteerd uit Mohler dataset"))
                total_answers_inserted += 1
        
        if (i + 1) % 10 == 0:
            print(f"{i + 1} studenten verwerkt...")

    conn.commit()
    conn.close()
    print(f"Klaar! {total_answers_inserted} studentantwoorden geïmporteerd voor {max_students} studenten in toets {exam_id}.")

if __name__ == "__main__":
    import_dataset()
