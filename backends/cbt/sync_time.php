<?php
require_once '../config.php';
require_once '../database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to UTC for consistent time handling
date_default_timezone_set('UTC');

// Initialize response array
$response = [
    'success' => false,
    'server_time' => time(),
    'remaining_time' => 0,
    'end_time' => 0,
    'duration' => 0,
    'message' => ''
];

// Check if session_id is provided
if (!isset($_GET['session_id'])) {
    $response['message'] = 'Missing session ID';
    echo json_encode($response);
    exit;
}

try {
    // Initialize database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $session_id = intval($_GET['session_id']);
    
    // Get exam session details with duration from cbt_exams
    $stmt = $conn->prepare("
        SELECT se.*, e.duration 
        FROM cbt_student_attempts se 
        JOIN cbt_exams e ON se.exam_id = e.id 
        WHERE se.id = ? AND se.student_id = ? AND se.status = 'In Progress'
    ");
    $stmt->bind_param("ii", $session_id, $_SESSION['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $exam_session = $result->fetch_assoc();
        
        // Calculate remaining time
        $duration_minutes = intval($exam_session['duration']);
        $start_time = strtotime($exam_session['start_time']);
        $end_time = strtotime($exam_session['end_time']);
        $current_time = time();
        $remaining_time = $end_time - $current_time;
        
        // Update response
        $response['success'] = true;
        $response['remaining_time'] = max(0, $remaining_time);
        $response['end_time'] = $end_time;
        $response['duration'] = $duration_minutes;
        
        // If time is up, update exam status
        if ($remaining_time <= 0 && $exam_session['status'] === 'In Progress') {
            $update_stmt = $conn->prepare("
                UPDATE cbt_exam_attempts 
                SET status = 'Time Expired',
                    submit_time = NOW(),
                    show_result = 1
                WHERE id = ?
            ");
            $update_stmt->bind_param("i", $session_id);
            $update_stmt->execute();
            
            $response['message'] = 'Exam time has expired';
        }
    } else {
        $response['message'] = 'Invalid exam session or exam already completed';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error syncing time: ' . $e->getMessage();
    error_log("Time sync error: " . $e->getMessage());
}

// Send response
header('Content-Type: application/json');
echo json_encode($response); 