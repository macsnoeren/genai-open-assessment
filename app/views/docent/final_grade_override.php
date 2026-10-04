<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/*
 * Gedeelde partial: het eindcijfer handmatig aanpassen (B8), in de docentweergave
 * (student_answers.php) en de blinde beoordeling (grade_exam.php).
 * Verwacht: $attemptResult (Grading::attemptResult()), $studentExam, $overrideRedirect
 * (view_student_answers of grade_student_exam) en optioneel $overrideShowComputed.
 */
$r = $attemptResult;
$useLabel = $r['show_label'];
$formatValue = function ($value): string {
    if ($value === null) {
        return 'nog geen cijfer';
    }
    return is_string($value) ? Grading::levelLabel($value) : Grading::formatGrade((float)$value);
};
?>
<div class="card mb-4" id="final-grade">
    <div class="card-header bg-light"><strong>Eindcijfer handmatig aanpassen</strong></div>
    <div class="card-body">
        <?php if (!empty($overrideShowComputed)): ?>
            <p class="mb-2">
                Berekend eindcijfer:
                <?php if ($r['computed'] !== null): ?>
                    <strong><?= e(Grading::formatGrade($r['computed'])) ?></strong>
                    <?php if ($useLabel): ?>(<?= e(Grading::levelLabel(Grading::gradeLabel($r['computed']))) ?>)<?php endif; ?>
                <?php else: ?>
                    <span class="text-muted"><?= (int)$r['graded'] ?> van <?= (int)$r['total'] ?> beoordeeld</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if (empty($studentExam['completed_at'])): ?>
            <p class="text-muted mb-0">Deze toetspoging is nog niet ingeleverd.</p>
        <?php else: ?>

        <?php if ($r['override'] !== null): ?>
            <?php if ($r['override_outdated']): ?>
            <div class="alert alert-warning">
                Het berekende cijfer is gewijzigd sinds de aanpassing (toen <?= e($formatValue($r['override_basis'])) ?>, nu <?= e($formatValue($r['computed'])) ?>).
                De aanpassing blijft staan.
            </div>
            <?php endif; ?>
            <div class="border rounded p-3 mb-3 bg-light">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        Aangepast eindcijfer: <strong class="fs-5"><?= e($formatValue($r['override'])) ?></strong>
                        <?php if (!is_string($r['override']) && $r['label'] !== null): ?>(<?= e(Grading::levelLabel($r['label'])) ?>)<?php endif; ?>
                        <div class="small text-muted">
                            Door <?= e($r['override_by_name'] ?? 'onbekend') ?> op <?= e($r['override_at'] ?? '') ?>.
                            Berekend cijfer op dat moment: <?= e($formatValue($r['override_basis'])) ?>.
                        </div>
                        <div class="small mt-1"><strong>Reden:</strong> <?= nl2br(e($r['override_reason'])) ?></div>
                    </div>
                    <form action="/?action=clear_final_grade_override" method="post" class="mb-0">
                        <?= csrfInput() ?>
                        <input type="hidden" name="student_exam_id" value="<?= (int)$studentExam['id'] ?>">
                        <input type="hidden" name="redirect_action" value="<?= e($overrideRedirect) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="De aanpassing verwijderen? Daarna geldt weer het berekende eindcijfer.">Aanpassing verwijderen</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <form action="/?action=override_final_grade" method="post" class="mb-0">
            <?= csrfInput() ?>
            <input type="hidden" name="student_exam_id" value="<?= (int)$studentExam['id'] ?>">
            <input type="hidden" name="redirect_action" value="<?= e($overrideRedirect) ?>">
            <div class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <?php if ($useLabel): ?>
                    <label class="form-label" for="overrideLabel">Eindbeoordeling</label>
                    <select name="grade_label" id="overrideLabel" class="form-select" required>
                        <?php foreach (Grading::LEVELS as $level): ?>
                            <option value="<?= e($level) ?>" <?= $r['label'] === $level ? 'selected' : '' ?>><?= e(Grading::levelLabel($level)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <label class="form-label" for="overrideGrade">Eindcijfer (0–10)</label>
                    <input type="number" name="grade" id="overrideGrade" class="form-control" min="0" max="10" step="0.1" required
                           value="<?= is_float($r['final']) ? e(number_format($r['final'], 1, '.', '')) : '' ?>">
                    <?php endif; ?>
                </div>
                <div class="col-sm-8">
                    <label class="form-label" for="overrideReason">Reden (verplicht)</label>
                    <input type="text" name="reason" id="overrideReason" class="form-control" maxlength="<?= (int)MAX_GRADE_OVERRIDE_REASON ?>" required>
                </div>
            </div>
            <div class="form-text mb-2">De student ziet alleen het uiteindelijke <?= $useLabel ? 'woord' : 'cijfer' ?>, niet de reden en niet dat het is aangepast.</div>
            <button type="submit" class="btn btn-outline-primary btn-sm"><?= $r['override'] !== null ? 'Aanpassing wijzigen' : 'Eindcijfer aanpassen' ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
