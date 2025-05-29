<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
//     exit();
// }

// Check if request is POST and has JSON content
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST) && empty(file_get_contents('php://input'))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Get exam ID from POST data
$data = json_decode(file_get_contents('php://input'), true);
$exam_id = filter_var($data['exam_id'] ?? null, FILTER_VALIDATE_INT);

if (!$exam_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid exam ID']);
    exit();
}

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();

    // Delete user responses for this exam
    $query = "DELETE ur FROM user_responses ur 
              JOIN exam_attempts ea ON ur.attempt_id = ea.id 
              WHERE ea.exam_id = :exam_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $exam_id]);

    // Delete exam attempts
    $query = "DELETE FROM exam_attempts WHERE exam_id = :exam_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $exam_id]);

    // Delete questions
    $query = "DELETE FROM questions WHERE exam_id = :exam_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $exam_id]);

    // Delete certificates
    $query = "DELETE FROM certificates WHERE exam_id = :exam_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $exam_id]);

    // Finally, delete the exam
    $query = "DELETE FROM exams WHERE id = :exam_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $exam_id]);

    $db->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Error deleting exam: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while deleting the exam'
    ]);
} 