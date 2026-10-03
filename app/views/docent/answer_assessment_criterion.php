<?php
/**
 * Copyright (C) 2025 JMNL Innovation.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Eén criterium van een agentic beoordeling (zonder layout).
 *
 * Verwacht:
 * - $criterion    rubriccriterium {nr, name, weight, description}
 * - $evidenceItem uitvoer van de Evidence Agent voor dit criterium (of null)
 * - $assessItem   uitvoer van de Assessment Agent voor dit criterium in deze ronde
 * - $finalStatus  status volgens de Validation Agent in deze ronde
 * - $decisionItem beslissing van de orchestrator voor dit criterium (alleen bij de laatste ronde, anders null)
 * - $corrections  correcties van de Validation Agent voor dit criterium in deze ronde
 * - $answerText   het studentantwoord (snapshot) voor de citaatcontrole
 */

$isConflict = $decisionItem && $decisionItem['agreement'] === 'conflict';
$symbolClass = ['voldaan' => 'text-success', 'deels' => 'text-warning', 'niet' => 'text-danger'][$finalStatus] ?? 'text-muted';
$agreementClass = ['eens' => 'bg-success', 'klein_verschil' => 'bg-info text-dark', 'conflict' => 'bg-warning text-dark'];
?>
<div class="card mb-3 <?= $isConflict ? 'border-warning border-2' : '' ?>">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <span class="fs-5 fw-bold <?= e($symbolClass) ?>" aria-hidden="true"><?= e(AnswerAssessment::statusSymbol($finalStatus)) ?></span>
        <span class="fw-semibold"><?= (int)$criterion['nr'] ?>. <?= e($criterion['name']) ?></span>
        <span class="badge <?= $criterion['weight'] === 'essentieel' ? 'bg-dark' : 'bg-light text-dark border' ?>"><?= e($criterion['weight']) ?></span>
        <span class="small text-muted">
            <?= e(AnswerAssessment::criterionStatusLabel($finalStatus)) ?>
            <?php if ($evidenceItem): ?> · <?= e(AnswerAssessment::evidenceLabel($evidenceItem['evidence_found'])) ?><?php endif; ?>
        </span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-2"><?= e($criterion['description']) ?></p>

        <div class="small mb-3">
            <?php if ($evidenceItem): ?>Evidence: <strong><?= e($evidenceItem['evidence_found']) ?></strong> · <?php endif; ?>
            Assessment: <strong><?= e($assessItem['status']) ?></strong> ·
            Validation: <strong><?= e($finalStatus) ?></strong>
            <?php if ($decisionItem): ?>
                <span class="badge <?= e($agreementClass[$decisionItem['agreement']] ?? 'bg-secondary') ?> ms-2"><?= e(AnswerAssessment::agreementLabel($decisionItem['agreement'])) ?></span>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="border rounded p-2 h-100 bg-light">
                    <h6 class="small text-uppercase text-muted">Bewijs (letterlijk uit het antwoord)</h6>
                    <?php $quotes = $evidenceItem['evidence'] ?? []; ?>
                    <?php if (!$quotes): ?>
                        <p class="small text-muted mb-0">Geen citaat gevonden.</p>
                    <?php endif; ?>
                    <?php foreach ($quotes as $quote): ?>
                        <blockquote class="border-start border-3 ps-2 mb-2 small fst-italic">
                            &ldquo;<?= e($quote) ?>&rdquo;
                            <?php if (!AnswerAssessment::quoteFound($answerText, $quote)): ?>
                                <span class="badge bg-danger fst-normal">niet letterlijk gevonden</span>
                            <?php endif; ?>
                        </blockquote>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="border rounded p-2 h-100">
                    <h6 class="small text-uppercase text-muted">Interpretatie</h6>
                    <?php if ($evidenceItem && $evidenceItem['interpretation'] !== ''): ?>
                        <p class="small mb-2"><?= e($evidenceItem['interpretation']) ?></p>
                    <?php endif; ?>
                    <?php if ($evidenceItem && $evidenceItem['missing_evidence'] !== ''): ?>
                        <p class="small mb-2"><span class="fw-semibold">Ontbreekt:</span> <?= e($evidenceItem['missing_evidence']) ?></p>
                    <?php endif; ?>
                    <?php if ($evidenceItem): ?>
                        <span class="badge <?= e(AnswerAssessment::confidenceClass($evidenceItem['confidence'])) ?>">confidence <?= e($evidenceItem['confidence']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="border rounded p-2 h-100">
                    <h6 class="small text-uppercase text-muted">Beoordeling</h6>
                    <?php if ($assessItem['assessment'] !== ''): ?>
                        <p class="small fw-semibold mb-1"><?= e($assessItem['assessment']) ?></p>
                    <?php endif; ?>
                    <?php if ($assessItem['reasoning'] !== ''): ?>
                        <p class="small mb-2"><?= e($assessItem['reasoning']) ?></p>
                    <?php endif; ?>
                    <?php foreach ($assessItem['evidence_used'] as $quote): ?>
                        <div class="small text-muted fst-italic">
                            &ldquo;<?= e($quote) ?>&rdquo;
                            <?php if (!AnswerAssessment::quoteFound($answerText, $quote)): ?>
                                <span class="badge bg-danger fst-normal">niet letterlijk gevonden</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <span class="badge <?= e(AnswerAssessment::confidenceClass($assessItem['confidence'])) ?> mt-1">confidence <?= e($assessItem['confidence']) ?></span>
                    <?php foreach ($corrections as $correction): ?>
                        <div class="alert alert-warning small py-1 px-2 mt-2 mb-0">
                            Validation: <?= e($correction['from']) ?> &rarr; <?= e($correction['to']) ?><?php if ($correction['why'] !== ''): ?>: <?= e($correction['why']) ?><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
