<?php
require_once 'ctrl/db_config.php';
require_once 'ctrl/view_payments.php';

// Initialize PaymentRecords class
$paymentRecords = new PaymentRecords($conn);

if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];
    $payment = $paymentRecords->getPaymentDetails($reference);
    
    if ($payment) {
        // Return payment details as JSON
        header('Content-Type: application/json');
        echo json_encode($payment);
    } else {
        // Return error if payment not found
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
    }
} else {
    // Return error if no reference provided
    http_response_code(400);
    echo json_encode(['error' => 'No reference provided']);
}
?> 