<?php
ob_start();
?>

<h2>Toets: <?= htmlspecialchars($studentExam['unique_id']) ?></h2>

<form method="POST" action="/?action=submit_exam">
  <input type="hidden" name="student_exam_id" value="<?= $studentExam['id'] ?>">
  
  <?php foreach ($questions as $q): ?>
  <div style="margin-bottom:20px;">
    <label><strong>Vraag:</strong> <?= htmlspecialchars($q['question_text']) ?></label><br>
    <textarea name="answers[<?= $q['id'] ?>]" required></textarea>
  </div>
  <?php endforeach; ?>
  
  <button type="submit">Toets inleveren</button>
</form>

<?php
$content = ob_get_clean();
$title = "Toets maken";
require __DIR__ . '/../layouts/main.php';
?>
