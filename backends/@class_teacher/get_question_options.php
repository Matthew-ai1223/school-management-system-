<?php
require_once '../config.php';
require_once '../database.php';
require_once 'class_teacher_auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

$question_id = isset($_GET['question_id']) ? (int)$_GET['question_id'] : 0;
$response = ['success' => false, 'options' => []];

if ($question_id > 0) {
    // Get the teacher ID from the session
    $teacher_id = $_SESSION['teacher_id'] ?? 0;
    
    // First verify that this question belongs to an exam created by this teacher
    $checkQuery = "SELECT q.id FROM cbt_questions q
                  JOIN cbt_exams e ON q.exam_id = e.id
                  WHERE q.id = ? AND e.teacher_id = ?";
    
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $question_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Get the options
        $optionsQuery = "SELECT id, option_text, is_correct 
                        FROM cbt_options 
                        WHERE question_id = ? 
                        ORDER BY id";
        
        $stmt = $conn->prepare($optionsQuery);
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $optionsResult = $stmt->get_result();
        
        $options = [];
        while ($row = $optionsResult->fetch_assoc()) {
            $options[] = [
                'id' => $row['id'],
                'option_text' => $row['option_text'],
                'is_correct' => (bool)$row['is_correct']
            ];
        }
        
        $response['success'] = true;
        $response['options'] = $options;
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 