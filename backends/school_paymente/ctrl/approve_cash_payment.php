<?php
require_once 'db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['reference']) || empty($_POST['reference'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Reference code is required']);
    exit;
}

$reference = $_POST['reference'];

try {
    // Get the cash payment details
    $sql = "SELECT * FROM cash_payments WHERE reference_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Cash payment not found');
    }
    
    $payment = $result->fetch_assoc();
    
    // Check if payment is already approved
    if ($payment['approval_status'] === 'approved') {
        throw new Exception('Payment is already approved');
    }
    
    // Update the payment approval status
    $update_sql = "UPDATE cash_payments SET 
                    approval_status = 'approved',
                    approver_id = 'ADMIN-001',
                    approver_name = 'Administrator',
                    approval_date = CURRENT_TIMESTAMP,
                    payment_status = 'completed'
                   WHERE reference_code = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("s", $reference);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update payment approval status');
    }
    
    if ($update_stmt->affected_rows === 0) {
        throw new Exception('No payment was updated');
    }
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Cash payment approved successfully',
        'payment' => [
            'reference_code' => $payment['reference_code'],
            'student_id' => $payment['student_id'],
            'amount' => $payment['amount'],
            'base_amount' => $payment['base_amount'],
            'approval_status' => 'approved',
            'approver_name' => 'Administrator',
            'approval_date' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?> 