<?php
include '../confg.php';
require_once 'check_account_status.php';
require_once 'generate_receipt.php';

header('Content-Type: application/json');

// Custom error handler class
class ReactivationError extends Exception {
    private $error_type;

    public function __construct($message, $error_type = 'general') {
        parent::__construct($message);
        $this->error_type = $error_type;
    }

    public function getErrorType() {
        return $this->error_type;
    }
}

// Verify Paystack payment
function verifyPaystackPayment($reference) {
    try {
        $curl = curl_init();
        if (!$curl) {
            throw new ReactivationError("Failed to initialize payment verification", "payment");
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "authorization: Bearer sk_test_ba85c77b3ea04ae33627b38ca46cf8e3b5a4edc5",
                "cache-control: no-cache"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            error_log("Paystack API Error: " . $err);
            throw new ReactivationError("Payment verification failed: " . $err, "payment");
        }

        if ($httpCode !== 200) {
            error_log("Paystack API HTTP Error: " . $httpCode . ", Response: " . $response);
            throw new ReactivationError("Payment verification failed with status code: " . $httpCode, "payment");
        }

        $tranx = json_decode($response);
        if (!$tranx || !isset($tranx->status)) {
            error_log("Invalid Paystack API Response: " . $response);
            throw new ReactivationError("Invalid payment verification response", "payment");
        }

        if (!$tranx->status || $tranx->data->status !== 'success') {
            error_log("Payment Not Successful. Status: " . ($tranx->data->status ?? 'unknown'));
            throw new ReactivationError("Payment verification failed: Transaction not successful", "payment");
        }

        // Verify amount
        $expected_amount = $_POST['amount'] * 100; // Convert to kobo
        if ($tranx->data->amount !== $expected_amount) {
            error_log("Amount Mismatch. Expected: {$expected_amount}, Got: {$tranx->data->amount}");
            throw new ReactivationError("Payment amount verification failed", "payment");
        }

        return true;
    } catch (Exception $e) {
        error_log("Payment verification error: " . $e->getMessage());
        throw new ReactivationError("Payment verification failed: " . $e->getMessage(), "payment");
    }
}

// Validate input parameters
function validateInput($data) {
    $required_fields = ['reference', 'table', 'email', 'payment_type', 'amount'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        throw new ReactivationError("Missing required fields: " . implode(', ', $missing_fields), "validation");
    }

    // Validate table name
    if (!in_array($data['table'], ['morning_students', 'afternoon_students'])) {
        throw new ReactivationError("Invalid table name", "validation");
    }

    // Validate payment type
    if (!in_array($data['payment_type'], ['full', 'half'])) {
        throw new ReactivationError("Invalid payment type", "validation");
    }

    // Validate amount based on table and payment type
    $expected_amount = ($data['table'] === 'morning_students') 
        ? ($data['payment_type'] === 'full' ? 10000 : 5200)
        : ($data['payment_type'] === 'full' ? 4000 : 2200);

    if ((float)$data['amount'] !== (float)$expected_amount) {
        error_log("Amount Mismatch. Expected: {$expected_amount}, Got: {$data['amount']}");
        throw new ReactivationError("Invalid payment amount", "validation");
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new ReactivationError("Invalid email format", "validation");
    }

    // Validate reference format
    if (!preg_match('/^(MORNING|AFTERNOON)_\d+_\d+$/', $data['reference'])) {
        throw new ReactivationError("Invalid payment reference format", "validation");
    }
}

// Handle the reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->begin_transaction();

        // Validate input
        validateInput($_POST);

        $reference = $_POST['reference'];
        $table = $_POST['table'];
        $email = $_POST['email'];
        $payment_type = $_POST['payment_type'];
        $amount = $_POST['amount'];

        // Verify payment first
        verifyPaystackPayment($reference);

        // Calculate new expiration date
        $expiration_date = calculateExpirationDate($payment_type);

        // Get user information
        $stmt = $conn->prepare("SELECT * FROM $table WHERE email = ? FOR UPDATE");
        if (!$stmt) {
            throw new ReactivationError("Database error: " . $conn->error, "database");
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new ReactivationError("Failed to fetch user data: " . $stmt->error, "database");
        }

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new ReactivationError("User not found", "database");
        }

        // Update the account
        $sql = "UPDATE $table SET 
                is_active = TRUE,
                payment_reference = ?,
                payment_type = ?,
                payment_amount = ?,
                expiration_date = ?
                WHERE email = ? AND is_active = FALSE";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new ReactivationError("Database error: " . $conn->error, "database");
        }

        $stmt->bind_param("ssdss", $reference, $payment_type, $amount, $expiration_date, $email);
        
        if (!$stmt->execute()) {
            throw new ReactivationError("Failed to update account: " . $stmt->error, "database");
        }

        if ($stmt->affected_rows === 0) {
            throw new ReactivationError("Account not found or already active", "database");
        }

        // Prepare payment details for receipt
        $payment_details = [
            'reference' => $reference,
            'payment_type' => $payment_type,
            'amount' => $amount,
            'expiration_date' => $expiration_date
        ];

        $receipt_info = null;
        try {
            // Generate and save receipt
            $receipt_info = generateAndSaveReceipt($user, $payment_details, $table);
        } catch (Exception $e) {
            // Log receipt generation error but don't fail the reactivation
            error_log("Receipt generation error: " . $e->getMessage());
        }

        // Commit transaction
        $conn->commit();

        // Return success response with receipt information
        echo json_encode([
            'status' => 'success',
            'message' => 'Account reactivated successfully',
            'data' => [
                'expiration_date' => $expiration_date,
                'payment_reference' => $reference,
                'receipt_url' => $receipt_info ? $receipt_info['filepath'] : null
            ]
        ]);
        
    } catch (ReactivationError $e) {
        // Rollback transaction on error
        if (isset($conn) && !$conn->connect_error) {
            $conn->rollback();
        }

        error_log("Reactivation error ({$e->getErrorType()}): " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error_type' => $e->getErrorType(),
            'message' => $e->getMessage()
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn) && !$conn->connect_error) {
            $conn->rollback();
        }

        error_log("Unexpected error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error_type' => 'system',
            'message' => 'An unexpected error occurred. Please try again later.'
        ]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
        if (isset($conn) && !$conn->connect_error) {
            $conn->close();
        }
    }
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error_type' => 'method',
        'message' => 'Invalid request method'
    ]);
}
?> 