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
*   **Admin**: Heeft volledige toegang, inclusief gebruikersbeheer, technische instellingen (API keys, externe koppelingen) en prompt-beheer.

---

## 3. Inloggen en Registreren

### Accounts
Bij de eerste start wordt automatisch een beheerdersaccount aangemaakt (`admin@school.nl` / `admin123`); bij de eerste login moet dit wachtwoord direct worden gewijzigd.
Zelfregistratie is standaard uitgeschakeld: alleen Admins maken nieuwe gebruikers aan via het beheerderspaneel. (Instelbaar via `ALLOW_SELF_REGISTRATION` in `config/app.php`.)

Wachtwoorden moeten minimaal 12 tekens lang zijn en minimaal één letter en één cijfer bevatten. Wie het eigen wachtwoord wijzigt via het profiel, moet het huidige wachtwoord opgeven. Na 30 minuten inactiviteit word je automatisch uitgelogd.

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
*   **Delen met andere docenten**: Andere docenten kunnen de toets dan inzien en beoordelen. Wijzigen, verwijderen en vragenbeheer blijven voorbehouden aan de eigenaar (en Admins).
*   **Gastlink vernieuwen of uitzetten**: op het dashboard staan naast de gastlink van je eigen toetsen 🔄 (nieuwe link; de oude werkt direct niet meer, bijvoorbeeld als hij te ver is gedeeld) en ⛔ (geen gastlink meer). Met "Gastlink aanzetten" maak je weer een nieuwe. Gasten die al bezig zijn, kunnen gewoon verder.
*   **Publiceren voor ingelogde studenten**: Alleen gepubliceerde toetsen verschijnen in het studentdashboard en kunnen daar gestart worden. De gastlink werkt onafhankelijk van dit vinkje.

> **Let op:** Als je de prompt van een bestaande toets wijzigt, wordt alle reeds gegenereerde AI-feedback verwijderd om consistentie te garanderen. Je moet dit bevestigen.

### Vragen beheren
Klik op "Vragen" bij een toets.
*   **Vraag**: De tekst die de student ziet.
*   **Criteria**: Dit is cruciaal voor de AI. Beschrijf hier expliciet waar een antwoord aan moet voldoen voor 0, 1, 5 of 10 punten. Hoe duidelijker de criteria, hoe beter de AI.
*   **Rubric-criteria**: Criteria met de opbouw die de AI-vraagontwerper maakt (kopjes `Beoordelingscriteria:` en `Puntentoekenning:`, regels als `- [essentieel] Naam: beschrijving` en `10 punten: …` t/m `0 punten: …`) worden door de AI **per criterium** beoordeeld. Je kunt die opbouw ook zelf gebruiken. Zie "Vraag ontwerpen met AI" hieronder.

### Vraag ontwerpen met AI
Twijfel je of je vraag eenduidig is, of wil je hulp bij de beoordelingscriteria? Klik op de vragenpagina van je toets op **"Vraag ontwerpen met AI"**. Deze knop zie je alleen bij toetsen die je zelf mag wijzigen (eigenaar of Admin).

1.  **Invoeren**: Je typt de vraag en het gewenste antwoord (het antwoord dat je van een goede student verwacht) en klikt op "Ontwerp starten".
2.  **Analyse**: Een eerste AI-stap bepaalt welke onderdelen van je antwoord essentieel zijn, controleert of de vraag duidelijk is en of je gewenste antwoord de vraag echt beantwoordt, en noemt mogelijke beoordelingsproblemen.
3.  **Verduidelijkende vragen**: Heeft de AI meer informatie nodig (bijvoorbeeld "hoeveel redenen moet een student noemen?"), dan zie je die vragen met bij elke vraag *waarom* het antwoord nodig is. Je mag een vraag leeg laten; de AI maakt dan zelf een redelijke keuze. Zijn er geen vragen nodig, dan gaat het ontwerp meteen door.
4.  **Voorstel en validatie**: Een tweede AI-stap maakt een rubric: criteria (*essentieel* of *aanvullend*, elk met een toelichting), wat er nodig is voor 10, 5, 1 en 0 punten, en alternatieve correcte antwoorden. Een derde AI-stap controleert dat voorstel kritisch (dekt het je antwoord, zijn de criteria duidelijk en niet te letterlijk, sluiten de punten logisch aan?) en levert een verbeterde versie met uitleg van de wijzigingen.
5.  **Bijsturen**: Niet tevreden? Schrijf bij "Feedback voor de AI" wat er anders moet en klik op "Opnieuw laten uitwerken". Dat kan een beperkt aantal rondes; daarna pas je de rubric zelf aan.
6.  **Aanpassen en goedkeuren**: Onderaan staan de vraag en de beoordelingscriteria (opgebouwd uit de verbeterde rubric) in tekstvakken. Pas ze naar wens aan en klik op "Goedkeuren en vraag toevoegen". Pas dan komt de vraag in de toets; daarna bewerk je hem zoals elke andere vraag.
7.  **Beoordeling per criterium**: Zolang de opbouw van de criteria intact blijft (de kopjes, de regels met `[essentieel]` of `[aanvullend]` en de vier regels van de puntentoekenning), beoordeelt de AI elk studentantwoord eerst per criterium (*voldaan*, *deels voldaan* of *niet voldaan*, met een korte toelichting) en kiest pas daarna de score. Dat oordeel staat onder **"Criteria:"** in de AI-feedback. Geeft de AI 10 punten terwijl een essentieel criterium niet volledig voldaan is, dan wordt dat 5 punten. Laat je de opbouw los, dan beoordeelt de AI met je tekst als gewone criteria. Bij een vraag met rubric-criteria gebruikt de AI niet de prompt die aan de toets is gekoppeld.

Terwijl de AI werkt, ververst de pagina zichzelf. Het kan even duren; draait de AI-ontwerpassistent niet, dan zie je een melding en wordt je aanvraag verwerkt zodra die weer actief is. Mislukt een stap, dan kun je hem met "Opnieuw proberen" opnieuw laten uitvoeren. Je ontwerpen staan onder de vragentabel bij **"AI-vraagontwerpen"**, waar je ze kunt openen of verwijderen.

> **De AI beslist niets definitief.** De voorstellen zijn hulpmiddelen: jij bepaalt de vraag en de criteria, en zonder jouw goedkeuring verandert er niets aan de toets.

### Resultaten & Beoordelen
Klik op "Resultaten" bij een toets voor een lijst met inzendingen.
*   **Bekijken**: Zie het antwoord van de student, de AI-feedback en eventuele docent-feedback onder elkaar.
*   **Beoordelen (Blind)**: Een speciale modus om antwoorden na te kijken zonder dat je de naam van de student of de AI-score ziet. Dit bevordert objectiviteit.

### Antwoorden agentic beoordelen
Bij een vraag met **rubric-criteria** (zie "Vraag ontwerpen met AI") beoordelen drie samenwerkende AI-agents het studentantwoord. Je ziet per criterium welk bewijs er in het antwoord staat, hoe de agents dat lezen en waar ze het (on)eens zijn. Het resultaat is een **AI-beoordeling**, net als de gewone AI-feedback: het staat **los van jouw beoordeling**. De docentscore is altijd een menselijke beoordeling, die je zelf geeft (bijvoorbeeld via "Beoordelen (Blind)"); de agentic beoordeling verandert daar niets aan.

**Wanneer het kan:** de toetspoging is ingeleverd, het antwoord is niet leeg, bij de toets staat **AI-beoordeling** aan, en de criteria van de vraag hebben de rubric-opbouw (de kopjes `Beoordelingscriteria:` en `Puntentoekenning:`). Agentic beoordelen kan bij toetsen die je zelf beheert, die met je gedeeld zijn, of als Admin. Een Beoordelaar ziet er niets van: de blinde beoordeling blijft blind.

**Automatisch:** voldoet een antwoord aan deze voorwaarden, dan start de agentic beoordeling **vanzelf** zodra de student inlevert (of zodra de voorwaarden later gelden, bijvoorbeeld als je AI-beoordeling achteraf aanzet). Zo'n antwoord krijgt dan **geen gewone AI-feedback**: de twee beoordelingen zitten elkaar niet in de weg. Antwoorden die niet aan de voorwaarden voldoen (bijvoorbeeld bij een vraag zonder rubric, of een leeg antwoord) krijgen de gewone AI-feedback zoals altijd. Mislukt een agentic beoordeling, dan krijgt het antwoord alsnog de gewone AI-feedback.

De student ziet van de agentic beoordeling alleen de **AI-score en de feedback**, gelabeld als "AI-beoordeling (agentic), automatisch door AI, niet door je docent". Citaten, redeneringen en controles ziet alleen de docent. In de AI-gemiddelden en in "Vergelijk AI" (grafiek, tabellen en CSV) staat de agentic beoordeling als eigen bron **"Agentic AI"** naast de AI-modellen, zodat je haar met je eigen beoordeling kunt vergelijken.

**Zelf starten:** open bij "Resultaten" een inzending met **"Bekijken"**. Klik bij een antwoord op **"Agentic beoordelen"**, of bovenaan op **"Alle antwoorden agentic beoordelen"** voor de hele poging. Bij die laatste knop worden antwoorden die al een beoordeling hebben (of niet beoordeeld kunnen worden) overgeslagen; je ziet hoeveel er gestart en overgeslagen zijn. Het aantal starts per uur is begrensd. Terwijl de agents werken, ververst de pagina zichzelf; draaien ze niet, dan zie je daar een melding van en start de beoordeling zodra ze weer actief zijn.

**Wat de drie agents doen:**
1.  **Evidence Agent**: zoekt per criterium naar *letterlijke* citaten in het antwoord, en schrijft apart op wat die betekenen (interpretatie) en wat er ontbreekt. Hij vult niets aan en neemt niet aan wat de student "bedoelde".
2.  **Assessment Agent**: beoordeelt elk criterium met de rubric en het bewijs (✓ volledig, ~ gedeeltelijk, ✗ onvoldoende) en kiest daarna een score van 0, 1, 5 of 10 met de puntentoekenning van de rubric. Hij verzint geen nieuwe criteria.
3.  **Validation Agent**: controleert de voorlopige beoordeling kritisch (zeven controles, zoals "staat het gebruikte bewijs echt in het antwoord?" en "is er een andere redelijke lezing?"), mag statussen corrigeren en geeft een eindoordeel met een confidence (*hoog*, *middel* of *laag*).

Zijn de Assessment en de Validation Agent het oneens over een essentieel criterium, dan volgt automatisch één extra ronde. Daarna beslist het systeem met vaste regels (geen AI) of menselijke controle nodig is.

**De pagina lezen:**
*   Bovenaan staan de **AI-score (agentic)**, de confidence en het blok **"Menselijke controle nodig: Ja/Nee"**. Bij "Ja" is de AI onzeker en staan de redenen erbij, bijvoorbeeld: de agents zijn het oneens, een citaat staat niet letterlijk in het antwoord, de confidence is laag, de validatie bevestigt de beoordeling niet, de score past niet bij de criteria, of het antwoord bevat mogelijk instructies aan de AI. Kijk bij zo'n antwoord extra goed bij je eigen beoordeling. Op de antwoordenpagina zie je dit als *"AI onzeker: menselijke controle nodig"*.
*   Per criterium zie je drie blokken: **Bewijs** (de citaten; een citaat dat niet letterlijk in het antwoord staat, krijgt de rode markering *niet letterlijk gevonden*), **Interpretatie** (wat het bewijs betekent en wat ontbreekt) en **Beoordeling** (conclusie, redenering en eventuele correcties van de validatie). Daarboven staan de drie oordelen naast elkaar (bijvoorbeeld *Evidence: gedeeltelijk · Assessment: deels · Validation: voldaan*) met een label *eens*, *klein verschil* of *conflict*. Een conflict krijgt een gekleurde rand.
*   Onder **"Validatie"** staan de zeven controles, de gevonden problemen, de correcties en de uitleg. Een eventuele eerdere ronde staat ingeklapt, zodat je ziet wat er veranderde.
*   **"Details van de run"** toont de gebruikte modellen, tijdsduren en de rubric waarmee is beoordeeld. Zijn de criteria van de vraag daarna gewijzigd, dan meldt de pagina dat; start dan opnieuw.

**Opnieuw beoordelen:** met **"Opnieuw beoordelen"** start je een nieuwe agentic beoordeling (bijvoorbeeld als die mislukte of als de criteria zijn gewijzigd). De vorige blijft bewaard onder "Alle agentic beoordelingen van dit antwoord".

**AI of mens?** Overal staat erbij wie beoordeelde: *AI-feedback* en *Agentic AI-beoordeling* (label **AI**) komen van de AI, de *Docentbeoordeling* (label **mens**) van jou. Het eindcijfer is altijd het gemiddelde van de docentscores; AI-scores staan er alleen ter vergelijking naast.

> **De AI beslist niets definitief.** De agentic beoordeling is een AI-beoordeling en geen cijfer; de docentscore geef je altijd zelf.

### Pogingen via een externe koppeling
Een beheerder kan een andere website (bijvoorbeeld een leeromgeving) koppelen, zodat deelnemers daar een toets van deze applicatie maken zonder account (zie "Externe koppelingen" bij de beheerders).
*   **Herkennen:** bij "Resultaten" staat bij zo'n poging de badge **"Koppeling: <naam>"**; als je erover beweegt, zie je de referentie van de externe website. Op de antwoordenpagina staat bovenaan een melding met de naam van de koppeling en de referentie. Er is geen deelbare resultatenlink: de deelnemer ziet hier geen resultaat, dat bepaalt de externe website.
*   **Nakijken:** de toets wordt automatisch door de AI nagekeken, net als andere pogingen. Is de AI niet zeker genoeg, dan kijkt een persoon bij de externe website de poging na. Die kan de scores terugmelden: ze verschijnen hier als **docentscore** (Docentbeoordeling, mens).
*   **Jouw docentscore gaat ook naar de externe website.** Een score die je hier geeft, ziet de externe website de volgende keer dat zij het resultaat ophaalt. Geef je elk antwoord van de poging een score, dan krijgt de externe website een seintje dat de poging beoordeeld is.
*   **Naam wijzigen:** je kunt de naam van de deelnemer aanpassen zoals bij elke gastpoging. De externe website krijgt daar geen seintje van; ze ziet de nieuwe naam pas als ze de poging opnieuw ophaalt.
*   **Let op:** zet je de AI-beoordeling van een gekoppelde toets uit, dan kunnen er geen nieuwe pogingen meer starten en blijven ingeleverde pogingen op "wordt nagekeken" staan tot je de AI weer aanzet of zelf beoordeelt.

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
*   De kolom **Type** toont *Worker* (voor de AI-workers) of *Koppeling*. Een key van een koppeling beheer je via **Koppelingen**; verwijder je hem hier, dan verdwijnt de koppeling ook. Een workerkey werkt niet voor een koppeling en andersom.

### Externe koppelingen
Met een koppeling laat een andere website (een leeromgeving of cursusplatform) haar eigen deelnemers een toets uit deze applicatie maken, zonder account. De server van die website start een poging, stuurt de deelnemer naar een eenmalige startlink, en na het inleveren keert de deelnemer terug naar de website. De AI kijkt de toets na; de website volgt de status via de API en via webhooks (seintjes). De technische beschrijving voor de ontwikkelaars van de externe website staat in `docs/integration-api.md`; een demo staat in `docs/integration-demo/`.

*   **Aanmaken:** ga naar **Koppelingen** → **Nieuwe koppeling** en vul in:
    *   **Naam** van de externe website (docenten zien die bij de pogingen).
    *   **Origin van de terugkeer-URL**, bijvoorbeeld `https://leeromgeving.example` (zonder pad). De deelnemer keert alleen terug naar een adres op precies deze origin.
    *   **Webhook-URL** (optioneel, alleen `https`): waar de seintjes heen gaan bij ingeleverd, nagekeken en handmatig beoordeeld.
    *   **Drempel voor menselijke controle**: bij `hoog` (standaard) laat alles wat de AI niet met hoge zekerheid beoordeelt, een mens nakijken.
    *   **Toetsen** die de koppeling mag gebruiken. Alleen toetsen met AI-beoordeling aan kunnen worden gekozen.
*   **Key en geheim één keer:** na het opslaan zie je de **API-key** en het **webhookgeheim**. Ze worden maar één keer getoond. Geef ze veilig door aan de beheerder van de externe website; de key hoort alleen op hun server, nooit in een browser.
*   **Toetsen koppelen of wijzigen:** via **Wijzigen**. Een gekoppelde toets waarvan de AI-beoordeling later is uitgezet, krijgt een waarschuwing.
*   **Events bekijken:** de detailpagina (klik op de naam) toont de gekoppelde toetsen, de laatste 50 webhooks (afgeleverd, nog open of opgegeven, met de laatste status of fout) en de laatste 50 pogingen met hun status. Webhooks worden verstuurd terwijl de AI-workers draaien; staan die stil, dan gaan er ook geen webhooks. Een webhook die steeds mislukt, wordt na 8 pogingen opgegeven. De externe website kan de status altijd zelf via de API opvragen.
*   **Geheim vernieuwen:** **Webhookgeheim vernieuwen** maakt een nieuw geheim (weer één keer getoond). Het oude werkt direct niet meer, dus spreek het moment af met de externe website.
*   **Uitschakelen:** de externe website krijgt dan geen toegang meer tot de API en startlinks werken niet meer. Inschakelen herstelt dat.
*   **Verwijderen:** verwijdert de koppeling en de key. De pogingen blijven als gewone gastpogingen bij de toets staan, maar de externe website kan ze niet meer opvragen.

### Audit Log
Bekijk wie wat heeft gedaan in het systeem (bijv. inloggen, toets aanmaken, cijfer geven). Je kunt deze log ook wissen indien nodig; de regels van het laatste uur blijven dan staan, omdat de inlogbeveiliging en de limieten ze gebruiken.

### Rollen en sessies
Een gewijzigde rol geldt direct, ook als de gebruiker al is ingelogd. Een verwijderde gebruiker is direct uitgelogd. Stel je een nieuw wachtwoord in voor een gebruiker, dan worden alle sessies van die gebruiker beëindigd.

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