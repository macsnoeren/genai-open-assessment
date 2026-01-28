<?php
ob_start();
?>

<h2>Student antwoorden</h2>

<?php foreach ($answers as $a): ?>
<div class="card">
  <p><strong>Vraag:</strong> <?= htmlspecialchars($a['question_text']) ?></p>
  <p><strong>Student antwoord:</strong> <?= nl2br(htmlspecialchars($a['answer'])) ?></p>
  <p><strong>Model antwoord:</strong> <?= nl2br(htmlspecialchars($a['model_answer'])) ?></p>
  <p><strong>Criteria:</strong> <?= nl2br(htmlspecialchars($a['criteria'])) ?></p>
  <?php if ($a['ai_feedback']): ?>
  <p><strong>AI feedback:</strong><br>
    <?= nl2br(htmlspecialchars($a['ai_feedback'])) ?>
  </p>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php
 $content = ob_get_clean();
 $title = "Student antwoorden";
 require __DIR__ . '/../layouts/main.php';
?>
