<?php
include '../confg.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$reference = trim($_POST['reference'] ?? '');

if (empty($reference)) {
    echo json_encode(['status' => 'error', 'message' => 'Reference number is required']);
    exit;
}

try {
    // Check if reference exists and is not used
    $sql = "SELECT rn.*, cp.* 
            FROM reference_numbers rn 
            LEFT JOIN cash_payments cp ON rn.reference_number = cp.reference_number 
            WHERE rn.reference_number = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid reference number']);
        exit;
    }
    
    $data = $result->fetch_assoc();
    
    if ($data['is_used']) {
        echo json_encode(['status' => 'error', 'message' => 'Reference number has already been used']);
        exit;
    }
    
    // Return payment details
    echo json_encode([
        'status' => 'success',
        'data' => [
            'reference_number' => $data['reference_number'],
            'session_type' => $data['session_type'],
            'payment_type' => $data['payment_type'],
            'payment_amount' => $data['payment_amount'],
            'fullname' => $data['fullname'],
            'department' => $data['department'],
            'class' => $data['class'] ?? '',
            'school' => $data['school'] ?? '',
            'expiration_date' => $data['expiration_date']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 