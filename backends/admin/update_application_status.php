<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (!$id || !in_array($status, ['approved', 'rejected', 'pending'])) {
        $response['message'] = 'Invalid parameters provided.';
        echo json_encode($response);
        exit;
    }
    
    $db = Database::getInstance();
    $mysqli = $db->getConnection();
    
    // Update application status
    $stmt = $mysqli->prepare("UPDATE applications SET status = ?, reviewed_by = ?, review_date = NOW() WHERE id = ?");
    $stmt->bind_param("sii", $status, $_SESSION['user_id'], $id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Application status updated successfully.';
    } else {
        $response['message'] = 'Failed to update application status.';
    }
}

echo json_encode($response); 