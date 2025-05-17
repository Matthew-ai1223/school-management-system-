<?php
require_once '../../config.php';
require_once '../../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $reference = trim($_POST['reference'] ?? '');
        
        if (empty($reference)) {
            throw new Exception('Please provide a payment reference');
        }
        
        $db = Database::getInstance();
        $mysqli = $db->getConnection();
        
        // Create used_references table if it doesn't exist
        $mysqli->query("
            CREATE TABLE IF NOT EXISTS used_references (
                id INT(11) NOT NULL AUTO_INCREMENT,
                reference VARCHAR(255) NOT NULL,
                used_time DATETIME NOT NULL,
                user_ip VARCHAR(50) NOT NULL,
                student_id INT(11) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY (reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // First, check if reference already exists in used_references table
        $stmt = $mysqli->prepare("SELECT id FROM used_references WHERE reference = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('This payment reference has already been used for registration. Each reference can only be used once.');
        }
        
        // Check if verification_logs table exists before logging
        $tableExists = false;
        $tablesResult = $mysqli->query("SHOW TABLES LIKE 'verification_logs'");
        if ($tablesResult && $tablesResult->num_rows > 0) {
            $tableExists = true;
            
            // Log verification attempt
            $ip = $_SERVER['REMOTE_ADDR'];
            $timestamp = date('Y-m-d H:i:s');
            $stmt = $mysqli->prepare("INSERT INTO verification_logs (reference, ip_address, timestamp, status) VALUES (?, ?, ?, 'attempted')");
            if ($stmt) {
                $stmt->bind_param('sss', $reference, $ip, $timestamp);
                $stmt->execute();
            }
        }
        
        // Check if reference exists and payment is completed
        $stmt = $mysqli->prepare("SELECT * FROM application_payments WHERE reference = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Payment reference not found in our records');
        }
        
        $payment = $result->fetch_assoc();
        
        // Check payment status
        if ($payment['status'] !== 'completed') {
            throw new Exception('This payment is not yet completed or has been declined');
        }
        
        // Check if the payment reference has a 'used' flag in the application_payments table
        $hasUsedFlag = false;
        $columnsResult = $mysqli->query("SHOW COLUMNS FROM application_payments");
        if ($columnsResult) {
            while ($column = $columnsResult->fetch_assoc()) {
                if ($column['Field'] === 'used' || $column['Field'] === 'is_used') {
                    $hasUsedFlag = true;
                    $usedField = $column['Field'];
                    break;
                }
            }
        }
        
        // If there's a used flag, check if this reference has been used
        if ($hasUsedFlag) {
            $usedCheckQuery = "SELECT id FROM application_payments WHERE reference = ? AND $usedField = 1";
            $stmt = $mysqli->prepare($usedCheckQuery);
            $stmt->bind_param('s', $reference);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('This payment reference has already been used for registration');
            }
        }
        
        // Check if students table has a payment_reference column
        $paymentReferenceColumnExists = false;
        $columnsResult = $mysqli->query("SHOW COLUMNS FROM students");
        if ($columnsResult) {
            while ($column = $columnsResult->fetch_assoc()) {
                if ($column['Field'] === 'payment_reference') {
                    $paymentReferenceColumnExists = true;
                    break;
                }
            }
        }
        
        // Check if reference has already been used for registration in the students table
        $referenceAlreadyUsed = false;
        
        if ($paymentReferenceColumnExists) {
            $stmt = $mysqli->prepare("SELECT id FROM students WHERE payment_reference = ?");
            $stmt->bind_param('s', $reference);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $referenceAlreadyUsed = true;
            }
        } else {
            // If there's no payment_reference column, we need to check all form fields
            // Get fields that might store payment reference
            $fieldsQuery = "DESCRIBE students";
            $fieldsResult = $mysqli->query($fieldsQuery);
            
            if ($fieldsResult && $fieldsResult->num_rows > 0) {
                $whereConditions = [];
                $parameters = [];
                $types = '';
                
                // Add possible field names that might store payment reference
                $possibleColumns = [
                    'reference', 'payment_ref', 'pay_ref', 'transaction_ref', 
                    'transaction_id', 'payment_id', 'application_payment'
                ];
                
                while ($field = $fieldsResult->fetch_assoc()) {
                    $fieldName = $field['Field'];
                    // If the field name contains any of these terms, it might be a payment reference field
                    if (in_array($fieldName, $possibleColumns) || 
                        strpos($fieldName, 'payment') !== false || 
                        strpos($fieldName, 'reference') !== false) {
                        $whereConditions[] = "$fieldName = ?";
                        $parameters[] = $reference;
                        $types .= 's';
                    }
                }
                
                if (!empty($whereConditions)) {
                    $query = "SELECT id FROM students WHERE " . implode(' OR ', $whereConditions);
                    $stmt = $mysqli->prepare($query);
                    
                    if ($stmt) {
                        // Bind parameters dynamically
                        $bindParams = array_merge([$types], $parameters);
                        $bindRefs = [];
                        foreach ($bindParams as $key => $value) {
                            $bindRefs[$key] = &$bindParams[$key];
                        }
                        
                        call_user_func_array([$stmt, 'bind_param'], $bindRefs);
                        $stmt->execute();
                        
                        if ($stmt->get_result()->num_rows > 0) {
                            $referenceAlreadyUsed = true;
                        }
                    }
                }
            }
        }
        
        // If reference is already used in students table, throw an exception
        if ($referenceAlreadyUsed) {
            throw new Exception('This payment reference has already been used for registration');
        }
        
        // If we got here, the reference is valid and unused
        // Immediately mark it as used in our used_references table
        $stmt = $mysqli->prepare("INSERT INTO used_references (reference, used_time, user_ip) VALUES (?, NOW(), ?)");
        $stmt->bind_param('ss', $reference, $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        
        $response['success'] = true;
        $response['message'] = 'Payment verified successfully';
        $response['data'] = [
            'email' => $payment['email'],
            'phone' => $payment['phone'],
            'amount' => $payment['amount']
        ];
        
        // Store verified reference in session
        $_SESSION['verified_payment_reference'] = $reference;
        $_SESSION['verified_payment_email'] = $payment['email'];
        $_SESSION['verified_payment_phone'] = $payment['phone'];
        $_SESSION['payment_verified_time'] = time();
        
        // Mark the reference as used if possible in application_payments
        if ($hasUsedFlag) {
            $updateQuery = "UPDATE application_payments SET $usedField = 1 WHERE reference = ?";
            $stmt = $mysqli->prepare($updateQuery);
            $stmt->bind_param('s', $reference);
            $stmt->execute();
        }
        
        // Update log if table exists
        if ($tableExists) {
            $stmt = $mysqli->prepare("UPDATE verification_logs SET status = 'success' WHERE reference = ? ORDER BY id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $reference);
                $stmt->execute();
            }
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        
        // Log failed verification if table exists
        if (isset($mysqli) && isset($tableExists) && $tableExists) {
            $error = $e->getMessage();
            $stmt = $mysqli->prepare("UPDATE verification_logs SET status = 'failed', error_message = ? WHERE reference = ? ORDER BY id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $error, $reference);
                $stmt->execute();
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} 