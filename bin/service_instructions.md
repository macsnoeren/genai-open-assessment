# Windows Service Instructies

Dit document beschrijft hoe je de `GenAIFeedbackService` als een Windows Service kunt installeren en beheren.

## Vereisten
- Python moet geïnstalleerd zijn en toegevoegd aan de `PATH` omgevingsvariabele.
- De `pywin32` bibliotheek moet geïnstalleerd zijn: `pip install pywin32`.
- Je moet een Command Prompt of PowerShell openen **als Administrator**.

## Installatie en Beheer

Navigeer in de administrator command prompt naar de `bin` map van dit project.

### 1. Service Installeren
Voer het volgende commando uit om de service te installeren. Dit registreert de service bij Windows.
```shell
python service_ai_feedback.py install
```

### 2. Service Starten
Na installatie kun je de service starten met:
```shell
python service_ai_feedback.py start
```
Je kunt de service ook starten via het `Services.msc` venster in Windows. Zoek naar "GenAI Open Assessment Feedback Service".

### 3. Service Stoppen
Om de service te stoppen:
```shell
python service_ai_feedback.py stop
```

### 4. Service Verwijderen
Om de service volledig van je systeem te verwijderen:
```shell
python service_ai_feedback.py remove
```

## Logging
De service schrijft logs naar de Windows Event Viewer onder "Windows Logs" -> "Application". Zoek naar events met de bron `GenAIFeedbackService`. Dit is de plek om te controleren op foutmeldingen of de status van de service.