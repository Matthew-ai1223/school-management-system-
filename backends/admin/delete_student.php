<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Add after Database::getInstance();
$mysqli = $db->getConnection();

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    // Get student ID from POST data
    $student_id = $_POST['id'] ?? 0;
    
    if (!$student_id) {
        throw new Exception('Invalid student ID');
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Delete associated records first
        // Delete exam results
        $query = "DELETE FROM exam_results WHERE student_id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        
        // Delete payments
        $query = "DELETE FROM payments WHERE student_id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        
        // Delete student record
        $query = "DELETE FROM students WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $student_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete student record');
        }
        
        // If we got here, commit the transaction
        $db->commit();
        
        $response['success'] = true;
        $response['message'] = 'Student record deleted successfully';
        
    } catch (Exception $e) {
        // If any error occurs, rollback the transaction
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 