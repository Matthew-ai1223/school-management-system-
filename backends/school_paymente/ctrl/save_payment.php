<?php
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['reference', 'student_id', 'payment_type_id', 'base_amount', 'service_charge'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $reference = $_POST['reference'];
        $student_id = $_POST['student_id'];
        $payment_type_id = $_POST['payment_type_id'];
        $base_amount = floatval($_POST['base_amount']);
        $service_charge = floatval($_POST['service_charge']);
        $total_amount = $base_amount + $service_charge;
        
        // Check if payment reference already exists
        $check_sql = "SELECT id FROM school_payments WHERE reference_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $reference);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Payment with this reference already exists");
        }
        
        // Save the payment details
        $sql = "INSERT INTO school_payments (student_id, payment_type_id, amount, base_amount, service_charge, reference_code, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siddds", $student_id, $payment_type_id, $total_amount, $base_amount, $service_charge, $reference);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Payment initiated successfully',
                'reference' => $reference
            ]);
        } else {
            throw new Exception("Error executing payment save query: " . $stmt->error);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
}
?> 