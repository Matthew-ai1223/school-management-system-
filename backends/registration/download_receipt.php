<?php
// Receipt Download Script
// This script handles downloading the registration receipt without requiring a session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';
require_once '../auth.php';

// Log function for debugging
function logDebug($message) {
    error_log('[Download Receipt Debug] ' . $message);
}

logDebug('Script started');

// Define temporary storage directory for receipt data
$tempDir = __DIR__ . '/../pdf/temp';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0755, true);
    logDebug('Created temp directory: ' . $tempDir);
}

// Function to clean up old receipt data files (older than 1 hour)
function cleanupOldFiles($dir) {
    $files = glob($dir . '/*.json');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file) > 3600)) { // 3600 seconds = 1 hour
            unlink($file);
        }
    }
}

// Clean up old files
cleanupOldFiles($tempDir);

// Output text receipt as fallback
function outputTextReceipt($studentData) {
    global $APP_NAME;
    
    // Set headers for text file download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="registration_receipt.txt"');
    
    $output = "=========================================\n";
    $output .= "            " . APP_NAME . "\n";
    $output .= "       REGISTRATION RECEIPT\n";
    $output .= "=========================================\n\n";
    $output .= "Date: " . date('F j, Y') . "\n\n";
    
    // Student Information
    $output .= "STUDENT INFORMATION\n";
    $output .= "--------------------\n";
    $output .= "Name: " . $studentData['first_name'] . " " . $studentData['last_name'] . "\n";
    $output .= "Email: " . $studentData['email'] . "\n";
    $output .= "Date of Birth: " . $studentData['date_of_birth'] . "\n";
    $output .= "Gender: " . $studentData['gender'] . "\n";
    $output .= "Phone: " . ($studentData['phone'] ?? 'N/A') . "\n";
    $output .= "Address: " . ($studentData['address'] ?? 'N/A') . "\n";
    
    // Handle class display correctly
    if (!empty($studentData['class_name'])) {
        $output .= "Class: " . $studentData['class_name'] . "\n";
    } else if (!empty($studentData['class_type'])) {
        $output .= "Class: " . $studentData['class_type'] . "\n";
    } else if (!empty($studentData['class_id'])) {
        $output .= "Class: " . $studentData['class_id'] . "\n";
    } else {
        $output .= "Class: N/A\n";
    }
    
    $output .= "Previous School: " . ($studentData['previous_school'] ?? 'N/A') . "\n\n";
    
    // Parent/Guardian Information
    $output .= "PARENT/GUARDIAN INFORMATION\n";
    $output .= "---------------------------\n";
    $output .= "Name: " . ($studentData['parent_name'] ?? 'N/A') . "\n";
    $output .= "Phone: " . ($studentData['parent_phone'] ?? 'N/A') . "\n";
    $output .= "Email: " . ($studentData['parent_email'] ?? 'N/A') . "\n";
    $output .= "Address: " . ($studentData['parent_address'] ?? 'N/A') . "\n\n";
    
    // Login Credentials
    $output .= "LOGIN CREDENTIALS\n";
    $output .= "-----------------\n";
    $output .= "Username: " . $studentData['username'] . "\n";
    $output .= "Password/Registration Number: " . $studentData['password'] . "\n\n";
    
    $output .= "Congratulations on your registration!. Note that you are to bring two copies of this receipt to the school office to complete your registration.\n";
    $output .= "The login credentials provided above will be needed to access your student account.\n\n";
    $output .= "=========================================\n";
    
    echo $output;
    exit;
}

// Use FPDF directly if everything else fails
function generateDirectPDF($studentData) {
    // Include FPDF library
    require_once '../../fpdf_temp/fpdf.php';
    
    // Create PDF instance
    $pdf = new FPDF('P', 'mm', 'A4');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('Arial', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, APP_NAME, 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'STUDENT REGISTRATION RECEIPT', 0, 1, 'C');
    
    // Date
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Date: ' . date('F j, Y'), 0, 1, 'R');
    
    // Add a line
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);
    
    // Get the admission/registration number - handle different possible key names
    $admissionNumber = '';
    if (isset($studentData['admission_number'])) {
        $admissionNumber = $studentData['admission_number'];
    } elseif (isset($studentData['registration_number'])) {
        $admissionNumber = $studentData['registration_number'];
    } elseif (isset($studentData['password'])) {
        // Use password as a fallback since it's often used as the registration number
        $admissionNumber = $studentData['password'];
    }
    
    // Registration Number
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, 'ADMISSION/REGISTRATION NUMBER: ' . $admissionNumber, 1, 1, 'C', true);
    $pdf->Ln(5);
    
    // Student Information header
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'STUDENT INFORMATION', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    
    // Create student info table
    $pdf->SetFillColor(245, 245, 245);
    
    // Student name
    $pdf->Cell(60, 8, 'Name:', 1, 0, 'L');
    $pdf->Cell(130, 8, $studentData['first_name'] . ' ' . $studentData['last_name'], 1, 1, 'L', true);
    
    // Login information
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'LOGIN CREDENTIALS', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    
    $pdf->Cell(60, 8, 'Username:', 1, 0, 'L');
    $pdf->Cell(130, 8, $studentData['username'], 1, 1, 'L', true);
    
    $pdf->Cell(60, 8, 'Password:', 1, 0, 'L');
    $pdf->Cell(130, 8, $studentData['password'], 1, 1, 'L');
    
    // Output PDF
    $pdf->Output('D', 'registration_receipt.pdf');
    exit;
}

// Check if we have a token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $dataFile = $tempDir . '/' . $token . '.json';
    
    logDebug('Using token: ' . $token);
    
    // Check if the data file exists
    if (file_exists($dataFile)) {
        logDebug('Data file exists: ' . $dataFile);
        
        // Get the student data from the file
        $studentData = json_decode(file_get_contents($dataFile), true);
        
        // Process the receipt download
        if (!empty($studentData)) {
            logDebug('Student data found in token file');
            
            // Define access constant to protect the receipt files
            define('ALLOW_ACCESS', true);
            
            try {
                // Try with custom PDF generator first
                if (file_exists('../pdf/custom_pdf/simple_pdf.php')) {
                    require_once '../pdf/custom_pdf/simple_pdf.php';
                    logDebug('Included PDF generator');
                    
                    // Generate and download the PDF receipt
                    generateSimplePDF($studentData);
                } else {
                    // Fallback to direct FPDF
                    logDebug('Using direct FPDF generation');
                    generateDirectPDF($studentData);
                }
                exit;
            } catch (Exception $e) {
                // Log error
                $errorMsg = 'PDF Receipt Generation Error: ' . $e->getMessage();
                logDebug($errorMsg);
                error_log($errorMsg);
                
                // Try direct PDF generation
                try {
                    generateDirectPDF($studentData);
                    exit;
                } catch (Exception $e2) {
                    // Fall back to text receipt
                    logDebug('Falling back to text receipt: ' . $e2->getMessage());
                    outputTextReceipt($studentData);
                }
            }
        } else {
            logDebug('Student data is empty in token file');
        }
    } else {
        logDebug('Data file does not exist: ' . $dataFile);
    }
    
    // If we get here, the token was invalid or expired
    echo '<script>alert("Invalid or expired token. Please try again."); window.location.href = "reg_confirm.php?error=invalid_token";</script>';
    exit;
}

// Check if we have student data in the session (this is for the first download after registration)
elseif (isset($_SESSION['student_data']) && !empty($_SESSION['student_data'])) {
    $studentData = $_SESSION['student_data'];
    logDebug('Found student data in session');
    
    // Generate a unique token
    $token = md5(uniqid(rand(), true));
    $dataFile = $tempDir . '/' . $token . '.json';
    
    // Save the student data to the temporary file
    $saveResult = file_put_contents($dataFile, json_encode($studentData));
    
    if ($saveResult === false) {
        logDebug('Failed to save student data to file: ' . $dataFile);
        // Try to create directory again
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
    } else {
        logDebug('Saved student data to file: ' . $dataFile);
    }
    
    // Define access constant to protect the receipt files
    define('ALLOW_ACCESS', true);
    
    try {
        // Try with custom PDF generator first
        if (file_exists('../pdf/custom_pdf/simple_pdf.php')) {
            // Include the PDF generator
            require_once '../pdf/custom_pdf/simple_pdf.php';
            logDebug('Included PDF generator');
            
            // Generate and download the PDF receipt
            generateSimplePDF($studentData);
        } else {
            // Fallback to direct FPDF
            logDebug('Using direct FPDF generation');
            generateDirectPDF($studentData);
        }
        exit;
    } catch (Exception $e) {
        // Log error
        $errorMsg = 'PDF Receipt Generation Error: ' . $e->getMessage();
        logDebug($errorMsg);
        error_log($errorMsg);
        
        // Try direct PDF generation
        try {
            generateDirectPDF($studentData);
            exit;
        } catch (Exception $e2) {
            // Fall back to text receipt
            logDebug('Falling back to text receipt: ' . $e2->getMessage());
            outputTextReceipt($studentData);
        }
    }
} else {
    logDebug('No student data found in session');
}

// If we get here, we don't have a valid token or session data
// Instead of redirecting, show a helpful message with a link
echo '<!DOCTYPE html>
<html>
<head>
    <title>Receipt Download - ' . APP_NAME . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        h2 { color: #333; }
        .error { color: #d9534f; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #5cb85c; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Receipt Download</h2>
        <p class="error">No student data found to generate receipt.</p>
        <p>This could happen if:</p>
        <ul style="text-align: left;">
            <li>Your session has expired</li>
            <li>You haven\'t completed registration yet</li>
            <li>The receipt was already downloaded and session data was cleared</li>
        </ul>
        <p>Please try the following:</p>
        <a href="reg_confirm.php" class="btn">Return to Confirmation Page</a>
    </div>
</body>
</html>';
exit; 