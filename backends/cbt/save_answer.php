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

// Check if required parameters are provided
if (!isset($_POST['session_id']) || !isset($_POST['question_id']) || !isset($_POST['answer'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$session_id = $_POST['session_id'];
$question_id = $_POST['question_id'];
$answer = $_POST['answer'];

// Initialize database
$testDb = TestDatabase::getInstance();

try {
    // Save the answer
    $result = $testDb->saveStudentAnswer($session_id, $question_id, $answer);
    
    echo json_encode(['success' => $result]);
    
} catch (Exception $e) {
    error_log("Error saving answer: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 