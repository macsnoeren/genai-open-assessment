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

<h2>Beoordelen (Blind)</h2>
<p>Hier beoordeel je de antwoorden zonder invloed van de AI-feedback.</p>

<?php foreach ($answers as $a): ?>
<div class="card mb-4" id="answer-<?= $a['id'] ?>">
  <div class="card-header bg-light">
      <strong>Vraag:</strong> <?= e($a['question_text']) ?>
  </div>
  <div class="card-body">
      <div class="mb-3">
          <h6 class="text-muted">Student antwoord:</h6>
          <div class="p-3 bg-white border rounded"><?= nl2br(e($a['answer'])) ?></div>
      </div>
      
      <hr class="my-4">
      
      <form method="POST" action="/?action=save_teacher_feedback">
      <?= csrfInput() ?>
      <input type="hidden" name="student_answer_id" value="<?= $a['id'] ?>">
      <input type="hidden" name="student_exam_id" value="<?= $studentExam['id'] ?>">
      <input type="hidden" name="redirect_action" value="grade_student_exam">
      
      <h5 class="mb-3">Docent Beoordeling</h5>
      <?php if ($gradingScale === Grading::SCALE_LEVELS): ?>
      <fieldset class="mb-3">
          <legend class="form-label fs-6">Niveau</legend>
          <?php foreach (array_merge(Grading::LEVELS, ['']) as $levelOption): ?>
          <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="teacher_level" value="<?= e($levelOption) ?>"
                     id="level-<?= (int)$a['id'] ?>-<?= e($levelOption ?: 'none') ?>"
                     <?= ($a['teacher_level'] ?? '') === $levelOption ? 'checked' : '' ?>>
              <label class="form-check-label" for="level-<?= (int)$a['id'] ?>-<?= e($levelOption ?: 'none') ?>">
                  <?= $levelOption === '' ? 'Nog niet beoordeeld' : e(Grading::levelLabel($levelOption)) ?>
              </label>
          </div>
          <?php endforeach; ?>
      </fieldset>
      <?php else: ?>
      <div class="mb-3">
          <label class="form-label">Score (0-10)</label>
          <input type="number" name="teacher_score" class="form-control" min="0" max="10" value="<?= e($a['teacher_score'] ?? '') ?>">
      </div>
      <?php endif; ?>
      <div class="mb-3">
          <label class="form-label">Feedback</label>
          <textarea name="teacher_feedback" class="form-control" placeholder="Schrijf hier uw feedback..." rows="3"><?= e($a['teacher_feedback'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Opslaan</button>
  </form>
  </div>
</div>
<?php endforeach; ?>

<?php
$overrideRedirect = 'grade_student_exam';
$overrideShowComputed = true;
require __DIR__ . '/final_grade_override.php';
?>

<?php
 $content = ob_get_clean();
 $title = "Beoordelen (Blind)";
 // Een beoordelaar komt uit de lijst Beoordelen en heeft geen toegang tot het dashboard.
 $breadcrumbs = ($_SESSION['role'] ?? '') === 'beoordelaar'
    ? ['Beoordelen' => '/?action=pending_assessments', 'Poging beoordelen' => '']
    : [
        'Dashboard' => '/?action=docent_dashboard',
        'Resultaten' => '/?action=exam_results&exam_id=' . $studentExam['exam_id'],
        'Beoordelen' => ''
    ];
 require __DIR__ . '/../layouts/main.php';
?>