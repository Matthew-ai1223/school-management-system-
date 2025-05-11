<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../payment_config.php';

if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];
    
    error_log("Payment verification started for reference: " . $reference);
    
    // Initialize database connection
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        error_log("Database connection successful");
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        header("Location: payment.php?payment_status=failed&error=db_connection");
        exit();
    }
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verify the transaction
    $url = PAYSTACK_VERIFY_URL . rawurlencode($reference);
    error_log("Verifying payment at URL: " . $url);
    
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Cache-Control: no-cache'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    
    if(curl_errno($ch)) {
        error_log("Paystack API Error: " . curl_error($ch));
        header("Location: payment.php?payment_status=failed&error=api_error");
        exit();
    }
    curl_close($ch);
    
    $result = json_decode($response, true);
    error_log("Paystack API Response: " . print_r($result, true));
    
    if ($result['status'] && $result['data']['status'] === 'success') {
        error_log("Payment verification successful");
        
        // Update payment status in database
        $sql = "UPDATE application_payments SET 
                status = ?,
                payment_date = NOW(),
                transaction_reference = ?
                WHERE reference = ?";
        
        $status = PAYMENT_STATUS_COMPLETED;
        $transaction_ref = $result['data']['reference'];
        
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement: " . $conn->error);
            }
            
            $stmt->bind_param("sss", $status, $transaction_ref, $reference);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update payment record: " . $stmt->error);
            }
            
            error_log("Payment status updated in database");
            
            // Get application type from payment record
            $sql = "SELECT application_type FROM application_payments WHERE reference = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();
            
            if ($payment) {
                // Set session variables
                $_SESSION['payment_verified'] = true;
                $_SESSION['payment_reference'] = $reference;
                $_SESSION['application_type'] = $payment['application_type'];
                
                error_log("Redirecting to application form with type: " . $payment['application_type']);
                
                // Redirect to application form
                $redirect_url = "application_form.php?type=" . urlencode($payment['application_type']) . 
                              "&reference=" . urlencode($reference) . 
                              "&payment_status=success";
                              
                error_log("Redirect URL: " . $redirect_url);
                header("Location: " . $redirect_url);
                exit();
            } else {
                throw new Exception("Payment record not found after update");
            }
        } catch (Exception $e) {
            error_log("Database Error: " . $e->getMessage());
            header("Location: payment.php?payment_status=failed&error=db_error");
            exit();
        }
    } else {
        error_log("Payment verification failed. Status: " . ($result['status'] ? 'true' : 'false') . 
                 ", Data status: " . ($result['data']['status'] ?? 'not set'));
        
        // Update payment status as failed
        $sql = "UPDATE application_payments SET status = ? WHERE reference = ?";
        $status = PAYMENT_STATUS_FAILED;
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $status, $reference);
        $stmt->execute();
        
        // Get application type for redirection
        $sql = "SELECT application_type FROM application_payments WHERE reference = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        // Redirect with error
        $redirect_url = "payment.php?payment_status=failed";
        if ($payment) {
            $redirect_url .= "&type=" . urlencode($payment['application_type']);
        }
        header("Location: " . $redirect_url);
        exit();
    }
} else {
    error_log("No reference provided in verification request");
    header("Location: payment.php?payment_status=invalid");
    exit();
}
?> 