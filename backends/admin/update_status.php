<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

$response = ['success' => false, 'message' => '', 'new_status' => ''];

try {
    // Log incoming request
    error_log("Received status update request: " . print_r($_POST, true));

    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get student ID and new status
    $student_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_status = $_POST['status'] ?? '';

    // Log parsed values
    error_log("Parsed values - student_id: $student_id, new_status: $new_status");

    // Validate inputs
    if ($student_id <= 0) {
        throw new Exception('Invalid student ID');
    }

    $allowed_statuses = ['pending', 'registered', 'rejected'];
    if (!in_array($new_status, $allowed_statuses)) {
        throw new Exception('Invalid status');
    }

    // Update student status
    $query = "UPDATE students SET status = ? WHERE id = ?";
    error_log("Executing query: $query with params: [$new_status, $student_id]");
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param('si', $new_status, $student_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Status updated successfully';
            $response['new_status'] = $new_status;
            error_log("Status updated successfully for student ID: $student_id to: $new_status");
        } else {
            throw new Exception("Student not found or status unchanged (ID: $student_id)");
        }
    } else {
        throw new Exception('Failed to execute update: ' . $stmt->error);
    }
} catch (Exception $e) {
    error_log("Error in update_status.php: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 