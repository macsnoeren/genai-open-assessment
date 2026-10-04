# TASKS: Professionelere webinterface

Takenlijst voor de branch `dev-ui-redesign`.

**Zo gebruik je deze lijst:**

- Werk de stappen in volgorde af en vink ze af. Elke stap is klein en eindigt waar nodig met een controle (*Klaar als*).
- Commit aan het eind van elke fase (kort, Engels, zoals de bestaande historie).
- De Docker-image **kopieert** de code: draai na elke stap die je wilt bekijken `./docker/start.sh` opnieuw.
- Bekijk elke visuele stap op twee breedtes: desktop (≥ 1200px) en mobiel (375px, via de devtools van de browser).
- Lees vooraf [CLAUDE.md](CLAUDE.md) (verplichte beveiligingspatronen). Alles daarin geldt hier ook: `e()` voor alle output, geen inline handlers, inline scripts alleen met `nonce`, externe scripts en stylesheets alleen met `integrity`.

---

## Wat we bouwen

De webapp krijgt een rustiger en professioneler uiterlijk dat past bij het logo:

1. **Een kleurenschema afgeleid van het logo** (marineblauw en koningsblauw, met het groen van de vinkjes en het oranje van het potlood als accenten) in plaats van het huidige donkerrood.
2. **Navigatie in een zijbalk links** voor docent, admin en beoordelaar, gegroepeerd en met een actieve staat. De header wordt een smalle witte topbalk met breadcrumbs, de status van de AI-worker en een gebruikersmenu.
3. **Een teller bij "Beoordelen"** die laat zien hoeveel ingeleverde pogingen nog op een beoordeling wachten, zoals het aantal ongelezen berichten bij een inbox.
4. **Een openbare landingspagina** op `/` die uitlegt waarvoor de applicatie is, met een contactregel naar JMNL Innovation.
5. **Opgeschoonde componenten:** iconen in plaats van emoji, zachte badges met een vaste betekenis, geen inline styles en overal breadcrumbs.

**Buiten scope:** dark mode, een inklapbare zijbalk en een SVG-versie van het logo (zie [Later](#later-buiten-deze-branch)).

---

## Analyse: wat het nu minder professioneel maakt

- **Kleuren botsen met het logo.** De navbar en de primaire knoppen zijn donkerrood (`#b71c1c`), het logo is blauw. Rood betekent ook "fout" of "verwijderen", dus elke primaire knop leest als een waarschuwing.
- **De horizontale navigatie is vol.** Een admin heeft 9 items plus een statusbadge, naam, rol, Profiel en Uitloggen op één balk. Er is geen groepering en geen actieve staat.
- **Interne status voor iedereen.** De rode badge "Parser Inactief" staat ook bij studenten en uitgelogde bezoekers. "Parser" is bovendien een verouderde naam: het is de ping van de AI-worker (`ApiController.php`, `database/last_api_ping.txt`).
- **Emoji als iconen** (📝📊📈▶️📋✏️🗑️🔄⛔) in `docent/dashboard.php`, `api_keys.php` en `integration_view.php`. Ze zien er per besturingssysteem anders uit.
- **Badges zonder vaste betekenis:** zes felle Bootstrap-kleuren door elkaar.
- **Losse stijlen:** 55 inline `style=`-attributen met eigen hexkleuren, vooral in `exam_comparison.php` (14), `view_results.php` (10) en `register.php` (8).
- **Breadcrumbs ontbreken** op `docent/dashboard.php`, `student/dashboard.php`, `auth/change_password.php`, `auth/register.php` en `pages/privacy.php`.
- **Zware footer en cookiebanner:** een volle footer met "proof-of-concept" onder elke werkpagina en een brede zwarte cookiebalk.
- **Geen uitleg voor bezoekers:** `/` toont meteen het inlogformulier (`$action = $_GET['action'] ?? 'login'` in `htdocs/index.php`).

---

## Ontwerpbeslissingen

**B1. Kleurenschema.** Alle kleuren staan als tokens op `:root` in `htdocs/style.css`. Nergens anders staan nog hexkleuren.

| Rol | Token | Kleur | Gebruik |
|---|---|---|---|
| Merk, donker | `--brand-900` | `#0F2A4F` | zijbalk, hero van de landingspagina, topbalk van `take_exam` |
| Primair | `--brand-600` | `#1F5FAF` | knoppen, links, actief item (wit erop: contrast 6,4:1) |
| Primair, hover | `--brand-700` | `#194C8C` | hover en focus |
| Primair, zacht | `--brand-50` | `#EAF1FB` | actief menu-item, geselecteerde rij, zachte badge |
| Succes | `--success` | `#2E9E4F` | groen van de vinkjes: gepubliceerd, beoordeeld, worker actief |
| Aandacht | `--accent` | `#E8890C` | oranje van het potlood: de teller bij Beoordelen |
| Tekst op aandacht | `--on-accent` | `#0F2A4F` | tekst in de teller (contrast 5,5:1; wit haalt het niet) |
| Gevaar | `--danger` | `#C62828` | **alleen** fouten en verwijderen |
| Achtergrond | `--bg` | `#F5F7FA` | pagina |
| Vlak | `--surface` | `#FFFFFF` | kaarten, topbalk |
| Rand | `--border` | `#E2E8F0` | kaarten, tabellen, topbalk |
| Tekst | `--text` | `#1E293B` | lopende tekst |
| Tekst, gedempt | `--muted` | `#5A6B82` | labels en metadata (contrast 5,1:1 op `--bg`) |

**Zachte badges** (lichte achtergrond, donkere tekst) met dezelfde namen als de Bootstrap-kleuren, zodat `bg-success` op een badge mechanisch `badge-soft-success` wordt:

| Klasse | Achtergrond | Tekst | Betekenis |
|---|---|---|---|
| `badge-soft-primary` | `#EAF1FB` | `#194C8C` | AI aan |
| `badge-soft-success` | `#E6F4EA` | `#1E6B35` | gepubliceerd, uitstekend |
| `badge-soft-info` | `#E3F2F9` | `#0B5A7A` | gedeeld, goed |
| `badge-soft-warning` | `#FDF0DC` | `#8A4F00` | van collega, voldoende |
| `badge-soft-danger` | `#FBE9E9` | `#9B1C1C` | onvoldoende, fout |
| `badge-soft-secondary` | `#EEF1F5` | `#475569` | AI uit, neutraal |

**B2. Wie krijgt welke layout.**

- **Docent, admin en beoordelaar:** zijbalk links (240px) en een topbalk. Onder de `lg`-breedte (992px) wordt de zijbalk een offcanvas die je opent met een hamburger. Dat is Bootstrap 5.3 `offcanvas-lg`: geen eigen JS nodig, werkt met `data-bs-*`, dus binnen de CSP.
- **Student:** een eenvoudige witte topnavigatie (Dashboard, Mijn toetsen) zonder zijbalk. De flow is kort en vaak op mobiel.
- **Iemand met `force_password_change`:** de studentlayout zonder menu-items, alleen het gebruikersmenu met Uitloggen.
- **Pagina's met `$hideHeaderFooter`** (login, gastlogin, startlink, toets maken) en de nieuwe landingspagina hebben hun eigen opzet en krijgen alleen de nieuwe kleuren.

**B3. Wat in de topbalk staat** (wit, 56px, rand onder):

- Links: de hamburger (alleen onder `lg`) en de breadcrumbs. Het "← Terug"-knopje vervalt.
- Rechts: de status van de AI-worker als bolletje met tekst ("AI-worker actief" / "AI-worker reageert niet"), **alleen voor docent en admin**, en een gebruikersmenu (initialen, naam, rol) met Profiel en Uitloggen.

**B4. De navigatie staat op één plek:** een nieuwe helper `app/helpers/navigation.php` met de functie `navItems()`. Elk item heeft `group`, `label`, `action`, `icon`, `roles`, `also` (onderliggende actions die hetzelfde item actief maken) en optioneel `counter`. De layout bouwt het menu uit die array. Contract 5 in CLAUDE.md verwijst daarna naar deze helper in plaats van naar `layouts/main.php`.

| Groep | Item | Action | Icoon | Rollen | Ook actief bij |
|---|---|---|---|---|---|
| Toetsen | Dashboard | `docent_dashboard` | `bi-grid` | docent, admin | `exam_create`, `exam_edit`, `questions`, `question_create`, `question_edit`, `question_design_create`, `question_design_view`, `exam_results`, `exam_comparison`, `view_student_answers`, `answer_assessment_view` |
| Toetsen | Beoordelen (+ teller) | `pending_assessments` | `bi-check2-square` | docent, admin, beoordelaar | `grade_student_exam` |
| Toetsen | Mijn testpogingen | `my_exams` | `bi-play-circle` | docent, admin | `student_view_results` |
| Inrichting | Puntenschema's | `grading_schemes` | `bi-sliders` | docent, admin | `create_grading_scheme`, `edit_grading_scheme` |
| Inrichting | Prompts | `prompts` | `bi-chat-left-text` | admin | `prompt_create`, `prompt_edit`, `prompt_help` |
| Beheer | Gebruikers | `students` | `bi-people` | admin | `student_create` |
| Beheer | Koppelingen | `integrations` | `bi-plug` | admin | `integration_create`, `integration_edit`, `integration_view` |
| Beheer | API-keys | `api_keys` | `bi-key` | admin | |
| Beheer | Audit log | `audit_log` | `bi-journal-text` | docent, admin | |
| *(student)* | Dashboard | `student_dashboard` | `bi-house` | student | `exams_list` |
| *(student)* | Mijn toetsen | `my_exams` | `bi-journal-check` | student | `student_view_results` |

"Mijn Toetsen" heet voor docenten nu "Mijn testpogingen", omdat het naast het Dashboard (de toetsen zelf) verwarrend is. Voor studenten blijft het "Mijn toetsen". `student_edit` hoort bij geen enkel item (het is zowel "Profiel" als gebruikersbeheer).

**B5. De teller bij Beoordelen** telt precies dezelfde pogingen als de lijst op `pending_assessments`: ingeleverd en nog niet alle antwoorden door een mens beoordeeld; een docent alleen voor de eigen toetsen, beoordelaar en admin alles. Daarom gaat de query van `DocentController::pendingAssessments()` naar het model en gebruiken lijst en teller dezelfde SQL. Opmaak: een pil in `--accent` met tekst `--on-accent`, verborgen bij 0, boven de 99 "99+". Eén `COUNT`-query per pagina, alleen voor docent, admin en beoordelaar, één keer per verzoek (statische cache).

**B6. Landingspagina.** `/` zonder action toont de openbare pagina `pages/home.php` (nieuwe `case 'home'`). Wie ingelogd is, gaat door naar het eigen dashboard (`redirectByRole()`). Inloggen blijft op `/?action=login`. Uitloggen gaat daarna naar de landingspagina. De pagina leest geen sessie- of toetsgegevens en heeft geen formulier. Contact via nieuwe constanten in `config/app.php`: `CONTACT_NAME`, `CONTACT_URL` en `CONTACT_EMAIL` (leeg = niet tonen).

**B7. Iconen:** Bootstrap Icons 1.11.3 van jsdelivr. De CSP staat jsdelivr al toe voor `style-src` en `font-src`; de fonts laden relatief vanaf dezelfde CDN.

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
      integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
```

Een icoon in een knop zonder tekst krijgt altijd `aria-hidden="true"` op de `<i>` en een `aria-label` (en `title`) op de knop.

**B8. Eén naam.** Het logo zegt "Open vragen | AI-Toetsing", de `<title>` zegt "Openvragen kennistoetsing". Er komt één constante `APP_NAME = 'Open vragen | AI-Toetsing'` in `config/app.php`, gebruikt in de `<title>`, de footer en de landingspagina.

**B9. Geen contractwijziging.** Geen schemawijziging, geen wijziging aan de worker-API of de integratie-API, geen nieuwe worker-instellingen. Alleen de webapp. De CSP blijft ongewijzigd. Er hoeft niets gecoördineerd te worden uitgerold.

---

## Fase 0: Voorbereiding

- [x] **0.1** De werkkopie heeft een niet-gecommitte wijziging in `config/app.php` op `dev-rubric-levels`. Commit die daar of zet hem apart (`git stash`), zodat de nieuwe tak schoon begint.
- [x] **0.2** Tak af van `main`: `git checkout main && git pull && git checkout -b dev-ui-redesign`.
- [x] **0.3** Start met een schone database (`cd docker && docker compose down -v`, dan `./docker/start.sh`). Maak testaccounts voor de rollen docent, beoordelaar en student (admin bestaat al: `admin@school.nl`).
- [x] **0.4** Maak "voor"-screenshots van het docentdashboard, de vragenpagina, Beoordelen, de resultaten van een student en de loginpagina, op desktop en mobiel. Die heb je nodig om te vergelijken.

## Fase 1: Kleuren en basis (alleen `htdocs/style.css`)

Geen wijziging aan de markup. Na deze fase is de hele app blauw in plaats van rood.

- [x] **1.1 Tokens.** Vervang het `:root`-blok door alle tokens uit B1 (`--brand-900` t/m `--muted`). Laat de oude namen (`--brand-primary`, `--brand-primary-hover`, `--brand-bg`) voorlopig verwijzen naar de nieuwe tokens, zodat niets breekt.
- [x] **1.2 Bootstrap-variabelen.** Zet op `:root`: `--bs-primary`, `--bs-primary-rgb` (`31, 95, 175`), `--bs-link-color`, `--bs-link-color-rgb`, `--bs-link-hover-color`, `--bs-body-bg` (`--bg`), `--bs-body-color` (`--text`), `--bs-secondary-color` (`--muted`) en `--bs-border-color` (`--border`).
- [x] **1.3 Primaire knoppen.** Vervang de overrides van `.btn-primary` door Bootstrap-knopvariabelen: `--bs-btn-bg`, `--bs-btn-border-color`, `--bs-btn-hover-bg`, `--bs-btn-hover-border-color`, `--bs-btn-active-bg`, `--bs-btn-active-border-color`, `--bs-btn-disabled-bg` en `--bs-btn-focus-shadow-rgb`.
- [x] **1.4 Outline-knoppen.** Hetzelfde voor `.btn-outline-primary` (`--bs-btn-color`, `--bs-btn-border-color`, `--bs-btn-hover-bg`, `--bs-btn-hover-color`, `--bs-btn-active-bg`).
- [x] **1.5 Gevaarkleur.** Zet `--bs-danger` en `--bs-danger-rgb` op `--danger`, en hetzelfde voor `--bs-success`/`--success`.
- [x] **1.6 Tekstmuted.** Zet `.text-muted` op `color: var(--muted) !important`, zodat de 110 plekken met `text-muted` meteen het nieuwe grijs krijgen.
- [x] **1.7 Zachte badges.** Voeg de zes klassen `.badge-soft-*` uit B1 toe (achtergrond, tekstkleur, `font-weight: 600`).
- [x] **1.8 Kaarten.** Geef `.card` een rand `1px solid var(--border)`, `border-radius: .75rem` en een zachtere schaduw (`0 1px 2px rgba(15, 42, 79, .06)`). Voeg `.card-header` toe met achtergrond `--surface` en een rand onder.
- [x] **1.9 Tabellen.** Laat `.table > thead.table-light th` de kleur `--muted` krijgen, met `font-size: .8rem`, hoofdletters en `letter-spacing: .03em`.
- [x] **1.10 Focus.** Een duidelijke focusring voor toetsenbordgebruikers: `:focus-visible { outline: 2px solid var(--brand-600); outline-offset: 2px; }`.
- [x] **1.11 Toetsbalk.** Zet `.exam-topbar` op `--brand-900`.
- [x] **1.12 Navbar tijdelijk.** Zet `.navbar-custom` op `--brand-900`, zodat de oude navbar tot fase 4 al bij de rest past.
  *Klaar als:* er nergens meer rood te zien is behalve bij verwijderknoppen en foutmeldingen, op alle pagina's van de screenshots uit 0.4. Commit: `Introduce color tokens based on the logo`.

## Fase 2: Basis voor de nieuwe layout (nog geen zichtbare wijziging)

- [x] **2.1 Constanten** in `config/app.php`, met commentaar: `APP_NAME = 'Open vragen | AI-Toetsing'`, `CONTACT_NAME = 'JMNL Innovation'`, `CONTACT_URL = 'https://jmnl.nl'` en `CONTACT_EMAIL = ''`. **Controleer de URL en een eventueel e-mailadres bij Maurice.**
- [x] **2.2 Titel.** Vervang in `layouts/main.php` de standaardtitel `'Openvragen kennistoetsing'` door `APP_NAME`, en maak de titel `<paginatitel> · <APP_NAME>` als `$title` gezet is.
- [x] **2.3 Iconen laden.** Voeg de stylesheet van Bootstrap Icons uit B7 toe aan `<head>` in `layouts/main.php`, onder Bootstrap.
  *Klaar als:* een tijdelijk `<i class="bi bi-check"></i>` zichtbaar is en de console geen CSP-fout geeft. Haal het tijdelijke icoon weer weg.
- [x] **2.4 Helperbestand.** Maak `app/helpers/navigation.php` met de copyright-kop zoals de andere helpers, en laad het in `htdocs/index.php` bij de andere helpers.
- [x] **2.5 `currentAction(): string`.** Geeft `requestString($_GET, 'action', 64)` terug; leeg wordt `'home'`.
- [x] **2.6 `navItems(): array`.** Geeft de items uit de tabel in B4 terug (alleen data, geen logica).
- [x] **2.7 `navItemsForRole(string $role): array`.** Filtert `navItems()` op `roles` en groepeert op `group` (volgorde behouden).
- [x] **2.8 `navIsActive(array $item, string $current): bool`.** Waar als `$current` gelijk is aan `action` of in `also` staat.
- [x] **2.9 `userInitials(string $name): string`.** Eerste letter van het eerste en het laatste woord, in hoofdletters, via `mb_substr`/`mb_strtoupper`. Leeg geeft `'?'`.
- [x] **2.10 `workerStatus(): string`.** Verplaats de ping-logica van bovenin `layouts/main.php` naar deze functie (geeft `'active'` of `'inactive'`). De layout roept hem aan.
- [x] **2.11 Syntaxcheck** van PHP (zie CLAUDE.md).
  *Klaar als:* de app er nog precies zo uitziet als na fase 1 en de syntaxcheck slaagt. Commit: `Add navigation helper and app constants`.

## Fase 3: De teller bij Beoordelen

- [x] **3.1 Lees** `DocentController::pendingAssessments()` door. De query daar is de bron.
- [x] **3.2 `StudentExam::pendingReviewQuery(int $userId, string $role): array`** (private static). Geeft `[$sql, $params]` terug met de huidige SELECT, de `WHERE`, het filter op `e.docent_id` bij de rol `docent`, en `GROUP BY se.id HAVING graded_answers < total_answers`, zonder `ORDER BY`.
- [x] **3.3 `StudentExam::pendingReview(int $userId, string $role): array`.** Voert de query uit met `ORDER BY se.completed_at ASC` en geeft de rijen terug.
- [x] **3.4 `StudentExam::pendingReviewCount(int $userId, string $role): int`.** `SELECT COUNT(*) FROM (<query>) AS t`, met dezelfde parameters.
- [x] **3.5 Controller.** Laat `pendingAssessments()` `StudentExam::pendingReview()` gebruiken. De rest van de methode blijft gelijk.
  *Klaar als:* de pagina Beoordelen dezelfde lijst toont als vóór deze stap.
- [x] **3.6 `navCounter(string $name): int`** in `navigation.php`. Voor `'pending'`: geeft 0 als er niemand ingelogd is of de rol niet docent, admin of beoordelaar is; anders `StudentExam::pendingReviewCount($_SESSION['user_id'], $_SESSION['role'])`. Bewaar het resultaat in een `static`-variabele, zodat de query hooguit één keer per verzoek draait.
- [x] **3.7 CSS.** `.nav-counter`: `margin-left: auto`, achtergrond `--accent`, kleur `--on-accent`, `font-weight: 700`, `font-size: .75rem`, `border-radius: 999px`, `padding: .1rem .5rem`, `min-width: 1.5rem`, `text-align: center`.
  *Klaar als:* de teller na fase 4 hetzelfde getal toont als het aantal rijen op Beoordelen, voor een docent (alleen eigen toetsen) en voor een beoordelaar (alles). Commit: `Count pending reviews in the model`.

## Fase 4: Zijbalk en topbalk voor docent, admin en beoordelaar

Splits `layouts/main.php` in partials. Houd in elke stap de bestaande scripts (bevestigingsmodal, kopiëren, cookiebanner) onderaan de layout ongewijzigd.

- [ ] **4.1 Keuze van de layout.** Bepaal bovenin `layouts/main.php` `$layoutMode`: `'bare'` bij `$hideHeaderFooter`, `'staff'` bij de rollen docent, admin en beoordelaar zonder `force_password_change`, anders `'simple'` (student, uitgelogd, wachtwoord moet gewijzigd).
- [ ] **4.2 Partial `layouts/partials/sidebar.php`.** Een `<aside class="offcanvas-lg offcanvas-start app-sidebar" id="appSidebar" tabindex="-1" aria-label="Hoofdmenu">` met bovenaan het logo (`logo-h.png`, hoogte 36px, link naar `/`) en `APP_NAME` in kleine witte tekst.
- [ ] **4.3 Menu in de zijbalk.** Loop over `navItemsForRole($_SESSION['role'])`. Per groep een kopje (klein, hoofdletters, 60% wit) en per item een `<a class="app-nav-link" href="/?action=…">` met `<i class="bi … " aria-hidden="true"></i>` en het label, alles via `e()`. Het actieve item krijgt de klasse `active` en `aria-current="page"`.
- [ ] **4.4 Teller in het menu.** Heeft een item `counter` en is `navCounter()` > 0, toon dan `<span class="nav-counter">N</span>` (boven de 99: `99+`) met daarachter `<span class="visually-hidden">pogingen te beoordelen</span>`.
- [ ] **4.5 Onderkant van de zijbalk.** `© <jaar> APP_NAME · CONTACT_NAME` en een link "Privacy & Cookies", klein en 60% wit. Zonder "proof-of-concept".
- [ ] **4.6 CSS van de zijbalk.** `.app-sidebar`: breedte 240px, achtergrond `--brand-900`, tekst wit. Vanaf `lg`: `position: sticky; top: 0; height: 100vh; overflow-y: auto`. `.app-nav-link`: `display: flex; gap: .75rem; align-items: center; padding: .5rem .75rem; border-radius: .5rem; color: rgba(255,255,255,.8)`. Hover: `rgba(255,255,255,.08)`. Actief: achtergrond `rgba(255,255,255,.12)`, kleur wit en een balkje links van 3px in `--accent`.
- [ ] **4.7 Partial `layouts/partials/topbar.php`.** `<header class="app-topbar">` met links een knop `d-lg-none` (`data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Menu openen"`, icoon `bi-list`).
- [ ] **4.8 Breadcrumbs naar de topbalk.** Verplaats het breadcrumb-blok uit `<main>` naar de topbalk, naast de hamburger. Onder `md` toon je alleen het laatste item (`d-none d-md-inline-block` op de andere items). Haal het knopje "← Terug" weg.
- [ ] **4.9 Status van de AI-worker** rechts in de topbalk, alleen voor docent en admin: een bolletje (`.status-dot`, 8px, `--success` of `--danger`) met de tekst "AI-worker actief" of "AI-worker reageert niet" (tekst `d-none d-xl-inline`, altijd ook als `title`).
- [ ] **4.10 Gebruikersmenu** rechts in de topbalk: een Bootstrap-dropdown (`data-bs-toggle="dropdown"`) met een rondje met `userInitials()` en, vanaf `md`, naam en rol. Items: "Profiel" (`/?action=student_edit&id=<eigen id>`, verborgen bij `force_password_change`), een scheidingslijn en "Uitloggen".
- [ ] **4.11 CSS van de topbalk.** `.app-topbar`: hoogte 56px, achtergrond `--surface`, rand onder `--border`, `position: sticky; top: 0; z-index: 1020`, flex met de breadcrumbs links en status en gebruikersmenu rechts. `.avatar`: 32px rond, achtergrond `--brand-50`, kleur `--brand-700`, `font-weight: 700`.
- [ ] **4.12 Opbouw in `main.php`** bij `'staff'`: `<div class="app-shell">` met de zijbalk en daarnaast `<div class="app-main">` met de topbalk, de flashmeldingen en `<main class="app-content">`. `.app-shell`: `display: flex; min-height: 100vh`. `.app-main`: `flex: 1; min-width: 0`. `.app-content`: `padding: 1.5rem; max-width: 1320px; width: 100%`.
- [ ] **4.13 Geen footer** bij `'staff'` (die staat nu in de zijbalk).
- [ ] **4.14 Oude navbar weg** bij `'staff'`. Haal ook `.navbar-custom` en `.badge-status-*` uit `style.css` zodra fase 5 klaar is.
  *Klaar als:* een admin alle groepen en items ziet, een docent geen Prompts/Gebruikers/Koppelingen/API-keys, een beoordelaar alleen Beoordelen; het juiste item actief is op een onderliggende pagina (bijvoorbeeld `questions` → Dashboard); de hamburger op 375px de zijbalk opent en sluit; de teller klopt (zie 3.7); de console geen CSP-fouten geeft. Commit: `Move staff navigation to a sidebar`.

## Fase 5: Studentlayout en pagina's met een eigen opzet

- [ ] **5.1 Partial `layouts/partials/simple_nav.php`** voor `'simple'`: een witte topbalk (`.app-topbar`, dezelfde CSS) met links het logo, in het midden de student-items uit `navItemsForRole('student')` (alleen als ingelogd en geen `force_password_change`) en rechts het gebruikersmenu uit 4.10. Uitgelogd staat rechts een knop "Inloggen". Op mobiel klappen de items in onder een hamburger (gewone Bootstrap `navbar-expand-md` met `collapse`).
- [ ] **5.2 Geen workerstatus** voor studenten en uitgelogde bezoekers.
- [ ] **5.3 Footer bij `'simple'`:** één regel, klein en `--muted`: `© <jaar> APP_NAME · CONTACT_NAME · Privacy & Cookies`. Zonder "proof-of-concept".
- [ ] **5.4 `<main>` bij `'simple'`:** `container` (zoals nu), met de breadcrumbs boven de inhoud zoals nu, maar zonder "← Terug".
- [ ] **5.5 Loginpagina (`auth/login.php`).** Een gecentreerde kaart (max 420px) op `--bg`: bovenin het volledige logo (`logo.png`, max 220px breed), dan het formulier, en onderaan een link "← Terug naar de startpagina" naar `/`. Verwijder de inline styles en gebruik klassen.
- [ ] **5.6 Gastlogin (`student/guest_login.php`) en startlink (`student/integration_launch.php`):** dezelfde kaartopbouw als 5.5, zonder de link naar de startpagina bij de startlink (die deelnemer komt van een andere website). Verwijder de inline styles.
- [ ] **5.7 Toets maken (`student/take_exam.php`):** controleer dat de toetsbalk (`--brand-900`) goed oogt en dat er geen rood meer in zit.
- [ ] **5.8 Cookiebanner** als kleine kaart linksonder in plaats van een volle balk: `.cookie-toast` met `position: fixed; left: 1rem; bottom: 1rem; max-width: 420px`, achtergrond `--surface`, rand en schaduw, tekst `--text`. Houd de ids `cookieBanner` en `acceptCookiesBtn`, zodat het script gelijk blijft. Haal de inline `style="display: none; z-index: 1050;"` weg en gebruik de klasse `d-none` (pas het script aan: `classList.remove('d-none')`/`classList.add('d-none')`).
  *Klaar als:* een student, een gast, een deelnemer via een startlink en iemand met `force_password_change` elk een nette, werkende pagina zien zonder workerstatus en zonder "proof-of-concept". Commit: `Restyle student layout, login and cookie notice`.

## Fase 6: Landingspagina

- [ ] **6.1 Standaard-action.** Zet in `htdocs/index.php` `$action = $_GET['action'] ?? 'home'` (en in de `is_string`-controle ook `'home'`).
- [ ] **6.2 Case.** Voeg `case 'home': $auth->showHome(); break;` toe.
- [ ] **6.3 `AuthController::showHome()`.** Is iemand ingelogd, dan `$this->redirectByRole($_SESSION['role'] ?? 'student')`; anders `require` van `views/pages/home.php`.
- [ ] **6.4 Uitloggen.** Laat `AuthController::logout()` doorsturen naar `/` in plaats van naar `index.php?action=login`.
- [ ] **6.5 View `pages/home.php`.** Met `ob_start()`, `$hideHeaderFooter = true`, `$title = APP_NAME` en `require` van de layout, zoals de andere views. Alle tekst via `e()` of als vaste HTML.
- [ ] **6.6 Topbalk van de pagina:** logo en `APP_NAME` links, rechts een knop "Inloggen" (`/?action=login`) en, als `ALLOW_SELF_REGISTRATION` aan staat, "Account aanmaken".
- [ ] **6.7 Hero** op `--brand-900`, witte tekst, twee kolommen vanaf `lg`:
  - Links de kop *"Open vragen toetsen, met AI als eerste beoordelaar"*, één zin uitleg (*"Docenten stellen open vragen en een rubric op. De AI geeft per antwoord een onderbouwde voorbeoordeling; de docent beslist."*) en de knoppen "Inloggen" (primair, wit) en "Hoe het werkt" (anker naar 6.8, outline wit).
  - Rechts een illustratie in HTML/CSS (geen afbeelding): een witte kaart met een voorbeeldvraag, een kort studentantwoord en drie criteria met zachte badges (`Voldaan`, `Deels`, `Voldaan`) en onderaan "Voorstel: Goed · wacht op docent".
- [ ] **6.8 "Hoe het werkt"** (`id="hoe-het-werkt"`): drie genummerde stappen naast elkaar (onder elkaar op mobiel), elk met een icoon in een rondje van `--brand-50`:
  1. *Toets en rubric maken.* De docent maakt een toets met open vragen; de AI-vraagontwerper helpt een rubric op te stellen.
  2. *Antwoorden.* Studenten maken de toets, ingelogd of als gast via een link.
  3. *Voorbeoordelen en beslissen.* De AI beoordeelt per criterium en onderbouwt dat met citaten; de docent controleert en bepaalt het eindcijfer.
- [ ] **6.9 Functies:** een raster van zes kaarten (3 × 2 vanaf `lg`, 2 × 3 vanaf `md`, onder elkaar op mobiel), elk met een icoon, een kop en één zin: beoordelen op een rubric; punten of niveaus met een eigen puntenschema; agentic beoordelen met controle van citaten; de AI-vraagontwerper; AI en docent vergelijken; koppelen met een andere website (integratie-API en webhooks).
- [ ] **6.10 Blok "De docent beslist"** op `--brand-50`: de AI beoordeelt alleen voor; het eindcijfer komt altijd van een mens; de beheerder kiest de AI-modellen, die via Ollama draaien, ook op een eigen server. **Laat de formulering over de modellen controleren door Maurice**, zodat ze klopt met de manier waarop de app gebruikt wordt.
- [ ] **6.11 "Voor wie":** vier korte regels met icoon: docenten, studenten, beoordelaars, externe partijen.
- [ ] **6.12 Contact:** *"Vragen of interesse? Neem contact op met <CONTACT_NAME>."* met een knop naar `CONTACT_URL` (`target="_blank" rel="noopener"`) en, als `CONTACT_EMAIL` niet leeg is, een `mailto:`-link. Alles via `e()`.
- [ ] **6.13 Footer van de pagina:** `© <jaar> APP_NAME · CONTACT_NAME · Privacy & Cookies`.
- [ ] **6.14 CSS** voor de landingspagina in een eigen blok in `style.css` met prefix `.landing-`. Geen inline styles.
- [ ] **6.15 Controle zonder sessie:** de pagina werkt in een privévenster, zonder fouten in de console, en toont geen gegevens uit de database.
  *Klaar als:* `/` uitgelogd de landingspagina toont en ingelogd doorstuurt naar het juiste dashboard voor elke rol; uitloggen op de landingspagina uitkomt; gastlinks (`?action=guest&token=…`) en startlinks (`?action=integration_launch…`) nog precies zo werken. Commit: `Add public landing page`.

## Fase 7: Componenten opschonen

Per stap één bestand of één soort wijziging, zodat je makkelijk kunt vergelijken.

**Paginakop**

- [ ] **7.1 CSS `.page-header`:** flex, `justify-content: space-between`, `align-items: flex-end`, `gap: 1rem`, `margin-bottom: 1.5rem`, `flex-wrap: wrap`. Daarin een `h1` met klasse `h3 mb-1` en een optionele `<p class="text-muted mb-0">` als subtitel; de primaire actie rechts.
- [ ] **7.2** Gebruik `.page-header` op het docentdashboard: kop "Toetsen", subtitel "Welkom, <naam>" (via `e()`), knop "Nieuwe toets" met `bi-plus-lg`.
- [ ] **7.3** Gebruik `.page-header` op de andere overzichtspagina's: `questions.php`, `pending_assessments.php`, `exam_results.php`, `grading_schemes.php`, `prompts.php`, `students.php`, `integrations.php`, `api_keys.php`, `audit_log.php` en `student/dashboard.php`. Eén bestand per keer.

**Docentdashboard (`docent/dashboard.php`)**

- [ ] **7.4 Badges:** AI aan → `badge-soft-primary`, AI uit → `badge-soft-secondary`, Gedeeld → `badge-soft-info`, Gepubliceerd → `badge-soft-success`, Van collega → `badge-soft-warning`.
- [ ] **7.5 Gastlink in een eigen kolom** "Gastlink" in plaats van boven de actieknoppen. De werking blijft gelijk; de knoppen krijgen iconen: kopiëren `bi-copy`, nieuwe link `bi-arrow-repeat`, uitzetten `bi-slash-circle`, elk met `aria-label` en `title`.
- [ ] **7.6 Actieknoppen:** houd twee zichtbare knoppen, "Vragen" (`bi-list-check`, met tekst) en "Resultaten" (`bi-bar-chart`, alleen icoon), en zet de rest in een dropdown met `bi-three-dots` (`aria-label="Meer acties"`): Vergelijk AI en docent (`bi-graph-up`), Testen (`bi-play`), Dupliceren (`bi-files`), Bewerken (`bi-pencil`), een scheidingslijn en Verwijderen (`bi-trash`, `text-danger`). De `data-confirm`-attributen gaan mee naar de `dropdown-item`-links; het bestaande script zet ze om naar een POST.
  *Klaar als:* elke actie nog werkt, inclusief de bevestiging en de POST (controleer in de netwerktab dat verwijderen en dupliceren een POST zijn).
- [ ] **7.7** Vervang de drie `htmlspecialchars(...)` door `e(...)`.

**Iconen in plaats van emoji en tekens**

- [ ] **7.8** `api_keys.php`: 📋 → `<i class="bi bi-copy" aria-hidden="true"></i>`, met `aria-label` op de knop.
- [ ] **7.9** `integration_view.php`: twee keer 📋, idem.
- [ ] **7.10** `question_design_view.php` en `answer_assessment_validation.php`: `&#10003;` → `bi-check-circle-fill` (kleur `--success`) en `&#10007;` → `bi-x-circle-fill` (kleur `--danger`). Houd de bestaande `aria-label`s.

**Niveaubadges**

- [ ] **7.11** Vervang in `student_answers.php` (3×) en `exam_results.php` (1×) `badge bg-<?= e(Grading::levelClass(...)) ?>` door `badge badge-soft-<?= e(Grading::levelClass(...)) ?>`. `Grading::levelClass()` blijft ongewijzigd.
- [ ] **7.12** Zoek de overige badges met `bg-success`, `bg-warning`, `bg-info`, `bg-danger`, `bg-primary` en `bg-secondary` (`grep -rn 'badge bg-' app/views`) en zet ze per bestand om naar de zachte variant met dezelfde naam. Laat `text-dark` erachter weg (de zachte badge heeft al donkere tekst).

**Inline styles naar klassen** (één bestand per stap; maak per terugkerend patroon één klasse in `style.css` en gebruik de tokens in plaats van hexkleuren)

- [ ] **7.13** `docent/exam_comparison.php` (14 stijlen, waaronder `#e3f2fd`, `#2196f3`, `#1565c0`, `#fff3cd`, `#ffc107`).
- [ ] **7.14** `student/view_results.php` (10).
- [ ] **7.15** `auth/register.php` (8), in de kaartopbouw van 5.5.
- [ ] **7.16** `docent/question_design_rubric.php` (4) en `docent/audit_log.php` (4).
- [ ] **7.17** De rest: `questions.php`, `prompt_help.php`, `change_password.php`, `dashboard.php` en wat `grep -rn 'style="' app/views` nog vindt. Alleen een echte uitzondering (bijvoorbeeld een breedte die uit PHP komt) mag blijven.

**Breadcrumbs en escaping**

- [ ] **7.18** Breadcrumbs toevoegen aan `docent/dashboard.php` (`['Toetsen' => null]`), `student/dashboard.php` (`['Dashboard' => null]`), `auth/change_password.php`, `auth/register.php` en `pages/privacy.php`.
- [ ] **7.19** Vervang in elk bestand dat je in deze fase aanraakt `htmlspecialchars(...)` door `e(...)` (lijst: `grep -rc htmlspecialchars app/views | grep -v ':0'`). Raak geen bestanden aan die je verder niet wijzigt.
- [ ] **7.20 Opruimen in `style.css`:** verwijder `.navbar-custom`, `.badge-status-*`, de oude aliassen uit 1.1 en de oude breadcrumbstijl als die niet meer gebruikt worden (`grep -rn` per klasse).
  *Klaar als:* `grep -rnP '[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]' app/views` niets meer vindt, `grep -rn 'style="' app/views` alleen bewuste uitzonderingen geeft en `grep -rn '#[0-9a-fA-F]\{3,6\}' app/views` leeg is. Commit: `Clean up components: icons, soft badges, no inline styles`.

## Fase 8: Documentatie

- [ ] **8.1 `CLAUDE.md`:** contract 5 noemt nu "de navigatie in `layouts/main.php`"; maak daar `navItems()` in `app/helpers/navigation.php` van. Voeg onder *Architectuur in het kort* toe dat `/` de landingspagina is en dat de layout drie standen heeft (`staff`, `simple`, `bare`). Voeg aan *Verplichte patronen* toe: kleuren alleen via de tokens in `style.css`, iconen via Bootstrap Icons met `aria-hidden` en een `aria-label` op de knop.
- [ ] **8.2 `ARCHITECTURE.md`:** de action `home`, de helper `navigation.php` (inclusief `navCounter()` en de query in `StudentExam::pendingReview*()`), de partials in `layouts/partials/`, de stylesheet van Bootstrap Icons als nieuwe externe bron, en de tokens.
- [ ] **8.3 `MANUAL.md`:** §3 (de landingspagina, inloggen via de knop), §5 (de zijbalk, de teller bij Beoordelen, het gebruikersmenu, "Mijn testpogingen") en de AI-workerstatus in de topbalk.
- [ ] **8.4 `docs/security-issues.txt`:** de openbare landingspagina (geen sessie- of databasegegevens, geen formulier), de nieuwe externe stylesheet met SRI binnen de bestaande CSP, en dat de teller dezelfde autorisatie volgt als `pending_assessments` (docent alleen eigen toetsen).
- [ ] **8.5 `README.md`:** werk een eventuele beschrijving of schermafbeelding van de interface bij.

## Fase 9: Afronding

- [ ] **9.1** PHP-syntaxcheck slaagt (zie CLAUDE.md). De Python-syntaxcheck en de drie mocktestsuites slagen nog (de worker is niet gewijzigd, maar de merge-checklist vraagt het).
- [ ] **9.2 Rooktest met een nieuwe database** (`docker compose down -v`), op desktop **en** op 375px:

  | Wie | Controleer |
  |---|---|
  | Uitgelogd | landingspagina, knoppen naar login, privacy, contactlink; geen workerstatus |
  | Admin | alle menugroepen, actieve staat op onderliggende pagina's, teller, workerstatus, gebruikersmenu, uitloggen → landingspagina |
  | Docent | geen beheer-items behalve Audit log; teller telt alleen eigen toetsen; dashboard-dropdown werkt incl. bevestiging |
  | Beoordelaar | alleen Beoordelen, met teller; geen workerstatus |
  | Student | eenvoudige topnavigatie, toets maken, resultaten bekijken |
  | Gast | gastlink starten, toets maken |
  | Deelnemer via startlink | startlink, toets maken, terugkeer-URL werkt nog |
  | Wachtwoord moet gewijzigd | geen menu-items, alleen Uitloggen |

- [ ] **9.3 Rooktest met een bestaande database:** de app werkt met de data van vóór deze branch (er is geen schemawijziging, dus dit is een korte controle).
- [ ] **9.4** Controleer voor een muterende actie die je verplaatst hebt (bijvoorbeeld verwijderen in de dashboard-dropdown) dat een GET nog steeds 405 geeft.
- [ ] **9.5** Open de console van de browser op elke pagina uit 9.2: geen CSP-meldingen.
- [ ] **9.6** Vergelijk met de "voor"-screenshots uit 0.4 en zet een paar "na"-screenshots in de PR-beschrijving.
- [ ] **9.7** Loop de [merge-checklist in CLAUDE.md](CLAUDE.md#checklist-voor-een-merge-naar-main) na.
- [ ] **9.8** De PR-beschrijving vermeldt dat alleen de webapp wijzigt: geen schema, geen worker, geen integratie-API, dus geen gecoördineerde uitrol en niets aan te passen op de workermachine.

---

## Later (buiten deze branch)

- Dark mode via `data-bs-theme="dark"` en een tweede set tokens.
- Een inklapbare zijbalk (alleen iconen), met de keuze bewaard in `localStorage`.
- Het logo als SVG voor een scherpe weergave in de zijbalk en op de landingspagina.
- Uitloggen als POST met CSRF-token in plaats van een GET-link. Let op: bij een verlopen sessie geeft `validateCsrfToken()` dan 403; `logout()` moet een uitgelogde gebruiker eerst gewoon doorsturen.
- Een tweede statusbolletje voor de assessment-worker (`ASSESSMENT_WORKER_STALE_SECONDS` bestaat al).
