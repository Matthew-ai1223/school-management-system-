<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../utils.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$student_id = $_POST['student_id'] ?? null;
$payment_type = $_POST['payment_type'] ?? null;
$amount = $_POST['amount'] ?? null;
$payment_method = $_POST['payment_method'] ?? null;
$reference_number = $_POST['reference_number'] ?? null;
$notes = $_POST['notes'] ?? null;

// Validate required fields
if (!$student_id || !$payment_type || !$amount || !$payment_method) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// Validate amount
if (!is_numeric($amount) || $amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid amount']);
    exit;
}

try {
    // Connect to database
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Prepare the insert statement
    $stmt = $conn->prepare("INSERT INTO payments (
        student_id, 
        payment_type, 
        amount, 
        payment_method, 
        reference_number, 
        notes, 
        status, 
        payment_date, 
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");

    // Bind parameters
    $stmt->bind_param(
        "isdsss",
        $student_id,
        $payment_type,
        $amount,
        $payment_method,
        $reference_number,
        $notes
    );

    // Execute the statement
    if ($stmt->execute()) {
        // Get the payment ID
        $payment_id = $conn->insert_id;

        // Here you would typically integrate with a payment gateway
        // For now, we'll simulate a successful payment
        $success = true;

        if ($success) {
            // Update payment status to completed
            $update_stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
            $update_stmt->bind_param("i", $payment_id);
            $update_stmt->execute();

            echo json_encode([
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'payment_id' => $payment_id
            ]);
        } else {
            // Update payment status to failed
            $update_stmt = $conn->prepare("UPDATE payments SET status = 'failed' WHERE id = ?");
            $update_stmt->bind_param("i", $payment_id);
            $update_stmt->execute();

            echo json_encode([
                'status' => 'error',
                'message' => 'Payment processing failed'
            ]);
        }
    } else {
        throw new Exception("Error inserting payment record");
    }
} catch (Exception $e) {
    error_log("Payment processing error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing the payment'
    ]);
}
?> 