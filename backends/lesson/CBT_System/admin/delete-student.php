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

// Get student ID from POST data
$data = json_decode(file_get_contents('php://input'), true);
$student_id = filter_var($data['student_id'] ?? null, FILTER_VALIDATE_INT);

if (!$student_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();

    // Delete user responses
    $query = "DELETE ur FROM user_responses ur 
              JOIN exam_attempts ea ON ur.attempt_id = ea.id 
              WHERE ea.user_id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $student_id]);

    // Delete exam attempts
    $query = "DELETE FROM exam_attempts WHERE user_id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $student_id]);

    // Delete certificates
    $query = "DELETE FROM certificates WHERE user_id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $student_id]);

    // Delete activity logs
    $query = "DELETE FROM activity_logs WHERE user_id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $student_id]);

    // Finally, delete the student
    $query = "DELETE FROM users WHERE id = :student_id AND role = 'student'";
    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $student_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Student not found or not authorized to delete.');
    }

    $db->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $db->rollBack();
    error_log("Error deleting student: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while deleting the student'
    ]);
} 