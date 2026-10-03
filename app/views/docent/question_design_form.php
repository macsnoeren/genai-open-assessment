<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
ob_start(); ?>

<div class="row justify-content-center">
<div class="col-lg-10">

<h2 class="mb-3">Vraag ontwerpen met AI</h2>
<p class="text-muted">
    Voer een open vraag en het gewenste antwoord in. De AI analyseert ze, stelt je zo nodig een paar
    verduidelijkende vragen en maakt daarna een voorstel voor de beoordelingscriteria (rubric), dat een
    tweede AI-stap kritisch controleert. Jij beslist: je kunt bijsturen, alles aanpassen en pas na jouw
    goedkeuring komt de vraag in de toets.
</p>

<div class="card">
<div class="card-body">
<form action="/?action=question_design_store" method="post">
    <?= csrfInput() ?>
    <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">

    <div class="mb-3">
        <label class="form-label" for="designQuestion">Vraag</label>
        <textarea name="question_text" id="designQuestion" class="form-control" rows="3" required
                  maxlength="<?= (int)MAX_DESIGN_TEXT_LENGTH ?>"><?= e($old['question_text'] ?? '') ?></textarea>
    </div>

    <div class="mb-4">
        <label class="form-label" for="designAnswer">Gewenst antwoord</label>
        <div class="form-text mb-2">
            Het antwoord dat je van een goede student verwacht. De AI gebruikt het om te bepalen wat essentieel is.
        </div>
        <textarea name="model_answer" id="designAnswer" class="form-control" rows="6" required
                  maxlength="<?= (int)MAX_DESIGN_TEXT_LENGTH ?>"><?= e($old['model_answer'] ?? '') ?></textarea>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Ontwerp starten</button>
        <a href="/?action=questions&exam_id=<?= (int)$exam['id'] ?>" class="btn btn-outline-secondary">Annuleren</a>
    </div>
</form>
</div>
</div>

</div>
</div>

<?php
$content = ob_get_clean();
$title = 'Vraag ontwerpen met AI';
$breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Vragen: ' . $exam['title'] => '/?action=questions&exam_id=' . (int)$exam['id'],
    $title => ''
];
require __DIR__ . '/../layouts/main.php';
