<?php
require_once '../config.php';
require_once '../database.php';
require_once 'class_teacher_auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize response
$response = [
    'success' => false,
    'message' => 'An error occurred'
];

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get parameters
    $exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
    $total_questions = isset($_POST['total_questions']) ? (int)$_POST['total_questions'] : 0;
    $teacher_id = $_SESSION['teacher_id'] ?? 0;
    
    if ($exam_id > 0 && $total_questions > 0 && $teacher_id > 0) {
        // Verify that this exam belongs to the current teacher
        $checkQuery = "SELECT id FROM cbt_exams WHERE id = ? AND teacher_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ii", $exam_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update the exam's total_questions field
            $updateQuery = "UPDATE cbt_exams SET total_questions = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ii", $total_questions, $exam_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Exam updated successfully';
            } else {
                $response['message'] = 'Database error: ' . $conn->error;
            }
        } else {
            $response['message'] = 'You do not have permission to modify this exam';
        }
    } else {
        $response['message'] = 'Invalid parameters';
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 