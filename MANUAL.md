# Gebruikershandleiding GenAI Open Assessment

Welkom bij de handleiding van **GenAI Open Assessment**. Deze applicatie ondersteunt het afnemen en beoordelen van open kennisvragen met behulp van Generatieve AI als assistent.

---

## Inhoudsopgave
1. [Inleiding](#1-inleiding)
2. [Rollen en Rechten](#2-rollen-en-rechten)
3. [Inloggen en Registreren](#3-inloggen-en-registreren)
4. [Voor Studenten](#4-voor-studenten)
5. [Voor Docenten](#5-voor-docenten)
6. [Voor Beheerders (Admin)](#6-voor-beheerders-admin)

---

## 1. Inleiding
Deze applicatie stelt docenten in staat om toetsen met open vragen te ontwerpen. Studenten beantwoorden deze vragen digitaal. Vervolgens kan een lokaal draaiend AI-model de antwoorden analyseren op basis van door de docent opgegeven criteria. De docent behoudt altijd de eindcontrole en kan de AI-beoordeling vergelijken met eigen bevindingen.

---

## 2. Rollen en Rechten
Er zijn vier rollen in het systeem:
*   **Student**: Kan toetsen maken en eigen resultaten inzien.
*   **Docent**: Kan toetsen maken, vragen beheren, resultaten inzien en handmatig beoordelen.
*   **Beoordelaar**: Kan alleen toegewezen toetsen beoordelen (beperkte rechten t.o.v. docent).
*   **Admin**: Heeft volledige toegang, inclusief gebruikersbeheer, technische instellingen (API keys) en prompt-beheer.

---

## 3. Inloggen en Registreren

### Registratie
Wanneer de applicatie voor het eerst wordt opgestart en er nog geen gebruikers zijn, kan de eerste gebruiker zich registreren. Dit account krijgt automatisch **Admin** rechten.
Daarna kunnen alleen Admins nieuwe gebruikers aanmaken via het beheerderspaneel.

### Inloggen
Ga naar de startpagina en voer je e-mailadres en wachtwoord in.
*   **Wachtwoord vergeten?** Vraag je beheerder om je wachtwoord te resetten.
*   **Eerste keer inloggen?** Als de beheerder dit heeft ingesteld, moet je direct na het inloggen een nieuw wachtwoord kiezen.

---

## 4. Voor Studenten

### Dashboard
Na het inloggen zie je het student dashboard. Hier staan:
1.  **Beschikbare toetsen**: Toetsen die open staan om te maken.
2.  **Mijn gemaakte toetsen**: Een overzicht van toetsen waar je aan begonnen bent of die je hebt ingeleverd.

![Screenshot van student dashboard](images/student_dashboard.png)

### Een toets maken
Je kunt een toets starten via het dashboard of via een **directe link** die je van je docent hebt gekregen.
*   **Gasttoegang**: Als je een link hebt gekregen, hoef je niet in te loggen. Vul enkel je naam in om te starten.
*   **Tussentijds opslaan**: Je kunt je antwoorden tussentijds opslaan en later verdergaan (zolang je dezelfde browser/apparaat gebruikt bij gasttoegang, of ingelogd bent).
*   **Inleveren**: Klik op "Definitief inleveren" als je klaar bent. Hierna kun je niets meer wijzigen.

### Resultaten
Zodra de docent de resultaten heeft vrijgegeven of beoordeeld, kun je via "Mijn toetsen" je antwoorden en de feedback bekijken.

---

## 5. Voor Docenten

### Dashboard
Op het dashboard zie je een overzicht van al je toetsen.
*   **Nieuwe toets**: Klik op de knop om een toets aan te maken.
*   **Link kopiëren**: Klik op het klembord-icoontje naast een toets om de directe link voor studenten te kopiëren.
*   **Status**: Je ziet direct of AI-beoordeling aan of uit staat voor een toets.

![Screenshot van docent dashboard](images/docent_dashboard.png)

### Toets aanmaken & Instellingen
Bij het maken of bewerken van een toets zijn de volgende instellingen belangrijk:
*   **Titel & Omschrijving**: Zichtbaar voor de student.
*   **AI Prompt**: Selecteer welke systeem-instructie de AI moet gebruiken. (Standaard of een specifieke prompt).
*   **AI Beoordeling inschakelen**: Vink dit aan als je wilt dat het systeem automatisch feedback genereert zodra een student inlevert.

> **Let op:** Als je de prompt van een bestaande toets wijzigt, wordt alle reeds gegenereerde AI-feedback verwijderd om consistentie te garanderen. Je moet dit bevestigen.

### Vragen beheren
Klik op "Vragen" bij een toets.
*   **Vraag**: De tekst die de student ziet.
*   **Criteria**: Dit is cruciaal voor de AI. Beschrijf hier expliciet waar een antwoord aan moet voldoen voor 0, 1, 5 of 10 punten. Hoe duidelijker de criteria, hoe beter de AI.

### Resultaten & Beoordelen
Klik op "Resultaten" bij een toets voor een lijst met inzendingen.
*   **Bekijken**: Zie het antwoord van de student, de AI-feedback en eventuele docent-feedback onder elkaar.
*   **Beoordelen (Blind)**: Een speciale modus om antwoorden na te kijken zonder dat je de naam van de student of de AI-score ziet. Dit bevordert objectiviteit.

### Validatie & Rapportage
Klik op **"Vergelijk AI"** op het dashboard.
Hier zie je hoe goed de AI presteert ten opzichte van jouw beoordeling.
*   **Grafiek**: Een scatterplot toont de correlatie tussen jouw cijfers en die van de AI.
*   **Statistieken**: Bekijk de gemiddelde afwijking en correlatiecoëfficiënt.
*   **Export**: Download een **PDF-rapport** (inclusief grafieken en de gebruikte prompt) of een CSV-bestand voor eigen analyse.

![Screenshot van rapportage](images/rapportage.png)

---

## 6. Voor Beheerders (Admin)

Als admin heb je toegang tot extra menu-opties in de navigatiebalk.

### Gebruikersbeheer
Hier kun je gebruikers aanmaken, bewerken en verwijderen.
*   **Wachtwoord reset**: Je kunt bij het bewerken van een gebruiker aanvinken dat zij bij de volgende login verplicht hun wachtwoord moeten wijzigen.
*   **Rollen**: Je kunt gebruikers promoveren tot Docent of Admin.

### Prompts Beheren
Hier beheer je de instructies die naar de AI worden gestuurd. Een goede prompt is essentieel.
*   Gebruik variabelen in je prompt tekst:
    *   `{{question_text}}`: Wordt vervangen door de vraag.
    *   `{{criteria}}`: Wordt vervangen door de beoordelingscriteria.
    *   `{{student_answer}}`: Wordt vervangen door het antwoord.
*   Zorg dat de prompt de AI instrueert om **JSON** terug te geven (zie de Hulp-pagina in de applicatie voor een voorbeeld).

### API Keys
Beheer de toegangssleutels voor de Python-service die op de achtergrond draait.
*   Maak een sleutel aan en kopieer deze naar het `config.py` bestand van de Python service.

### Audit Log
Bekijk wie wat heeft gedaan in het systeem (bijv. inloggen, toets aanmaken, cijfer geven). Je kunt deze log ook wissen indien nodig.

---

## Veelgestelde Vragen (FAQ)

**V: Kan ik plaatjes toevoegen aan een vraag?**
A: Op dit moment ondersteunt de editor alleen tekst.

**V: Waarom zie ik geen AI feedback?**
A: Controleer of:
1.  "AI Beoordeling inschakelen" aan staat bij de toets.
2.  De Python service op de server draait.
3.  Er een geldige API key is ingesteld.

**V: Hoe werkt de gast-link?**
A: De link bevat een unieke token. Als een student deze opent, wordt er een cookie geplaatst. Zolang die cookie bestaat (30 dagen), kan de student terugkeren naar zijn/haar toets via dezelfde link.

---
*Copyright (C) 2025 JMNL Innovation.*