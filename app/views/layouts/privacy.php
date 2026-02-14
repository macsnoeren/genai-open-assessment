<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 */
ob_start();
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="mb-4">Privacy- en Cookieverklaring</h1>
            
            <div class="alert alert-info">
                <strong>Let op bij het invullen van toetsen:</strong><br>
                Vul in de open antwoorden <u>geen</u> direct persoonlijk identificeerbare informatie in (zoals BSN, telefoonnummers of volledige adressen). 
                Voor deelname als gast is het invullen van uw <strong>voornaam en de eerste letter van uw achternaam</strong> voldoende om uw resultaten terug te kunnen vinden.
            </div>

            <h3>1. Doelstelling van dit project</h3>
            <p>
                De applicatie <strong>GenAI Open Assessment</strong> is ontwikkeld met als doel onderzoek te doen naar de inzet van Generatieve AI binnen het onderwijs. 
                Specifiek onderzoeken we hoe AI kan ondersteunen bij het geven van feedback op open kennisvragen. De gegevens die in dit systeem worden verwerkt, 
                worden gebruikt om de kwaliteit van AI-feedback te analyseren en de onderwijspraktijk te verbeteren.
            </p>

            <h3>2. Welke gegevens verwerken wij?</h3>
            <p>Wij verwerken zo min mogelijk persoonsgegevens. Afhankelijk van uw rol verwerken wij:</p>
            <ul>
                <li><strong>Als Student (met account):</strong> Naam, e-mailadres, versleuteld wachtwoord en uw gegeven antwoorden op toetsvragen.</li>
                <li><strong>Als Gast (zonder account):</strong> De door u opgegeven naam (nickname) en uw gegeven antwoorden.</li>
                <li><strong>Als Docent/Beoordelaar:</strong> Naam, e-mailadres en versleuteld wachtwoord.</li>
                <li><strong>Technische gegevens:</strong> IP-adres (voor beveiliging en audit-logging) en tijdstippen van inloggen/inleveren.</li>
            </ul>

            <h3>3. Gebruik van Cookies</h3>
            <p>
                Deze applicatie maakt gebruik van functionele cookies die noodzakelijk zijn voor de werking van de site. 
                Wij gebruiken <u>geen</u> tracking cookies of cookies van derde partijen voor advertentiedoeleinden.
            </p>
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Naam</th>
                        <th>Doel</th>
                        <th>Bewaartermijn</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>PHPSESSID</code></td>
                        <td>Houdt uw sessie in stand nadat u bent ingelogd.</td>
                        <td>Tot sluiten browser</td>
                    </tr>
                    <tr>
                        <td><code>guest_access_token</code></td>
                        <td>Zorgt ervoor dat u als gaststudent later terug kunt keren naar uw toets om resultaten te bekijken.</td>
                        <td>30 dagen</td>
                    </tr>
                    <tr>
                        <td><code>cookie_consent</code></td>
                        <td>Onthoudt dat u deze melding heeft gesloten.</td>
                        <td>1 jaar</td>
                    </tr>
                </tbody>
            </table>

            <h3>4. AI Verwerking</h3>
            <p>
                De antwoorden die u geeft, worden verwerkt door een lokaal of extern AI-model om feedback te genereren. 
                De prompts die naar de AI worden gestuurd bevatten uw antwoord, maar worden waar mogelijk geanonimiseerd verstuurd.
            </p>

            <h3>5. Uw Rechten</h3>
            <p>
                U heeft het recht om uw gegevens in te zien, te corrigeren of te laten verwijderen. 
                Neem hiervoor contact op met de docent of beheerder die verantwoordelijk is voor deze toetsafname.
            </p>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Privacy & Cookies";
require __DIR__ . '/../layouts/main.php';
?>