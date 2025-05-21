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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_id'])) {
    $examId = intval($_POST['exam_id']);
    
    // Verify the exam belongs to the current teacher
    $examQuery = "SELECT * FROM cbt_exams WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($examQuery);
    $stmt->bind_param("ii", $examId, $teacherId);
    $stmt->execute();
    $examResult = $stmt->get_result();
    
    if ($examResult->num_rows === 0) {
        $_SESSION['error_message'] = "You don't have permission to delete this exam.";
        header("Location: dashboard.php");
        exit;
    }
    
    $exam = $examResult->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if there are any attempts for this exam
        $attemptsQuery = "SELECT COUNT(*) as attempt_count FROM cbt_exam_attempts WHERE exam_id = ?";
        $stmt = $conn->prepare($attemptsQuery);
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        $attemptsResult = $stmt->get_result();
        $attempts = $attemptsResult->fetch_assoc();
        
        if ($attempts['attempt_count'] > 0) {
            // Delete all student answers for this exam's attempts
            $deleteAnswersQuery = "DELETE FROM cbt_student_answers WHERE attempt_id IN 
                                  (SELECT id FROM cbt_exam_attempts WHERE exam_id = ?)";
            $stmt = $conn->prepare($deleteAnswersQuery);
            $stmt->bind_param("i", $examId);
            $stmt->execute();
            
            // Delete all attempts for this exam
            $deleteAttemptsQuery = "DELETE FROM cbt_exam_attempts WHERE exam_id = ?";
            $stmt = $conn->prepare($deleteAttemptsQuery);
            $stmt->bind_param("i", $examId);
            $stmt->execute();
        }
        
        // Delete all questions for this exam
        $deleteQuestionsQuery = "DELETE FROM cbt_questions WHERE exam_id = ?";
        $stmt = $conn->prepare($deleteQuestionsQuery);
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        
        // Delete the exam
        $deleteExamQuery = "DELETE FROM cbt_exams WHERE id = ?";
        $stmt = $conn->prepare($deleteExamQuery);
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Exam deleted successfully!";
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting exam: " . $e->getMessage();
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