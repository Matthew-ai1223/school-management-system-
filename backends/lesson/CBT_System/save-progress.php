<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

session_start();

// Check if the request is POST and has JSON content
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(400);
    exit('Invalid request');
}

// Get the JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['question_number']) || !isset($data['attempt_id']) || !isset($data['answer'])) {
    http_response_code(400);
    exit('Missing required data');
}

try {
    $db = Database::getInstance()->getConnection();

    // Update or insert the answer
    $stmt = $db->prepare("
        INSERT INTO user_responses (attempt_id, question_id, selected_answer)
        VALUES (:attempt_id, :question_id, :answer)
        ON DUPLICATE KEY UPDATE selected_answer = :answer
    ");

    $stmt->execute([
        ':attempt_id' => $data['attempt_id'],
        ':question_id' => $data['question_number'],
        ':answer' => $data['answer']
    ]);

    // Track answered questions in session
    if (!isset($_SESSION['answered_questions'])) {
        $_SESSION['answered_questions'] = [];
    }
    if (!in_array($data['question_number'], $_SESSION['answered_questions'])) {
        $_SESSION['answered_questions'][] = $data['question_number'];
    }

    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 