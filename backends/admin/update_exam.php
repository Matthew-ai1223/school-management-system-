<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get form data
$exam_id = $_POST['id'] ?? '';
$exam_type = $_POST['exam_type'] ?? '';
$exam_date = $_POST['exam_date'] ?? '';
$score = $_POST['score'] ?? '';
$total_score = $_POST['total_score'] ?? '';
$status = $_POST['status'] ?? '';
$remarks = $_POST['remarks'] ?? '';

// Validate required fields
if (!$exam_id || !$exam_type || !$exam_date || !$score || !$total_score || !$status) {
    echo json_encode([
        'success' => false,
        'message' => 'All required fields must be filled out.'
    ]);
    exit;
}

// Validate score
if (!is_numeric($score) || !is_numeric($total_score) || $score < 0 || $total_score <= 0 || $score > $total_score) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid score values.'
    ]);
    exit;
}

// Check if exam exists
$stmt = $db->prepare("SELECT id FROM exam_results WHERE id = ?");
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

// Prepare data for update
$data = [
    'exam_type' => $exam_type,
    'exam_date' => $exam_date,
    'score' => $score,
    'total_score' => $total_score,
    'status' => $status,
    'remarks' => $remarks,
    'updated_at' => date('Y-m-d H:i:s')
];

// Update exam result
$query = "UPDATE exam_results 
          SET exam_type = ?, exam_date = ?, score = ?, total_score = ?, status = ?, remarks = ?, updated_at = ? 
          WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('ssdiissi', 
    $data['exam_type'],
    $data['exam_date'],
    $data['score'],
    $data['total_score'],
    $data['status'],
    $data['remarks'],
    $data['updated_at'],
    $exam_id
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Exam result updated successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating exam result.'
    ]);
} 