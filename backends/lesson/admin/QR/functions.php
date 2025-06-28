<?php
require_once __DIR__ . '/phpqrcode.php';

// Function to format date to a more readable format
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('F j, Y g:i A'); // Example: May 28, 2025 12:17 AM
}

// Function to format amount with currency
function formatAmount($amount) {
    return 'NGN ' . number_format((float)$amount, 2); // Using NGN instead of ₦ symbol
}

// Function to determine account status
function getAccountStatus($expirationDate) {
    $today = new DateTime();
    $expiration = new DateTime($expirationDate);
    
    if ($today > $expiration) {
        return "Expired";
    } else {
        $diff = $today->diff($expiration);
        if ($diff->days <= 7) {
            return "Expiring Soon";
        }
        return "Active";
    }
}

// Function to generate a simple text-based QR code pattern
function generateSimplePattern($text) {
    $pattern = array();
    $size = 20; // Size of the pattern
    
    // Initialize pattern with zeros
    for ($i = 0; $i < $size; $i++) {
        $pattern[$i] = array_fill(0, $size, 0);
    }
    
    // Add fixed patterns (finder patterns)
    // Top-left finder pattern
    for ($i = 0; $i < 7; $i++) {
        for ($j = 0; $j < 7; $j++) {
            if ($i == 0 || $i == 6 || $j == 0 || $j == 6 || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)) {
                $pattern[$i][$j] = 1;
            }
        }
    }
    
    // Top-right finder pattern
    for ($i = 0; $i < 7; $i++) {
        for ($j = $size - 7; $j < $size; $j++) {
            if ($i == 0 || $i == 6 || $j == $size - 7 || $j == $size - 1 || ($i >= 2 && $i <= 4 && $j >= $size - 5 && $j <= $size - 3)) {
                $pattern[$i][$j] = 1;
            }
        }
    }
    
    // Bottom-left finder pattern
    for ($i = $size - 7; $i < $size; $i++) {
        for ($j = 0; $j < 7; $j++) {
            if ($i == $size - 7 || $i == $size - 1 || $j == 0 || $j == 6 || ($i >= $size - 5 && $i <= $size - 3 && $j >= 2 && $j <= 4)) {
                $pattern[$i][$j] = 1;
            }
        }
    }
    
    // Add some data pattern based on text length
    $textLen = strlen($text);
    for ($i = 8; $i < $size - 8; $i++) {
        for ($j = 8; $j < $size - 8; $j++) {
            if (($i + $j + $textLen) % 3 == 0) {
                $pattern[$i][$j] = 1;
            }
        }
    }
    
    return $pattern;
}

// Function to generate QR code for a student
function generateStudentQR($studentData) {
    // Create a unique filename for the QR code
    $filename = 'qrcodes/' . md5($studentData['fullname'] . time()) . '.png';

    // Create the directory if it doesn't exist
    if (!file_exists('qrcodes')) {
        mkdir('qrcodes', 0777, true);
    }

    // Format the dates
    $registrationDate = formatDate($studentData['registration_date']);
    $expirationDate = formatDate($studentData['expiration_date']);

    // Get account status
    $accountStatus = getAccountStatus($studentData['expiration_date']);

    // Format the payment amount
    $formattedAmount = formatAmount($studentData['payment_amount']);

    // Prepare the data to be encoded in QR code with a more readable format
    $qrData = "STUDENT INFORMATION\n" .
              "==================\n" .
              "Name: " . $studentData['fullname'] . "\n" .
              "Department: " . ucfirst($studentData['department']) . "\n" .
              "Reg Number: " . $studentData['reg_number'] . "\n" .
              "Status: " . $accountStatus . "\n" .
              "\nPAYMENT DETAILS\n" .
              "==================\n" .
              "Reference: " . $studentData['payment_reference'] . "\n" .
              "Type: " . ucfirst($studentData['payment_type']) . " Payment\n" .
              "Amount: " . $formattedAmount . "\n" .
              "\nDATES\n" .
              "==================\n" .
              "Registered: " . $registrationDate . "\n" .
              "Expires: " . $expirationDate;

    // Use GoQR.me API to generate QR code
    $url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => '300x300',
        'data' => $qrData,
        'format' => 'png',
        'charset-source' => 'UTF-8',
        'charset-target' => 'UTF-8',
        'ecc' => 'L',
        'color' => '0-0-0',
        'bgcolor' => '255-255-255',
        'margin' => '1',
        'qzone' => '1'
    ]);

    // Download the QR code image
    $qrImage = @file_get_contents($url);
    if ($qrImage !== false) {
        if (file_put_contents($filename, $qrImage)) {
            return $filename;
        }
    }
    
    error_log('Failed to generate QR code for student: ' . $studentData['reg_number']);
    return false;
} 