<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get form data
$student_id = $_POST['student_id'] ?? '';
$exam_type = $_POST['exam_type'] ?? '';
$exam_date = $_POST['exam_date'] ?? '';
$score = $_POST['score'] ?? '';
$total_score = $_POST['total_score'] ?? '';
$status = $_POST['status'] ?? '';
$remarks = $_POST['remarks'] ?? '';

// Validate required fields
if (!$student_id || !$exam_type || !$exam_date || !$score || !$total_score || !$status) {
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

// Check if student exists and is registered
$stmt = $db->prepare("SELECT id FROM students WHERE id = ? AND status = 'registered'");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student selected.'
    ]);
    exit;
}

// Prepare data for insertion
$data = [
    'student_id' => $student_id,
    'exam_type' => $exam_type,
    'exam_date' => $exam_date,
    'score' => $score,
    'total_score' => $total_score,
    'status' => $status,
    'remarks' => $remarks,
    'created_at' => date('Y-m-d H:i:s')
];

// Insert exam result
$query = "INSERT INTO exam_results (student_id, exam_type, exam_date, score, total_score, status, remarks, created_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $db->prepare($query);
$stmt->bind_param('issdiiss', 
    $data['student_id'],
    $data['exam_type'],
    $data['exam_date'],
    $data['score'],
    $data['total_score'],
    $data['status'],
    $data['remarks'],
    $data['created_at']
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Exam result added successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while adding exam result.'
    ]);
} 