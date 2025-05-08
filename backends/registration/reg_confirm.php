<?php
// Registration Confirmation Page
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';
require_once '../auth.php';

// Check if user was redirected with registration data
if (!isset($_SESSION['registration_success']) || !$_SESSION['registration_success']) {
    // Redirect to registration page if not coming from successful registration
    header('Location: students_reg.php');
    exit;
}

// Get registration data from session
$successMessage = $_SESSION['success_message'] ?? 'Registration successful! You can now login with your credentials.';
$generatedUsername = $_SESSION['generated_username'] ?? '';
$generatedPassword = $_SESSION['generated_password'] ?? '';

// Get all registration data for receipt
$studentData = $_SESSION['student_data'] ?? [];

// Check for receipt generation errors
$receiptError = $_SESSION['receipt_error'] ?? '';

// Check for error parameters from download script
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'pdf_error':
            $receiptError = 'Could not generate PDF receipt. Please try again or contact support.';
            break;
        case 'invalid_token':
            $receiptError = 'Invalid or expired download token. Please try again.';
            break;
        case 'no_data':
            $receiptError = 'No registration data available. Please try registering again.';
            break;
    }
}

// Get token from URL if it exists
$token = $_GET['token'] ?? '';

// Flag to check if we're generating a receipt
$generateReceipt = isset($_GET['generate_receipt']) && $_GET['generate_receipt'] == 'true';

if ($generateReceipt) {
    // Generate and download receipt
    generateReceipt($studentData);
    exit;
}

// This function generates a receipt and forces download
function generateReceipt($studentData) {
    global $_SESSION;
    
    // If no data is available, redirect back
    if (empty($studentData)) {
        $_SESSION['receipt_error'] = 'No student data available for receipt generation.';
        header('Location: reg_confirm.php');
        exit;
    }
    
    // Ensure the download script opens in the same window, not as a redirect
    echo '<script>
        window.location.href = "download_receipt.php";
    </script>';
    exit;
}

// Generate PDF receipt using FPDF
function generatePdfReceipt($studentData) {
    try {
        // Define access constant to protect the receipt files
        define('ALLOW_ACCESS', true);
        
        // First, try to use our simple PDF generator (more reliable)
        require_once '../pdf/custom_pdf/simple_pdf.php';
        
        // Generate the PDF receipt using the simple generator
        return generateSimplePDF($studentData);
        
    } catch (Exception $e) {
        // Log the error
        error_log('PDF Receipt Generation Error: ' . $e->getMessage());
        $_SESSION['receipt_error'] = 'PDF Receipt Error: ' . $e->getMessage();
        return false;
    }
}

// Generate text receipt as fallback
function generateTextReceipt($studentData) {
    // Set headers for a text file download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="registration_receipt.txt"');
    
    $output = "=========================================\n";
    $output .= "            " . APP_NAME . "\n";
    $output .= "       REGISTRATION RECEIPT\n";
    $output .= "=========================================\n\n";
    $output .= "Date: " . date('F j, Y') . "\n\n";
    
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
    
    $output .= "PARENT/GUARDIAN INFORMATION\n";
    $output .= "---------------------------\n";
    $output .= "Name: " . ($studentData['parent_name'] ?? 'N/A') . "\n";
    $output .= "Phone: " . ($studentData['parent_phone'] ?? 'N/A') . "\n";
    $output .= "Email: " . ($studentData['parent_email'] ?? 'N/A') . "\n";
    $output .= "Address: " . ($studentData['parent_address'] ?? 'N/A') . "\n\n";
    
    $output .= "LOGIN CREDENTIALS\n";
    $output .= "-----------------\n";
    $output .= "Username: " . $studentData['username'] . "\n";
    $output .= "Password/Registration Number: " . $studentData['password'] . "\n\n";
    
    $output .= "Please keep this receipt for your records.\n";
    $output .= "The login credentials provided above will be needed to access your student account.\n\n";
    $output .= "Welcome to " . APP_NAME . "!\n";
    $output .= "=========================================\n";
    
    echo $output;
}

// Clear session variables after retrieving them
// Only clear them if we are not generating a receipt
if (!$generateReceipt) {
    // Don't unset student_data because we need it for the PDF generation
    // We'll let the download script handle that if needed
    unset($_SESSION['registration_success']);
    unset($_SESSION['success_message']);
    unset($_SESSION['receipt_error']);
    
    // Keep the credentials in session since they're shown on the page
    // They'll be destroyed when the session ends
}

// Page title
$pageTitle = "Registration Confirmation";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 20px;
        }
        
        .success-card {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .credentials-box {
            background-color: #f8f9fa;
            border: 1px dashed #6c757d;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .receipt-btn {
            background-color: #28a745;
            border-color: #28a745;
            font-weight: bold;
            padding: 8px 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .receipt-btn:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="text-center mb-4">
                    <img src="../../images/logo.png" alt="<?php echo APP_NAME; ?> Logo" class="img-fluid mb-3" style="max-height: 100px;">
                    <h2><?php echo APP_NAME; ?></h2>
                    <h4>Registration Confirmation</h4>
                </div>
                
                <div class="success-card">
                    <h5><i class="fas fa-check-circle text-success me-2"></i>Registration Successful!</h5>
                    <p><?php echo $successMessage; ?></p>
                    
                    <?php if (!empty($receiptError)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $receiptError; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="credentials-box">
                        <h6>Your Login Credentials:</h6>
                        <p><strong>Username:</strong> <?php echo $generatedUsername; ?></p>
                        <p><strong>Password/Admission Number:</strong> <?php echo $generatedPassword; ?></p>
                        <div class="alert alert-warning">
                            <small><i class="fas fa-exclamation-triangle me-1"></i> Please save these credentials. You will need them to login.</small>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="../../login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-1"></i> Login Now
                        </a>
                        <a href="students_reg.php" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-user-plus me-1"></i> Register Another Student
                        </a>
                        <button class="btn receipt-btn text-white ms-2" id="downloadReceiptBtn">
                            <i class="fas fa-file-download me-1"></i> Download Receipt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Receipt Download Script -->
    <script>
        // Auto-download receipt when page loads
        window.addEventListener('DOMContentLoaded', (event) => {
            // Wait a moment to ensure the page is fully loaded
            setTimeout(function() {
                // Try to auto-download after a slight delay
                // Use a popup window to avoid blocking the main page
                try {
                    window.open('download_receipt.php', '_blank');
                } catch(e) {
                    console.error('Auto-download failed:', e);
                    // Let the user know if popup is blocked
                    alert('Please use the Download Receipt button below to get your receipt.');
                }
            }, 1500);
        });
        
        // Manual download button click handler
        document.getElementById('downloadReceiptBtn').addEventListener('click', function(e) {
            e.preventDefault();
            // Open in new window to avoid navigation away from confirmation page
            window.open('download_receipt.php', '_blank');
        });
    </script>
</body>
</html>
