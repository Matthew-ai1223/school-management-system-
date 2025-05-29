<?php
// Database connection
include '../confg.php';
require_once 'check_account_status.php';
require_once 'generate_receipt.php';

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
    
    // First check if it's a pre-generated reference number
    $check_sql = "SELECT * FROM reference_numbers WHERE reference_number = ? AND session_type = ? AND is_used = 0";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $reference, $form_type);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Mark the reference as used
        $update_sql = "UPDATE reference_numbers SET is_used = 1, used_at = CURRENT_TIMESTAMP WHERE reference_number = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("s", $reference);
        $update_stmt->execute();
        
        // Return the pre-generated reference data
        return $result->fetch_assoc();
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
            'expiration_date' => $expiration_date
        ];

        // Generate receipt but don't output it directly
        $pdf = new Receipt();
        $pdf->generateReceipt($user, $payment_details, $form_type === 'morning' ? 'morning_students' : 'afternoon_students');
        
        // Save the PDF to a file in the uploads directory
        $receipt_filename = 'receipt_' . $reference . '.pdf';
        $receipt_path = $uploads_dir . '/' . $receipt_filename;
        $pdf->Output('F', $receipt_path);

        // Return success with the correct relative path
        echo json_encode([
            'status' => 'success', 
            'message' => 'Registration completed successfully',
            'receipt_url' => 'uploads/' . $receipt_filename
        ]);
        
    } catch (Exception $e) {
        error_log('Registration error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
        if (isset($conn)) {
            $conn->close();
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?> 