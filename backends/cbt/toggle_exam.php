<?php
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in (either regular teacher or class teacher)
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'class_teacher')) {
    header("Location: login.php");
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get teacher details
$teacherId = $_SESSION['teacher_id'];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_id']) && isset($_POST['action'])) {
    $examId = intval($_POST['exam_id']);
    $action = $_POST['action']; // 'activate' or 'deactivate'
    
    // Verify the exam belongs to the current teacher
    $examQuery = "SELECT * FROM cbt_exams WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($examQuery);
    $stmt->bind_param("ii", $examId, $teacherId);
    $stmt->execute();
    $examResult = $stmt->get_result();
    
    if ($examResult->num_rows === 0) {
        $_SESSION['error_message'] = "You don't have permission to modify this exam.";
        header("Location: dashboard.php");
        exit;
    }
    
    $exam = $examResult->fetch_assoc();
    
    // Set the active status based on action
    $isActive = ($action === 'activate') ? 1 : 0;
    
    // Update the exam status
    $updateQuery = "UPDATE cbt_exams SET is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ii", $isActive, $examId);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Exam " . ($isActive ? "activated" : "deactivated") . " successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating exam status: " . $conn->error;
    }
    
    // Redirect back to dashboard
    header("Location: dashboard.php");
    exit;
} else {
    // If not a valid request, redirect to dashboard
    header("Location: dashboard.php");
    exit;
}
?> 