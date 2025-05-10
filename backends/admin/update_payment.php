<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Add after Database::getInstance();
$mysqli = $db->getConnection();

// Get payment ID and status
$payment_id = $_POST['id'] ?? 0;
$status = $_POST['status'] ?? '';

if (!$payment_id || !in_array($status, ['completed', 'failed'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment ID or status'
    ]);
    exit;
}

// Check if payment exists
$query = "SELECT * FROM payments WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Payment not found'
    ]);
    exit;
}

$payment = $result->fetch_assoc();

// Check if payment is already in final state
if ($payment['status'] !== 'pending') {
    echo json_encode([
        'success' => false,
        'message' => 'Payment is already in a final state'
    ]);
    exit;
}

// Update payment status
$query = "UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('si', $status, $payment_id);

if ($stmt->execute()) {
    // If payment is completed, update student status if it's an application fee
    if ($status === 'completed' && $payment['payment_type'] === 'application_fee') {
        $query = "UPDATE students SET status = 'registered', updated_at = NOW() WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $payment['student_id']);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment status updated successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update payment status'
    ]);
} 