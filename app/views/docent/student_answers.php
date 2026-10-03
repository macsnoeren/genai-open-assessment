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
                <input type="text" name="guest_name" class="form-control border-warning" value="<?= htmlspecialchars($studentExam['guest_name'] ?? '') ?>" required>
                <button type="submit" class="btn btn-outline-warning text-dark">Wijzigen</button>
            </div>
        </form>
    <?php elseif (empty($studentExam['student_id'])): ?>
        <h4 class="text-muted mb-0"><span class="badge bg-warning text-dark">Gast</span> <?= htmlspecialchars($studentExam['guest_name'] ?? 'Gast') ?></h4>
    <?php elseif (isset($studentExam['name'])): ?>
        <h4 class="text-muted mb-0"><?= htmlspecialchars($studentExam['name']) ?></h4>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($shareableLink) && $shareableLink): ?>
    <div class="alert alert-info mb-4">
        <label class="form-label"><strong>Deelbare link voor de student:</strong></label>
        <div class="input-group mb-1">
            <input type="text" id="shareableLink" class="form-control" value="<?= htmlspecialchars($shareableLink) ?>" readonly data-select-on-click>
            <button class="btn btn-outline-primary" type="button" data-copy-target="shareableLink" data-copy-feedback="Link gekopieerd naar klembord!">Kopieer link</button>
        </div>
        <small>De student kan deze link gebruiken om zijn resultaten te bekijken, ook als hij zijn sessie/cookie is kwijtgeraakt.</small>
    </div>
<?php endif; ?>

<?php if (isset($finalScore) && $finalScore !== null): ?>
<div class="alert alert-primary">
    <strong>Eindscore docent (gemiddelde van de docentscores):</strong> <?= number_format($finalScore, 1) ?>
</div>
<?php endif; ?>

<?php if (!empty($finalAiScores)): ?>
<div class="alert alert-info">
    <strong>AI-scores (gemiddelde, alleen ter vergelijking):</strong>
    <ul class="mb-0 mt-1">
    <?php foreach ($finalAiScores as $model => $score): ?>
        <li><strong><?= htmlspecialchars($model) ?>:</strong> <?= number_format($score, 1) ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php $canAssess = !empty($exam['ai_grading_enabled']) && !empty($studentExam['completed_at']); ?>
<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1">
            <strong>Agentic beoordelen</strong>
            <div class="small text-muted">
            <?php if (empty($exam['ai_grading_enabled'])): ?>
                AI-beoordeling staat uit voor deze toets, dus agentic beoordelen kan hier niet. Zet AI-beoordeling aan bij de instellingen van de toets.
            <?php elseif (empty($studentExam['completed_at'])): ?>
                Deze toetspoging is nog niet ingeleverd.
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

<?php foreach ($answers as $a): ?>
<div class="card mb-4" id="answer-<?= (int)$a['id'] ?>">
  <div class="card-header bg-light">
      <strong>Vraag:</strong> <?= htmlspecialchars($a['question_text']) ?>
  </div>
  <div class="card-body">
      <div class="mb-3">
          <h6 class="text-muted">Student antwoord:</h6>
          <div class="p-3 bg-white border rounded"><?= nl2br(htmlspecialchars($a['answer'])) ?></div>
      </div>
      
      <div class="mb-3">
              <small class="text-muted d-block">Criteria:</small>
              <div class="small text-secondary"><?= nl2br(htmlspecialchars($a['criteria'])) ?></div>
      </div>

      <?php if ($a['ai_feedback']): ?>
      <div class="alert alert-info">
          <strong>AI-feedback</strong> <span class="badge bg-info text-dark">AI</span><br>
          <?= nl2br(htmlspecialchars($a['ai_feedback'])) ?>
      </div>
      <?php endif; ?>

      <?php $run = $assessmentRuns[(int)$a['id']] ?? null; ?>
      <?php $agentic = AnswerAssessment::studentSummary($run); ?>
      <?php if ($run || $canAssess): ?>
      <div class="<?= $agentic ? 'alert alert-info' : 'mb-3' ?>">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <strong class="me-1">Agentic AI-beoordeling</strong> <span class="badge bg-info text-dark">AI</span>
          <?php if ($run): ?>
              <span class="badge <?= e(AnswerAssessment::statusClass($run['status'])) ?>"><?= e(AnswerAssessment::statusLabel($run['status'])) ?></span>
              <?php if ($agentic): ?>
                  <span class="fw-semibold">AI-score: <?= (int)$agentic['score'] ?></span>
                  <?php if ((int)$run['human_review_needed'] === 1): ?>
                      <span class="badge bg-warning text-dark">AI onzeker: menselijke controle nodig</span>
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

      <div class="mt-3 pt-3 border-top">
          <div class="d-flex justify-content-between align-items-start">
              <div>
                  <strong>Docentbeoordeling</strong> <span class="badge bg-primary">mens</span><br>
                  <?php if (!empty($a['teacher_feedback'])): ?>
                      <?= nl2br(htmlspecialchars($a['teacher_feedback'])) ?>
                  <?php else: ?>
                      <em class="text-muted">Nog geen feedback gegeven.</em>
                  <?php endif; ?>
              </div>
              <div class="text-end">
                  <span class="badge bg-primary fs-6">Docentscore: <?= isset($a['teacher_score']) ? htmlspecialchars($a['teacher_score']) : '-' ?></span>
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
