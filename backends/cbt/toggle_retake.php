<?php
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header("Location: login.php");
    exit;
}

// Check if exam_id and action are provided
if (!isset($_POST['exam_id']) || !isset($_POST['action'])) {
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: dashboard.php");
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

$exam_id = $_POST['exam_id'];
$action = $_POST['action'];
$teacher_id = $_SESSION['teacher_id'];

try {
    // First, verify that this exam belongs to the logged-in teacher
    $verify_query = "SELECT id FROM cbt_exams WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("ii", $exam_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("You don't have permission to modify this exam.");
    }

    // Update the exam's retake status
    $allow_retake = ($action === 'enable_retake') ? 1 : 0;
    
    $update_query = "UPDATE cbt_exams SET allow_retake = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $allow_retake, $exam_id);
    
    if ($stmt->execute()) {
        // Start transaction for status updates
        $conn->begin_transaction();
        
        try {
            // If enabling retakes, reset attempt counts for all students
            if ($action === 'enable_retake') {
                // Mark all completed attempts as 'archived'
                $archive_query = "UPDATE cbt_exam_attempts 
                                SET status = 'archived' 
                                WHERE exam_id = ? AND status IN ('completed', 'Completed')";
                $stmt = $conn->prepare($archive_query);
                $stmt->bind_param("i", $exam_id);
                $stmt->execute();
                
                // Reset in-progress attempts
                $reset_query = "UPDATE cbt_exam_attempts 
                              SET status = 'cancelled' 
                              WHERE exam_id = ? AND status = 'in_progress'";
                $stmt = $conn->prepare($reset_query);
                $stmt->bind_param("i", $exam_id);
                $stmt->execute();
                
                // Reset student exam status
                $reset_student_exams = "UPDATE cbt_student_exams 
                                      SET status = 'archived',
                                          completed_at = NULL,
                                          score = NULL 
                                      WHERE exam_id = ? AND status IN ('Completed', 'completed', 'Passed', 'passed', 'Failed', 'failed')";
                $stmt = $conn->prepare($reset_student_exams);
                $stmt->bind_param("i", $exam_id);
                $stmt->execute();
            }
            
            // Commit all changes
            $conn->commit();
            
            $_SESSION['success_message'] = ($action === 'enable_retake') 
                ? "Exam retakes have been enabled successfully. All previous attempts have been archived." 
                : "Exam retakes have been disabled successfully.";
                
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            throw new Exception("Failed to update exam status: " . $e->getMessage());
        }
    } else {
        throw new Exception("Failed to update exam retake status.");
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

header("Location: dashboard.php");
exit;
?> 