<?php
include '../confg.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $required_fields = ['session_type', 'payment_type', 'reference_number'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $session_type = $_POST['session_type'];
        $payment_type = $_POST['payment_type'];
        $reference_number = $_POST['reference_number'];
        $created_by = 'admin'; // You can modify this based on your admin authentication system

        // Check if reference number already exists
        $check_sql = "SELECT id FROM reference_numbers WHERE reference_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $reference_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Reference number already exists");
        }

        // Insert new reference number
        $sql = "INSERT INTO reference_numbers (reference_number, session_type, payment_type, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $reference_number, $session_type, $payment_type, $created_by);

        if (!$stmt->execute()) {
            throw new Exception("Failed to create reference number: " . $stmt->error);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Reference number created successfully'
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all reference numbers
    try {
        $sql = "SELECT * FROM reference_numbers ORDER BY created_at DESC";
        $result = $conn->query($sql);
        
        $references = [];
        while ($row = $result->fetch_assoc()) {
            $references[] = $row;
        }

        echo json_encode([
            'status' => 'success',
            'data' => $references
        ]);

    } catch (Exception $e) {
        http_response_code(400);
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