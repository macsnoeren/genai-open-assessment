# Het gebruik van generatieve AI voor geautomatiseerde beoordeling van open kennisvragen in het hoger onderwijs
**Maurice Snoeren, 28-01-2026**

---

## Abstract
De opkomst van generatieve artificiële intelligentie (genAI) heeft geleid tot fundamentele vragen over toetsing en examinering in het hoger onderwijs. Waar genAI vaak wordt gezien als een bedreiging voor traditionele toetsvormen, biedt dezelfde technologie ook kansen om juist de toetsing van diepgaande kennis te versterken.  

In dit artikel wordt een toetsconcept beschreven waarbij docenten genAI inzetten om open kennisvragen automatisch te beoordelen op basis van expliciet gedefinieerde beoordelingscriteria en strikt ontworpen prompts.  

De centrale hypothese is dat genAI-gebaseerde beoordeling van open vragen een valide, schaalbare en controleerbare methode kan zijn om kennis van studenten te toetsen, mits het prompt-ontwerp voldoende beperkingen oplegt aan het modelgedrag.  

Op basis van een literatuurstudie en praktijkinzichten wordt aangetoond dat prompt-ontwerp een cruciale didactische en technische factor vormt in genAI-toetsing. Het artikel sluit af met concrete richtlijnen voor docenten.

**Trefwoorden:** generatieve AI, toetsing, open vragen, prompt-ontwerp, automatische beoordeling

---

## 1. Inleiding
Toetsing vervult een centrale rol in het hoger onderwijs, zowel als meetinstrument voor leerresultaten als sturingsmechanisme voor leren (Biggs & Tang, 2011). Open kennisvragen worden algemeen beschouwd als valide instrumenten om conceptueel begrip en samenhangende kennis te toetsen, maar hun toepassing wordt in de praktijk beperkt door de hoge beoordelingslast voor docenten.  

Met de opkomst van generatieve AI, en in het bijzonder grote taalmodellen (LLM’s), ontstaat de mogelijkheid om deze beoordelingslast gedeeltelijk te automatiseren. Tegelijkertijd blijkt in de praktijk dat ongecontroleerd gebruik van genAI leidt tot inconsistente, moeilijk verwerkbare en pedagogisch ongewenste output.  

Dit artikel betoogt dat niet het model, maar het promptontwerp bepalend is voor de betrouwbaarheid van genAI-gebaseerde kennistoetsing.

---

## 2. Probleemstelling en hypothese
Hoewel genAI technisch in staat is om open antwoorden te analyseren, blijkt in de praktijk dat modellen vaak extra uitleg geven, eigen beoordelingscriteria introduceren of afwijken van afgesproken beoordelingsschalen. Dit vormt een risico voor toetsvaliditeit, transparantie en navolgbaarheid.  

**Centrale hypothese:**  
> Generatieve AI kan open kennisvragen valide, consistent en schaalbaar beoordelen, mits docenten expliciete beoordelingscriteria combineren met strikt gedefinieerde prompts die het modelgedrag beperken.

---

## 3. Conceptueel model voor genAI-gebaseerde kennistoetsing
In het voorgestelde model ontwikkelt de docent een toetsapplicatie waarin genAI fungeert als uitvoerend beoordelingsmechanisme. De docent blijft verantwoordelijk voor:  

- het formuleren van leerdoelen  
- het ontwerpen van open kennisvragen  
- het definiëren van beoordelingscriteria  
- het ontwerpen en beheren van prompts  

GenAI voert uitsluitend uit wat expliciet is gedefinieerd. Alle studentantwoorden, prompts en AI-output worden opgeslagen, zodat herbeoordeling en auditing mogelijk blijven.

---

## 4. Literatuurstudie

### 4.1 Automatische beoordeling van open antwoorden
Recente studies tonen aan dat LLM’s in staat zijn om open antwoorden te beoordelen met een betrouwbaarheid die in veel gevallen vergelijkbaar is met menselijke beoordelaars, mits gebruik wordt gemaakt van expliciete rubrics (Zhai et al., 2025). Zonder dergelijke rubrics neemt de variatie in beoordeling significant toe.

### 4.2 Rubric- en prompt-gebaseerde beoordeling
Onderzoek naar rubric-aligned grading (Kumar et al., 2024) laat zien dat AI-modellen beter presteren wanneer zij strikt binnen vooraf gedefinieerde beoordelingsdimensies opereren. Recente praktijkstudies benadrukken dat prompt-ontwerp hierbij een even belangrijke rol speelt als de rubric zelf.

### 4.3 Validiteit, transparantie en AI
Onderwijskundige literatuur benadrukt dat toetsvaliditeit afhankelijk is van consistentie, uitlegbaarheid en controleerbaarheid (Biggs & Tang, 2011). Nederlandse richtlijnen (SURF Communities, 2024) stellen dat AI-gebruik bij toetsing alleen verantwoord is wanneer menselijke verantwoordelijkheid expliciet geborgd blijft.

---

## 5. Prompt-ontwerp als didactisch kerninstrument

### 5.1 Waarom prompt-ontwerp cruciaal is
Generatieve AI-modellen zijn van nature ontworpen om behulpzaam, verklarend en creatief te zijn. Dit gedrag is functioneel in leercontexten, maar problematisch in summatieve toetsing. Zonder expliciete instructies produceren modellen:  

- uitgebreide analyses  
- alternatieve beoordelingsschalen  
- niet-gevraagde feedback  

Deze output ondermijnt automatische verwerking en toetsvaliditeit.

### 5.2 Principes voor effectieve toets-prompts
Op basis van praktijkervaring en literatuur kunnen vijf kernprincipes worden onderscheiden:  

1. **Taakreductie** – de prompt bevat één expliciete taak: beoordelen.  
2. **Gedragsbeperking** – ongewenst gedrag (uitleg, analyse) wordt expliciet verboden.  
3. **Expliciete criteria** – alle beoordelingscriteria worden door de docent aangeleverd.  
4. **Vast outputformaat** – de outputstructuur is strikt gedefinieerd.  
5. **Determinisme** – variatie wordt beperkt door lage temperatuurinstellingen en eenvoudige beoordelingsschalen.

### 5.3 Bouwstenen van een toets-prompt
Een robuuste genAI-toetsprompt bestaat uit de volgende vaste onderdelen:  

- Context-reset (bijv. “Negeer alle eerdere context”)  
- Roldefinitie (“Je bent een automatisch beoordelingssysteem”)  
- Taken en puntenschaal  
- Inhoudelijke criteria of modelantwoord  
- Regels (wat het model niet mag doen)  
- Vast outputformaat (bij voorkeur JSON)  
- Studentantwoord  

### 5.4 Voorbeeld van een strikte toets-prompt
```text
Negeer alle eerdere context.

Je bent een automatisch beoordelingssysteem.
Je mag GEEN uitleg, analyse of extra tekst geven.

TAKEN:
- Beoordeel het antwoord van de student.
- Ken punten toe: 0, 1, 5 of 10.

HET JUISTE ANTWOORD EN CRITERIA:
[door docent geformuleerd]

REGELS:
- Geef ALLEEN de onderstaande output.
- Voeg niets toe.

OUTPUT (JSON):
{ "score": <0-10>, "feedback": "<max 2 zinnen, je-vorm>" }

STUDENTANTWOORD:
[antwoord student]
```

---

## 6. Discussie
De analyse toont aan dat genAI-gebaseerde kennistoetsing technisch haalbaar is, maar didactisch alleen verantwoord wanneer prompt-ontwerp expliciet wordt gezien als onderdeel van toetsontwerp. In dit licht verschuift een deel van de toetsdeskundigheid van correctie naar ontwerp.  

Een belangrijk voordeel van deze aanpak is dat open kennisvragen weer schaalbaar worden, zonder dat toetsing verschuift naar oppervlakkige gesloten vraagvormen.

---

## 7. Conclusie
Generatieve AI kan een waardevolle rol spelen in het beoordelen van open kennisvragen, mits docenten expliciete criteria combineren met strikt ontworpen prompts. Prompt-ontwerp blijkt geen technische bijzaak, maar een didactisch kerninstrument dat bepalend is voor validiteit, consistentie en controleerbaarheid.  

Door deze aanpak kunnen open vragen opnieuw centraal worden gesteld in toetsing, terwijl werkdruk wordt verminderd en feedbackkwaliteit behouden blijft.

---

## Referenties
- Biggs, J., & Tang, C. (2011). *Teaching for quality learning at university* (4e ed.). Open University Press.  
- Kumar, R., Patel, S., & Li, J. (2024). Rubric-aligned automated grading using large language models. *Computers & Education: Artificial Intelligence, 5*, 100145. [https://doi.org/10.1016/j.caeai.2023.100145](https://doi.org/10.1016/j.caeai.2023.100145)  
- SURF Communities. (2024). AI en toetsen: Check je toetsen met de toolkit AI in je toetsontwerp. [https://communities.surf.nl/digitaal-toetsen/artikel/ai-en-toetsen-check-je-toetsen-met-de-toolkit-ai-in-je-toetsontwerp](https://communities.surf.nl/digitaal-toetsen/artikel/ai-en-toetsen-check-je-toetsen-met-de-toolkit-ai-in-je-toetsontwerp)  
- SURF Communities. (2024). Wat betekent AI voor toetsing? [https://communities.surf.nl/ai-in-education/artikel/wat-betekent-ai-voor-toetsing](https://communities.surf.nl/ai-in-education/artikel/wat-betekent-ai-voor-toetsing)  
- Npuls. (2024). Handreiking (G)AI voor examencommissies en examinatoren. [link](https://community-data-ai.npuls.nl/file/download/cdf31042-bdd5-4a83-bf18-ce472e1a4fe6/ai_handreikingexamencommissies_examinatoren_versieapril24_def.pdf)  
- Npuls. (2024). Generatieve AI in onderwijs: regels en adviezen. [link](https://community-data-ai.npuls.nl/file/download/65b4c781-3c02-40a0-a922-0801a9b486be/generatieve-ai-in-onderwijs-regels-en-adviezen.pdf)  
- Universiteit Utrecht. (2024). Themadossier generatieve AI in het onderwijs. [link](https://www.uu.nl/onderwijs/onderwijsadvies-training/kennisdossiers/themadossier-generatieve-ai-in-het-onderwijs)  
- Wei, J., Wang, X., Schuurmans, D., Bosma, M., Chi, E., Le, Q., & Zhou, D. (2022). Chain-of-thought prompting elicits reasoning in large language models. *Advances in Neural Information Processing Systems, 35*, 24824–24837.  
- Zhai, X., He, P., & Yu, X. (2025). Automated assessment of open-ended responses using large language models. *International Journal of Artificial Intelligence in Education, 35*(1), 1–24. [https://doi.org/10.1007/s40593-025-00517-2](https://doi.org/10.1007/s40593-025-00517-2)