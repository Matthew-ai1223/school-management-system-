<?php
require_once 'vendor/autoload.php';

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use BaconQrCode\Common\ErrorCorrectionLevel;

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

// Function to generate QR code for a student
function generateStudentQR($studentData) {
    // Create a unique filename for the QR code
    $filename = 'qrcodes/' . md5($studentData['fullname'] . time()) . '.svg';
    
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
    
    // Create QR code renderer with larger size for better readability
    $renderer = new ImageRenderer(
        new RendererStyle(400, 4),  // Size 400px, margin 4 modules
        new SvgImageBackEnd()
    );
    
    // Create writer instance with UTF-8 encoding and high error correction
    $writer = new Writer($renderer);
    
    try {
        // Generate and save QR code with explicit UTF-8 encoding
        $qrCode = $writer->writeString($qrData, 'UTF-8', ErrorCorrectionLevel::Q());
        file_put_contents($filename, $qrCode);
    } catch (Exception $e) {
        // If UTF-8 fails, try with basic ASCII encoding
        $qrData = iconv('UTF-8', 'ASCII//TRANSLIT', $qrData);
        $qrCode = $writer->writeString($qrData);
        file_put_contents($filename, $qrCode);
    }
    
    return $filename;
} 