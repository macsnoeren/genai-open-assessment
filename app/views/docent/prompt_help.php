<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start();
?>

<h2>Hulp bij Prompts</h2>
<p>Bij het schrijven van een prompt voor de AI kun je gebruik maken van speciale variabelen. Deze variabelen worden automatisch vervangen door de echte gegevens van de toetsvraag en het antwoord van de student op het moment dat de AI wordt aangeroepen.</p>

<div class="card mb-4">
    <div class="card-header bg-light fw-bold">Beschikbare Variabelen</div>
    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Variabele</th>
                    <th>Beschrijving</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-monospace">{{question_text}}</td>
                    <td>De tekst van de vraag die aan de student is gesteld.</td>
                </tr>
                <tr>
                    <td class="font-monospace">{{criteria}}</td>
                    <td>De beoordelingscriteria die de docent heeft ingevuld bij de vraag.</td>
                </tr>
                <tr>
                    <td class="font-monospace">{{student_answer}}</td>
                    <td>Het antwoord dat de student heeft gegeven.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info">
    <strong>Tip:</strong> Zorg ervoor dat je de AI instrueert om de output in een specifiek JSON formaat te geven, zodat het systeem de score en feedback correct kan verwerken.
</div>

<div class="card mb-4">
    <div class="card-header bg-light fw-bold">Voorbeeld Prompt</div>
    <div class="card-body">
        <p>Hieronder staat een voorbeeld van een effectieve prompt. Let op het gebruik van de variabelen en de strikte instructie voor JSON output.</p>
        <pre class="bg-light p-3 border rounded" style="white-space: pre-wrap;">Negeer alle eerdere context.

Je bent een automatisch beoordelingssysteem.
Je mag GEEN uitleg, analyse of extra tekst geven.

TAKEN:
- Beoordeel het antwoord van de student.
- Ken punten toe: 0, 1, 5 of 10.
- 10 punten wanneer het juiste antwoord wordt gegeven.
- 5 punten als het antwoord in de buurt komt.
- 1 punt als er enigzins iets zinnigs in staat.
- Geef korte feedback aan de student in de je-vorm.
- Geef een korte uitleg wat beter kan in de je-vorm.

GESTELDE VRAAG AAN STUDENT:
{{question_text}}

HET JUISTE ANTWOORD EN CRITERIA:
{{criteria}}

REGELS:
- Geef ALLEEN de onderstaande output.
- Gebruik exact deze labels.
- Voeg niets toe.
- Gebruik maximaal 4 zinnen feedback.

OUTPUTFORMAAT JSON exact (verplicht):
{ 
    "score": <0-10>,
    "feedback": "<tekst>",
    "uitleg": "<tekst>"
}

STUDENTANTWOORD:
{{student_answer}}</pre>
    </div>
</div>

<a href="/?action=prompts" class="btn btn-primary">Terug naar Prompts</a>

<?php
$content = ob_get_clean();
$title = "Hulp bij Prompts";
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Prompts' => '/?action=prompts',
    'Hulp' => ''
];
require __DIR__ . '/../layouts/main.php';
?>