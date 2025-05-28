<?php
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';
require_once 'test_db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check if session_id is provided
if (!isset($_POST['session_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing session ID']);
    exit;
}

$session_id = $_POST['session_id'];

// Initialize database
$testDb = TestDatabase::getInstance();

try {
    // Submit the exam
    $result = $testDb->submitExam($session_id);
    
    if ($result['success']) {
        // Clear exam session data
        unset($_SESSION['current_exam']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Exam submitted successfully',
            'score' => $result['score'],
            'status' => $result['status'],
            'marks_obtained' => $result['marks_obtained'],
            'redirect_url' => "view_result.php?session_id=$session_id"
        ]);
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    error_log("Error submitting exam: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 