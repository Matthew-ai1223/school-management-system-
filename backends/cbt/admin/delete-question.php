<?php
// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$question_id = isset($data['question_id']) ? filter_var($data['question_id'], FILTER_VALIDATE_INT) : null;

if (!$question_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid question ID']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // First verify the question belongs to an exam owned by this teacher
    $verifyQuery = "SELECT q.id, q.exam_id, q.image_url 
                   FROM questions q 
                   JOIN exams e ON q.exam_id = e.id 
                   WHERE q.id = :question_id AND e.created_by = :teacher_id";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute([
        ':question_id' => $question_id,
        ':teacher_id' => $_SESSION['teacher_id']
    ]);
    
    $question = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'Question not found or unauthorized']);
        exit();
    }

    // Delete the associated image if it exists
    if (!empty($question['image_url'])) {
        $image_path = '../' . $question['image_url'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    // Delete the question
    $deleteQuery = "DELETE FROM questions WHERE id = :question_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $result = $deleteStmt->execute([':question_id' => $question_id]);

    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Question deleted successfully',
            'exam_id' => $question['exam_id']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete question']);
    }

} catch (PDOException $e) {
    error_log("Database error in delete-question.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in delete-question.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} 