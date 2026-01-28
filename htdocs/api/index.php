<?php
require_once __DIR__ . '/../../config/database.php';

header("Content-Type: application/json");

$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? null;

if (!$apiKey || !validApiKey($apiKey)) {
  print_r($_GET);
print_r($_POST);

  http_response_code(401);
  echo json_encode(['error' => 'Invalid API key']);
  exit;
 }

$action = $_GET['action'] ?? null;

switch ($action) {
 case 'open_student_answers':
   getOpenStudentAnswers();
   break;
   
 case 'submit_ai_feedback':
   submitAiFeedback();
   break;
   
 default:
   http_response_code(404);
   echo json_encode(['error' => 'Unknown endpoint']);
 }

function validApiKey($key) {
  $pdo = Database::connect();
  $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE api_key = ? AND active = 1");
  $stmt->execute([$key]);
  return (bool)$stmt->fetch();
}

function getOpenStudentAnswers() {
  $pdo = Database::connect();
  
  $stmt = $pdo->prepare("
			SELECT
			sa.id AS student_answer_id,
			sa.answer,
			q.question_text,
			q.criteria,
			u.name AS student_name,
			e.title AS exam_title,
			se.unique_id AS student_exam_id
			FROM student_answers sa
			JOIN questions q ON sa.question_id = q.id
			JOIN student_exams se ON sa.student_exam_id = se.id
			JOIN users u ON se.student_id = u.id
			JOIN exams e ON se.exam_id = e.id
			WHERE sa.ai_feedback IS NULL
			");
  
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  echo json_encode([
		    'status' => 'ok',
		    'count' => count($rows),
		    'answers' => $rows
		    ]);
}

  function submitAiFeedback() {
    $input = json_decode(file_get_contents("php://input"), true);
    
    $answerId = $input['student_answer_id'] ?? null;
    $feedback = $input['ai_feedback'] ?? null;
    
    if (!$answerId || !$feedback) {
      http_response_code(400);
      echo json_encode(['error' => 'Missing parameters']);
      return;
    }
    
    $pdo = Database::connect();
    $stmt = $pdo->prepare("
			  UPDATE student_answers
			  SET ai_feedback = ?, ai_updated_at = CURRENT_TIMESTAMP
			  WHERE id = ?
			  ");
    $stmt->execute([$feedback, $answerId]);
    
    echo json_encode([
		      'status' => 'saved',
		      'student_answer_id' => $answerId
      ]);
  }

?>
