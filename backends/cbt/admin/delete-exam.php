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
$exam_id = isset($data['exam_id']) ? filter_var($data['exam_id'], FILTER_VALIDATE_INT) : null;

if (!$exam_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid exam ID']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Start transaction
    $db->beginTransaction();

    // First verify the exam belongs to this teacher
    $verifyQuery = "SELECT id FROM exams WHERE id = :exam_id AND created_by = :teacher_id";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute([
        ':exam_id' => $exam_id,
        ':teacher_id' => $_SESSION['teacher_id']
    ]);
    
    if (!$verifyStmt->fetch()) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Exam not found or unauthorized']);
        exit();
    }

    // Get all question images for this exam
    $imageQuery = "SELECT image_url FROM questions WHERE exam_id = :exam_id AND image_url IS NOT NULL";
    $imageStmt = $db->prepare($imageQuery);
    $imageStmt->execute([':exam_id' => $exam_id]);
    $images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete exam attempts first (due to foreign key constraints)
    $deleteAttemptsQuery = "DELETE FROM exam_attempts WHERE exam_id = :exam_id";
    $deleteAttemptsStmt = $db->prepare($deleteAttemptsQuery);
    $deleteAttemptsStmt->execute([':exam_id' => $exam_id]);

    // Delete all questions for this exam
    $deleteQuestionsQuery = "DELETE FROM questions WHERE exam_id = :exam_id";
    $deleteQuestionsStmt = $db->prepare($deleteQuestionsQuery);
    $deleteQuestionsStmt->execute([':exam_id' => $exam_id]);

    // Finally delete the exam
    $deleteExamQuery = "DELETE FROM exams WHERE id = :exam_id AND created_by = :teacher_id";
    $deleteExamStmt = $db->prepare($deleteExamQuery);
    $result = $deleteExamStmt->execute([
        ':exam_id' => $exam_id,
        ':teacher_id' => $_SESSION['teacher_id']
    ]);

    if ($result) {
        // Delete associated image files
        foreach ($images as $image_url) {
            if (!empty($image_url)) {
                $image_path = '../' . $image_url;
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
        }

        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Exam and all associated data deleted successfully'
        ]);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete exam']);
    }

} catch (PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("Database error in delete-exam.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("Error in delete-exam.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} 