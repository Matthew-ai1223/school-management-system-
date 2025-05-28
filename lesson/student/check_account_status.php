<?php
include '../confg.php';

function checkAndUpdateAccountStatus($conn) {
    // Get all active accounts
    $tables = ['morning_students', 'afternoon_students'];
    
    foreach ($tables as $table) {
        // Get all active accounts that have expired
        $sql = "SELECT id, email, fullname, expiration_date FROM $table 
                WHERE is_active = TRUE AND expiration_date < CURDATE()";
        
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Deactivate the account
                $update_sql = "UPDATE $table SET is_active = FALSE WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
                $stmt->close();
                
                // You could add email notification here
                // sendExpirationEmail($row['email'], $row['fullname']);
            }
        }
    }
}

// Function to calculate expiration date based on payment type
function calculateExpirationDate($payment_type) {
    $expiration_date = new DateTime();
    
    if ($payment_type === 'full') {
        // Full payment expires in 1 month
        $expiration_date->modify('+1 month');
    } else {
        // Half payment expires in 15 days
        $expiration_date->modify('+15 days');
    }
    
    return $expiration_date->format('Y-m-d');
}

// Function to check if an account is active
function isAccountActive($conn, $user_id, $user_table) {
    $sql = "SELECT is_active, expiration_date FROM $user_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) {
        return false;
    }
    
    // Check both is_active flag and expiration date
    return $row['is_active'] && strtotime($row['expiration_date']) >= strtotime('today');
}

// Function to get days remaining until expiration
function getDaysRemaining($conn, $user_id, $user_table) {
    $sql = "SELECT expiration_date FROM $user_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) {
        return 0;
    }
    
    $expiration = new DateTime($row['expiration_date']);
    $today = new DateTime();
    $interval = $today->diff($expiration);
    
    return $interval->days;
}
?> 