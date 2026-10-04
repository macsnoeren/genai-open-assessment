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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Student antwoorden</h2>
    <?php if (empty($studentExam['student_id']) && !empty($canEdit)): ?>
        <form action="/?action=update_guest_name" method="POST" class="d-flex align-items-center">
            <?= csrfInput() ?>
            <input type="hidden" name="student_exam_id" value="<?= (int)$studentExam['id'] ?>">
            <div class="input-group">
                <span class="input-group-text bg-warning text-dark border-warning">Gast</span>
                <input type="text" name="guest_name" class="form-control border-warning" value="<?= e($studentExam['guest_name'] ?? '') ?>" required>
                <button type="submit" class="btn btn-outline-warning text-dark">Wijzigen</button>
            </div>
        </form>
    <?php elseif (empty($studentExam['student_id'])): ?>
        <h4 class="text-muted mb-0"><span class="badge badge-soft-warning">Gast</span> <?= e($studentExam['guest_name'] ?? 'Gast') ?></h4>
    <?php elseif (isset($studentExam['name'])): ?>
        <h4 class="text-muted mb-0"><?= e($studentExam['name']) ?></h4>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (!empty($integrationAttempt)): ?>
    <div class="alert alert-info mb-4">
        Deze poging is gestart via de koppeling <strong><?= e($integrationAttempt['integration_name']) ?></strong>
        (ref <code><?= e($integrationAttempt['external_ref']) ?></code>). De externe website haalt het resultaat op;
        een docentscore die je hier geeft, ziet die website ook.
    </div>
<?php endif; ?>

<?php if (isset($shareableLink) && $shareableLink): ?>
    <div class="alert alert-info mb-4">
        <label class="form-label"><strong>Deelbare link voor de student:</strong></label>
        <div class="input-group mb-1">
            <input type="text" id="shareableLink" class="form-control" value="<?= e($shareableLink) ?>" readonly data-select-on-click>
            <button class="btn btn-outline-primary" type="button" data-copy-target="shareableLink" data-copy-feedback="Link gekopieerd naar klembord!">Kopieer link</button>
        </div>
        <small>De student kan deze link gebruiken om zijn resultaten te bekijken, ook als hij zijn sessie/cookie is kwijtgeraakt.</small>
    </div>
<?php endif; ?>

<?php $isLevels = $gradingScale === Grading::SCALE_LEVELS; ?>
<?php if ($isLevels): ?>
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white"><strong>Eindcijfer</strong></div>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <?php if ($attemptResult['computed'] !== null): ?>
                <span class="fs-3 fw-bold"><?= e(Grading::formatGrade($attemptResult['computed'])) ?></span>
                <?php if ($attemptResult['show_label']): ?>
                    <?php $computedLabel = Grading::gradeLabel($attemptResult['computed']); ?>
                    <span class="badge badge-soft-<?= e(Grading::levelClass($computedLabel)) ?> fs-6"><?= e(Grading::levelLabel($computedLabel)) ?></span>
                <?php endif; ?>
                <span class="text-muted small">berekend uit de docentniveaus</span>
            <?php else: ?>
                <span class="fs-5"><?= (int)$attemptResult['graded'] ?> van <?= (int)$attemptResult['total'] ?> beoordeeld</span>
                <span class="text-muted small">Het eindcijfer verschijnt als elk antwoord een docentniveau heeft.</span>
            <?php endif; ?>
        </div>
        <?php if ($attemptResult['scheme']): ?>
        <div class="small text-muted mt-2">
            Puntenschema: <?= e($attemptResult['scheme']['name']) ?>
            (onvoldoende <?= (int)$attemptResult['scheme']['points_onvoldoende'] ?>, voldoende <?= (int)$attemptResult['scheme']['points_voldoende'] ?>,
            goed <?= (int)$attemptResult['scheme']['points_goed'] ?>, uitstekend <?= (int)$attemptResult['scheme']['points_uitstekend'] ?>).
            Eindcijfer = 10 × punten / (<?= (int)$attemptResult['total'] ?> × <?= (int)$attemptResult['scheme']['points_uitstekend'] ?>).
        </div>
        <?php endif; ?>
        <?php if (!empty($aiGrades)): ?>
        <div class="mt-3">
            <strong>AI-cijfers</strong> <span class="badge badge-soft-info">AI</span> <small class="text-muted">alleen ter vergelijking</small>
            <ul class="mb-0 mt-1">
            <?php foreach ($aiGrades as $source => $aiGrade): ?>
                <li><strong><?= e($source) ?>:</strong> <?= e(Grading::formatGrade($aiGrade['grade'])) ?>
                    <?php if ($aiGrade['count'] < $attemptResult['total']): ?>
                        <small class="text-muted">(over <?= (int)$aiGrade['count'] ?> van <?= (int)$attemptResult['total'] ?> antwoorden)</small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!$isLevels && isset($finalScore) && $finalScore !== null): ?>
<div class="alert alert-primary">
    <strong>Eindscore docent (gemiddelde van de docentscores):</strong> <?= number_format($finalScore, 1) ?>
</div>
<?php endif; ?>

<?php if (!$isLevels && !empty($finalAiScores)): ?>
<div class="alert alert-info">
    <strong>AI-scores (gemiddelde, alleen ter vergelijking):</strong>
    <ul class="mb-0 mt-1">
    <?php foreach ($finalAiScores as $model => $score): ?>
        <li><strong><?= e($model) ?>:</strong> <?= number_format($score, 1) ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php
$overrideRedirect = 'view_student_answers';
$overrideShowComputed = false;
require __DIR__ . '/final_grade_override.php';
?>

<?php $levelsAiOff = $isLevels && !LEVELS_AI_ENABLED; ?>
<?php $canAssess = !empty($exam['ai_grading_enabled']) && !empty($studentExam['completed_at']) && !$levelsAiOff; ?>
<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1">
            <strong>Agentic beoordelen</strong>
            <div class="small text-muted">
            <?php if (empty($exam['ai_grading_enabled'])): ?>
                AI-beoordeling staat uit voor deze toets, dus agentic beoordelen kan hier niet. Zet AI-beoordeling aan bij de instellingen van de toets.
            <?php elseif (empty($studentExam['completed_at'])): ?>
                Deze toetspoging is nog niet ingeleverd.
            <?php elseif ($levelsAiOff): ?>
                AI-beoordeling met niveaus staat nog uit op deze server. Beoordeel deze toets zelf per niveau.
            <?php else: ?>
                AI-agents zoeken per rubriccriterium bewijs in het antwoord, beoordelen en controleren elkaar. Dat levert een AI-beoordeling op, net als de AI-feedback; jouw eigen beoordeling staat daar los van.
            <?php endif; ?>
            </div>
        </div>
        <?php if ($canAssess): ?>
        <form action="/?action=answer_assessment_start_exam" method="post" class="mb-0">
            <?= csrfInput() ?>
            <input type="hidden" name="student_exam_id" value="<?= (int)$studentExam['id'] ?>">
            <button type="submit" class="btn btn-outline-primary btn-sm"
                    data-confirm="Alle antwoorden van deze poging agentic laten beoordelen? Antwoorden die al een beoordeling hebben, worden overgeslagen.">Alle antwoorden agentic beoordelen</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($canEdit)): ?>
<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1">
            <strong>AI-resultaten</strong>
            <div class="small text-muted">
            <?php if (!empty($canResetAi)): ?>
                Verwijdert de AI-feedback en de agentic beoordelingen van deze poging en laat de AI opnieuw beoordelen. Je eigen docentbeoordeling blijft staan.
            <?php else: ?>
                <?= e($aiResetReason ?? '') ?>
            <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($canResetAi)): ?>
        <form action="/?action=ai_results_reset_attempt" method="post" class="mb-0">
            <?= csrfInput() ?>
            <input type="hidden" name="student_exam_id" value="<?= (int)$studentExam['id'] ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    data-confirm="Alle AI-resultaten van deze poging verwijderen en opnieuw laten uitvoeren? Je docentbeoordeling blijft staan.">Alle AI-resultaten opnieuw laten uitvoeren</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php foreach ($answers as $a): ?>
<div class="card mb-4" id="answer-<?= (int)$a['id'] ?>">
  <div class="card-header bg-light">
      <strong>Vraag:</strong> <?= e($a['question_text']) ?>
  </div>
  <div class="card-body">
      <div class="mb-3">
          <h6 class="text-muted">Student antwoord:</h6>
          <div class="p-3 bg-white border rounded"><?= nl2br(e($a['answer'])) ?></div>
      </div>
      
      <div class="mb-3">
              <small class="text-muted d-block">Criteria:</small>
              <div class="small text-secondary"><?= nl2br(e($a['criteria'])) ?></div>
      </div>

      <?php if ($isLevels && !empty($a['ai_levels'])): ?>
      <div class="mb-2 d-flex flex-wrap align-items-center gap-1">
          <span class="small text-muted me-1">AI-niveaus:</span>
          <?php foreach ($a['ai_levels'] as $source => $aiLevel): ?>
              <span class="badge badge-soft-<?= e(Grading::levelClass($aiLevel)) ?>"><?= e($source) ?>: <?= e(Grading::levelLabel($aiLevel)) ?></span>
          <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($a['ai_feedback']): ?>
      <div class="alert alert-info">
          <strong>AI-feedback</strong> <span class="badge badge-soft-info">AI</span><br>
          <?= nl2br(e($a['ai_feedback'])) ?>
      </div>
      <?php endif; ?>

      <?php $run = $assessmentRuns[(int)$a['id']] ?? null; ?>
      <?php $agentic = AnswerAssessment::studentSummary($run); ?>
      <?php if ($run || $canAssess): ?>
      <div class="<?= $agentic ? 'alert alert-info' : 'mb-3' ?>">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <strong class="me-1">Agentic AI-beoordeling</strong> <span class="badge badge-soft-info">AI</span>
          <?php if ($run): ?>
              <span class="badge <?= e(AnswerAssessment::statusClass($run['status'])) ?>"><?= e(AnswerAssessment::statusLabel($run['status'])) ?></span>
              <?php if ($agentic): ?>
                  <?php if (isset($agentic['level'])): ?>
                  <span class="fw-semibold">AI-niveau: <?= e(Grading::levelLabel($agentic['level'])) ?></span>
                  <?php else: ?>
                  <span class="fw-semibold">AI-score: <?= (int)$agentic['score'] ?></span>
                  <?php endif; ?>
                  <?php if ((int)$run['human_review_needed'] === 1): ?>
                      <span class="badge badge-soft-warning">AI onzeker: menselijke controle nodig</span>
                  <?php endif; ?>
              <?php endif; ?>
              <a href="/?action=answer_assessment_view&id=<?= (int)$run['id'] ?>" class="btn btn-sm btn-outline-secondary">Details</a>
          <?php else: ?>
              <form action="/?action=answer_assessment_start" method="post" class="mb-0">
                  <?= csrfInput() ?>
                  <input type="hidden" name="student_answer_id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-primary"
                          data-confirm="Dit antwoord agentic laten beoordelen door de AI-agents?">Agentic beoordelen</button>
              </form>
          <?php endif; ?>
        </div>
        <?php if ($agentic && $agentic['feedback'] !== ''): ?>
            <div class="mt-1"><?= nl2br(e($agentic['feedback'])) ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($canResetAi) && (!empty($a['ai_feedback']) || $run !== null)): ?>
      <form action="/?action=ai_results_reset_answer" method="post" class="mb-0">
          <?= csrfInput() ?>
          <input type="hidden" name="student_answer_id" value="<?= (int)$a['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"
                  data-confirm="De AI-feedback en de agentic beoordeling van dit antwoord verwijderen en opnieuw laten uitvoeren?">AI opnieuw laten beoordelen</button>
      </form>
      <?php endif; ?>

      <div class="mt-3 pt-3 border-top">
          <div class="d-flex justify-content-between align-items-start">
              <div>
                  <strong>Docentbeoordeling</strong> <span class="badge badge-soft-primary">mens</span><br>
                  <?php if (!empty($a['teacher_feedback'])): ?>
                      <?= nl2br(e($a['teacher_feedback'])) ?>
                  <?php else: ?>
                      <em class="text-muted">Nog geen feedback gegeven.</em>
                  <?php endif; ?>
              </div>
              <div class="text-end">
                  <?php if (Grading::examScale($exam) === Grading::SCALE_LEVELS): ?>
                      <?php if (!empty($a['teacher_level'])): ?>
                          <span class="badge badge-soft-<?= e(Grading::levelClass($a['teacher_level'])) ?> fs-6">Docentniveau: <?= e(Grading::levelLabel($a['teacher_level'])) ?></span>
                      <?php else: ?>
                          <span class="badge badge-soft-secondary fs-6">Docentniveau: -</span>
                      <?php endif; ?>
                  <?php else: ?>
                  <span class="badge badge-soft-primary fs-6">Docentscore: <?= isset($a['teacher_score']) ? e($a['teacher_score']) : '-' ?></span>
                  <?php endif; ?>
              </div>
          </div>
      </div>
  </div>
</div>
<?php endforeach; ?>

<?php
 $content = ob_get_clean();
 $title = "Student antwoorden";
 $breadcrumbs = [
    'Dashboard' => '/?action=docent_dashboard',
    'Resultaten' => '/?action=exam_results&exam_id=' . $studentExam['exam_id'],
    'Student antwoorden' => ''
 ];
 require __DIR__ . '/../layouts/main.php';
?>
