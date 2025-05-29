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

// Get question ID from POST data
$data = json_decode(file_get_contents('php://input'), true);
$question_id = filter_var($data['question_id'] ?? null, FILTER_VALIDATE_INT);

if (!$question_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid question ID']);
    exit();
}

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();

    // Get image URL before deleting the question
    $query = "SELECT image_url FROM questions WHERE id = :question_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':question_id' => $question_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete user responses for this question
    $query = "DELETE FROM user_responses WHERE question_id = :question_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':question_id' => $question_id]);

    // Delete the question
    $query = "DELETE FROM questions WHERE id = :question_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':question_id' => $question_id]);

    // Delete the image file if it exists
    if ($question && $question['image_url']) {
        $image_path = $_SERVER['DOCUMENT_ROOT'] . '/online-exam/' . $question['image_url'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    $db->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Error deleting question: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while deleting the question'
    ]);
} 