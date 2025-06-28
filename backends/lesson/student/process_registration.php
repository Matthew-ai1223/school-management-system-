<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// First, ensure no output has been sent
ob_start();

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Enable error reporting but log to file instead of output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Remove the utils.php include since it doesn't exist
// require_once __DIR__ . '/utils.php';

if (headers_sent()) {
    file_put_contents(__DIR__ . '/debug.log', 'Headers already sent!' . PHP_EOL, FILE_APPEND);
}

// Remove the duplicate function definition since it's already in check_account_status.php
// function calculateExpirationDate($payment_type) {
//     $current_date = new DateTime();
//     
//     // For full payment, expiration is 1 year from now
//     if (strtolower($payment_type) === 'full') {
//         return $current_date->modify('+1 year')->format('Y-m-d');
//     }
//     
//     // For part payment, expiration is 6 months from now
//     return $current_date->modify('+6 months')->format('Y-m-d');
// }

file_put_contents(__DIR__ . '/debug.log', 'Script started: ' . date('c') . PHP_EOL, FILE_APPEND);

try {
    // Database connection
    require_once '../confg.php';
    file_put_contents(__DIR__ . '/debug.log', 'DB config included' . PHP_EOL, FILE_APPEND);
    
    if (!isset($conn) || $conn->connect_error) {
        file_put_contents(__DIR__ . '/debug.log', 'DB connection failed' . PHP_EOL, FILE_APPEND);
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
    }
    file_put_contents(__DIR__ . '/debug.log', 'DB connection successful' . PHP_EOL, FILE_APPEND);
    
    // Clear any existing output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Rest of your existing code starting from require_once statements
    require_once __DIR__ . '/check_account_status.php';
    file_put_contents(__DIR__ . '/debug.log', 'After require_once check_account_status.php' . PHP_EOL, FILE_APPEND);
    require_once __DIR__ . '/generate_receipt.php';
    file_put_contents(__DIR__ . '/debug.log', 'Required files included' . PHP_EOL, FILE_APPEND);

    // Add migration to ensure created_at column exists
    try {
        $conn->query("ALTER TABLE morning_students ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    } catch (Exception $e) {}
    try {
        $conn->query("ALTER TABLE afternoon_students ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    } catch (Exception $e) {}
    // Add migration to ensure reg_number column exists
    try {
        $conn->query("ALTER TABLE morning_students ADD COLUMN IF NOT EXISTS reg_number VARCHAR(32) UNIQUE NULL");
    } catch (Exception $e) {}
    try {
        $conn->query("ALTER TABLE afternoon_students ADD COLUMN IF NOT EXISTS reg_number VARCHAR(32) UNIQUE NULL");
    } catch (Exception $e) {}

    // Verify Paystack payment
    function verifyPaystackPayment($reference) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "authorization: Bearer sk_test_ba85c77b3ea04ae33627b38ca46cf8e3b5a4edc5",
                "cache-control: no-cache"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return false;
        }

        $tranx = json_decode($response);
        return $tranx->status && $tranx->data->status === 'success';
    }

    // Verify Paystack payment or check pre-generated reference
    function verifyPayment($reference, $form_type) {
        global $conn;
        
        // First check if it's a pre-generated reference number from cash payment
        $check_sql = "SELECT rn.*, cp.* 
                      FROM reference_numbers rn 
                      LEFT JOIN cash_payments cp ON rn.reference_number = cp.reference_number 
                      WHERE rn.reference_number = ? AND rn.session_type = ? AND rn.is_used = 0";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $reference, $form_type);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $payment_data = $result->fetch_assoc();
            
            // Mark the reference as used
            $update_sql = "UPDATE reference_numbers SET is_used = 1, used_at = CURRENT_TIMESTAMP WHERE reference_number = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("s", $reference);
            $update_stmt->execute();
            
            // Mark cash payment as processed
            $update_cash_sql = "UPDATE cash_payments SET is_processed = 1, processed_at = CURRENT_TIMESTAMP WHERE reference_number = ?";
            $update_cash_stmt = $conn->prepare($update_cash_sql);
            $update_cash_stmt->bind_param("s", $reference);
            $update_cash_stmt->execute();
            
            // Return the cash payment data
            return $payment_data;
        }
        
        // If not a pre-generated reference, verify with Paystack
        return verifyPaystackPayment($reference);
    }

    // Handle the registration
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Validate required fields
            $required_fields = ['reference', 'form_type', 'payment_type', 'amount'];
            foreach ($required_fields as $field) {
                if (!isset($_POST[$field]) || empty($_POST[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $reference = $_POST['reference'];
            $form_type = $_POST['form_type'];
            $payment_type = $_POST['payment_type'];
            $amount = $_POST['amount'];

            // Calculate expiration date
            $expiration_date = calculateExpirationDate($payment_type);

            // Verify payment or pre-generated reference
            $payment_verification = verifyPayment($reference, $form_type === 'morning' ? 'morning' : 'afternoon');
            if (!$payment_verification) {
                throw new Exception('Invalid reference number or payment verification failed.');
            }

            // If it's a pre-generated reference, use its payment type
            if (is_array($payment_verification)) {
                $payment_type = $payment_verification['payment_type'];
                // Set amount based on payment type and session
                if ($form_type === 'morning') {
                    $amount = $payment_type === 'full' ? 10000 : 5200;
                } else {
                    $amount = $payment_type === 'full' ? 4000 : 2200;
                }
            }

            // Create uploads directory if it doesn't exist
            $uploads_dir = dirname(__FILE__) . '/uploads';
            if (!file_exists($uploads_dir)) {
                if (!mkdir($uploads_dir, 0777, true)) {
                    throw new Exception('Failed to create uploads directory. Please check server permissions.');
                }
            }

            // Prepare data based on form type
            if ($form_type === 'morning') {
                // Validate morning form fields
                $required_morning_fields = ['morning_fullname', 'morning_email', 'morning_phone', 'morning_password', 
                                          'morning_department', 'morning_parent_name', 'morning_parent_phone', 'morning_address'];
                foreach ($required_morning_fields as $field) {
                    if (!isset($_POST[$field]) || empty($_POST[$field])) {
                        throw new Exception("Missing required field: $field");
                    }
                }

                $fullname = $_POST['morning_fullname'];
                $email = $_POST['morning_email'];
                $phone = $_POST['morning_phone'];
                $password = password_hash($_POST['morning_password'], PASSWORD_DEFAULT);
                $department = $_POST['morning_department'];
                $parent_name = $_POST['morning_parent_name'];
                $parent_phone = $_POST['morning_parent_phone'];
                $address = $_POST['morning_address'];
                
                // Handle photo upload
                if (!isset($_FILES['morning_photo']) || $_FILES['morning_photo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Photo upload failed: ' . ($_FILES['morning_photo']['error'] ?? 'No file uploaded'));
                }
                
                $photo = $_FILES['morning_photo'];
                $photo_name = time() . '_' . basename($photo['name']);
                $photo_path = $uploads_dir . '/' . $photo_name;
                
                if (!move_uploaded_file($photo['tmp_name'], $photo_path)) {
                    throw new Exception('Failed to move uploaded photo');
                }

                $sql = "INSERT INTO morning_students (fullname, email, phone, password, photo, department, parent_name, parent_phone, address, payment_reference, payment_type, payment_amount, expiration_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Database prepare failed: ' . $conn->error);
                }
                
                $stmt->bind_param("sssssssssssss", $fullname, $email, $phone, $password, $photo_path, $department, $parent_name, $parent_phone, $address, $reference, $payment_type, $amount, $expiration_date);
            } else {
                // Afternoon class registration
                $fullname = $_POST['afternoon_fullname'];
                $email = $_POST['afternoon_email'];
                $phone = $_POST['afternoon_phone'];
                $password = password_hash($_POST['afternoon_password'], PASSWORD_DEFAULT);
                $department = $_POST['afternoon_department'];
                $class = $_POST['afternoon_class'];
                $school = $_POST['afternoon_school'];
                $parent_name = $_POST['afternoon_parent_name'];
                $parent_phone = $_POST['afternoon_parent_phone'];
                $address = $_POST['afternoon_address'];
                
                // Handle photo upload
                if (!isset($_FILES['afternoon_photo']) || $_FILES['afternoon_photo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Photo upload failed: ' . ($_FILES['afternoon_photo']['error'] ?? 'No file uploaded'));
                }
                
                $photo = $_FILES['afternoon_photo'];
                $photo_name = time() . '_' . basename($photo['name']);
                $photo_path = $uploads_dir . '/' . $photo_name;
                
                if (!move_uploaded_file($photo['tmp_name'], $photo_path)) {
                    throw new Exception('Failed to move uploaded photo');
                }

                $sql = "INSERT INTO afternoon_students (fullname, email, phone, password, photo, department, class, school, parent_name, parent_phone, address, payment_reference, payment_type, payment_amount, expiration_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Database prepare failed: ' . $conn->error);
                }
                
                $stmt->bind_param("sssssssssssssss", $fullname, $email, $phone, $password, $photo_path, $department, $class, $school, $parent_name, $parent_phone, $address, $reference, $payment_type, $amount, $expiration_date);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Registration failed: ' . $stmt->error);
            }
            file_put_contents(__DIR__ . '/debug.log', 'Student inserted' . PHP_EOL, FILE_APPEND);

            // Generate registration number
            $year = date('Y');
            $class_type = strtoupper($form_type); // MORNING or AFTERNOON
            // Get the last serial for this year and class type
            if ($form_type === 'morning') {
                $serial_sql = "SELECT COUNT(*) as count FROM morning_students WHERE YEAR(created_at) = ?";
                $serial_stmt = $conn->prepare($serial_sql);
                $serial_stmt->bind_param('i', $year);
            } else {
                $serial_sql = "SELECT COUNT(*) as count FROM afternoon_students WHERE YEAR(created_at) = ?";
                $serial_stmt = $conn->prepare($serial_sql);
                $serial_stmt->bind_param('i', $year);
            }
            $serial_stmt->execute();
            $serial_result = $serial_stmt->get_result();
            $serial_row = $serial_result->fetch_assoc();
            $serial = $serial_row ? ((int)$serial_row['count']) : 0;
            $serial++;
            $reg_number = sprintf('%s/%s/%04d', $year, $class_type, $serial);
            $serial_stmt->close();
            file_put_contents(__DIR__ . '/debug.log', 'Reg number generated: ' . $reg_number . PHP_EOL, FILE_APPEND);

            // Update the student record with the reg number
            if ($form_type === 'morning') {
                $update_sql = "UPDATE morning_students SET reg_number = ? WHERE email = ? AND payment_reference = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('sss', $reg_number, $email, $reference);
            } else {
                $update_sql = "UPDATE afternoon_students SET reg_number = ? WHERE email = ? AND payment_reference = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('sss', $reg_number, $email, $reference);
            }
            $update_stmt->execute();
            $update_stmt->close();
            file_put_contents(__DIR__ . '/debug.log', 'Reg number updated in DB' . PHP_EOL, FILE_APPEND);

            // Prepare user data for receipt
            $user = [
                'fullname' => $fullname,
                'email' => $email,
                'phone' => $phone,
                'department' => $department
            ];

            // Prepare payment details for receipt
            $payment_details = [
                'reference' => $reference,
                'payment_type' => $payment_type,
                'amount' => $amount,
                'expiration_date' => $expiration_date,
                'registration_date' => date('Y-m-d')
            ];

            // Generate receipt
            $receipt = new Receipt();
            $receipt->generateReceipt($user, $payment_details, $form_type === 'morning' ? 'morning_students' : 'afternoon_students');
            $receipt_filename = 'receipt_' . $reference . '.pdf';
            $receipt_path = $uploads_dir . '/' . $receipt_filename;
            $receipt->Output('F', $receipt_path);
            file_put_contents(__DIR__ . '/debug.log', 'Receipt generated: ' . $receipt_filename . PHP_EOL, FILE_APPEND);

            // If it's a cash payment (pre-generated reference), generate enhanced receipt with QR
            if (is_array($payment_verification)) {
                require_once '../admin/payment/generate_cash_receipt.php';
                
                $payment_data = [
                    'fullname' => $fullname,
                    'department' => $department,
                    'reg_number' => $reference,
                    'payment_reference' => $reference,
                    'session_type' => $form_type,
                    'payment_type' => $payment_type,
                    'payment_amount' => $amount,
                    'class' => isset($class) ? $class : '',
                    'school' => isset($school) ? $school : '',
                    'registration_date' => date('Y-m-d'),
                    'expiration_date' => $expiration_date
                ];
                
                $enhanced_receipt_filename = generateCashPaymentReceipt($payment_data);
                $receipt_filename = $enhanced_receipt_filename; // Use the enhanced receipt
                file_put_contents(__DIR__ . '/debug.log', 'Enhanced receipt generated: ' . $receipt_filename . PHP_EOL, FILE_APPEND);
            }

            // Return success with the correct relative path and reg number
            file_put_contents(__DIR__ . '/debug.log', 'About to return JSON success' . PHP_EOL, FILE_APPEND);
            
            // Clear any existing output
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Registration completed successfully',
                'receipt_url' => 'uploads/' . $receipt_filename,
                'reg_number' => $reg_number
            ]);
            exit;
            
        } catch (Exception $e) {
            // Log the error
            error_log("Registration error: " . $e->getMessage());
            
            // Clear any existing output
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Send JSON error response
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error_type' => 'registration_error'
            ]);
            exit;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
            if (isset($conn)) {
                $conn->close();
            }
        }
    } else {
        // Clear any existing output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send JSON error response for invalid method
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request method',
            'error_type' => 'method_error'
        ]);
        exit;
    }
} catch (Exception $e) {
    // Log the error
    error_log("Global error: " . $e->getMessage());
    
    // Clear any existing output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send JSON error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'error_type' => 'global_error'
    ]);
    exit;
}
?> 