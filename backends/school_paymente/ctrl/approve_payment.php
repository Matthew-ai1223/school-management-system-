<?php
require_once 'db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['reference'])) {
            throw new Exception('Reference code is required');
        }

        $reference = $_POST['reference'];
        $method = isset($_POST['method']) ? $_POST['method'] : 'online';
        
        if ($method === 'cash') {
            // Update cash payment status to completed
            $sql = "UPDATE cash_payments SET payment_status = 'completed' WHERE reference_code = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $reference);
            
            if ($stmt->execute()) {
                // Get updated cash payment details
                $sql = "SELECT cp.*, pt.name as payment_type_name 
                        FROM cash_payments cp 
                        JOIN school_payment_types pt ON cp.payment_type_id = pt.id 
                        WHERE cp.reference_code = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $reference);
                $stmt->execute();
                $result = $stmt->get_result();
                $payment = $result->fetch_assoc();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Cash payment approved successfully',
                    'payment' => $payment
                ]);
            } else {
                throw new Exception('Failed to approve cash payment');
            }
        } else {
            // Update online payment status to completed
            $sql = "UPDATE school_payments SET payment_status = 'completed' WHERE reference_code = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $reference);
            
            if ($stmt->execute()) {
                // Get updated online payment details
                $sql = "SELECT p.*, pt.name as payment_type_name 
                        FROM school_payments p 
                        JOIN school_payment_types pt ON p.payment_type_id = pt.id 
                        WHERE p.reference_code = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $reference);
                $stmt->execute();
                $result = $stmt->get_result();
                $payment = $result->fetch_assoc();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Online payment approved successfully',
                    'payment' => $payment
                ]);
            } else {
                throw new Exception('Failed to approve online payment');
            }
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