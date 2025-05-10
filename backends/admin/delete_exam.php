<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Add after Database::getInstance();
$mysqli = $db->getConnection();

// Get exam ID
$exam_id = $_POST['id'] ?? '';

if (!$exam_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid exam ID.'
    ]);
    exit;
}

// Check if exam exists
$stmt = $mysqli->prepare("SELECT id FROM exam_results WHERE id = ?");
$stmt->bind_param('i', $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Exam result not found.'
    ]);
    exit;
}

// Delete exam result
$stmt = $mysqli->prepare("DELETE FROM exam_results WHERE id = ?");
$stmt->bind_param('i', $exam_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Exam result deleted successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting exam result.'
    ]);
} 