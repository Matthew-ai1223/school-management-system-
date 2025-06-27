<?php
// Set session configuration for better compatibility with shared hosting
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'ctrl/db_config.php';
require_once 'ctrl/payment_types.php';

// Add session debugging
$session_debug = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE
];

// Test session endpoint
if (isset($_GET['test_session'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'session_debug' => $session_debug_info,
        'student_logged_in' => isset($_SESSION['student_id']) && isset($_SESSION['registration_number']),
        'session_vars' => [
            'student_id' => $_SESSION['student_id'] ?? 'not_set',
            'registration_number' => $_SESSION['registration_number'] ?? 'not_set',
            'student_name' => $_SESSION['student_name'] ?? 'not_set'
        ],
        'session_config' => [
            'session_name' => session_name(),
            'session_save_path' => session_save_path(),
            'session_cookie_params' => session_get_cookie_params(),
            'session_status' => session_status()
        ],
        'server_info' => [
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'not_set',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'not_set',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not_set',
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'not_set'
        ]
    ]);
    exit;
}

// Create all necessary tables if they don't exist
function createPaymentTables($conn) {
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    try {
        // Create payment types table
        $sql = "CREATE TABLE IF NOT EXISTS school_payment_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            min_payment_amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            academic_term VARCHAR(50) NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            throw new Exception("Error creating payment types table: " . $conn->error);
        }

        // Create payments table with base_amount and service_charge
        $sql = "CREATE TABLE IF NOT EXISTS school_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL,
            payment_type_id INT,
            amount DECIMAL(10,2) NOT NULL,
            base_amount DECIMAL(10,2) NOT NULL,
            service_charge DECIMAL(10,2) NOT NULL,
            reference_code VARCHAR(100) UNIQUE,
            payment_status ENUM('Success', 'completed', 'failed') DEFAULT 'completed',
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payment_type_id) REFERENCES school_payment_types(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            throw new Exception("Error creating payments table: " . $conn->error);
        }

        // Create payment receipts table
        $sql = "CREATE TABLE IF NOT EXISTS school_payment_receipts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payment_id INT,
            receipt_number VARCHAR(50) UNIQUE NOT NULL,
            generated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payment_id) REFERENCES school_payments(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            throw new Exception("Error creating payment receipts table: " . $conn->error);
        }

        // Create cash payments table
        $sql = "CREATE TABLE IF NOT EXISTS cash_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL,
            payment_type_id INT,
            amount DECIMAL(10,2) NOT NULL,
            base_amount DECIMAL(10,2) NOT NULL,
            service_charge DECIMAL(10,2) DEFAULT 0.00,
            reference_code VARCHAR(100) UNIQUE,
            payment_status ENUM('Success', 'completed', 'failed') DEFAULT 'completed',
            approval_status ENUM('under_review', 'approved', 'rejected') DEFAULT 'under_review',
            approver_id VARCHAR(50) NULL,
            approver_name VARCHAR(100) NULL,
            approval_date TIMESTAMP NULL,
            bursar_id VARCHAR(50),
            bursar_name VARCHAR(100),
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            receipt_number VARCHAR(50) UNIQUE,
            notes TEXT,
            FOREIGN KEY (payment_type_id) REFERENCES school_payment_types(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            throw new Exception("Error creating cash payments table: " . $conn->error);
        }

        // Add new columns to existing table if they don't exist
        $alterQueries = [
            "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS approval_status ENUM('under_review', 'approved', 'rejected') DEFAULT 'under_review' AFTER payment_status",
            "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS approver_id VARCHAR(50) NULL AFTER approval_status",
            "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS approver_name VARCHAR(100) NULL AFTER approver_id",
            "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS approval_date TIMESTAMP NULL AFTER approver_name"
        ];

        foreach ($alterQueries as $alterQuery) {
            $conn->query($alterQuery);
        }

        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        return true;
    } catch (Exception $e) {
        // Re-enable foreign key checks even if there's an error
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        return false;
    }
}

// Initialize tables
createPaymentTables($conn);

// Function to get student details
function getStudentDetails($reg_number) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM students WHERE registration_number = ?");
    $stmt->bind_param("s", $reg_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    return $student;
}

// Function to get payment history - FIXED: Changed payment_types to school_payment_types
function getPaymentHistory($reg_number) {
    global $conn;
    
    // Get online payments
    $sql_online = "SELECT sp.*, spt.name as payment_type_name, 'Online' as payment_method
            FROM school_payments sp 
            JOIN school_payment_types spt ON sp.payment_type_id = spt.id 
            WHERE sp.student_id = ? 
            ORDER BY sp.payment_date DESC";
            
    $stmt = $conn->prepare($sql_online);
    $stmt->bind_param("s", $reg_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $online_payments = [];
    
    while ($row = $result->fetch_assoc()) {
        $online_payments[] = $row;
    }
    $stmt->close();
    
    // Get cash payments
    $sql_cash = "SELECT cp.*, spt.name as payment_type_name, 'Cash' as payment_method
            FROM cash_payments cp 
            JOIN school_payment_types spt ON cp.payment_type_id = spt.id 
            WHERE cp.student_id = ? 
            ORDER BY cp.payment_date DESC";
            
    $stmt = $conn->prepare($sql_cash);
    $stmt->bind_param("s", $reg_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $cash_payments = [];
    
    while ($row = $result->fetch_assoc()) {
        $cash_payments[] = $row;
    }
    $stmt->close();
    
    // Combine and sort all payments by date
    $all_payments = array_merge($online_payments, $cash_payments);
    
    // Sort by payment date (newest first)
    usort($all_payments, function($a, $b) {
        return strtotime($b['payment_date']) - strtotime($a['payment_date']);
    });
    
    return $all_payments;
}

// Function to calculate total amount to pay for a student
function getTotalAmountToPay($reg_number) {
    global $conn;
    
    // Get only school fees payment types (assuming school fees have specific names or IDs)
    // You can modify this query based on how school fees are identified in your payment_types table
    $sql = "SELECT SUM(amount) as total_amount FROM school_payment_types 
            WHERE is_active = 1 
            AND (name LIKE '%school%fee%' OR name LIKE '%tuition%' OR name LIKE '%academic%fee%' 
                 OR name LIKE '%registration%fee%' OR name LIKE '%session%fee%')";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $total_amount_to_pay = $row['total_amount'] ?? 0;
    
    return $total_amount_to_pay;
}

// Function to calculate total amount to pay for other payments (non-school fees)
function getTotalOtherAmountToPay($reg_number) {
    global $conn;
    
    // Get other payment types (excluding school fees)
    $sql = "SELECT SUM(amount) as total_amount FROM school_payment_types 
            WHERE is_active = 1 
            AND NOT (name LIKE '%school%fee%' OR name LIKE '%tuition%' OR name LIKE '%academic%fee%' 
                     OR name LIKE '%registration%fee%' OR name LIKE '%session%fee%')";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $total_amount_to_pay = $row['total_amount'] ?? 0;
    
    return $total_amount_to_pay;
}

// Function to calculate total amount paid by a student for school fees only
function getTotalSchoolFeesPaid($reg_number) {
    global $conn;
    
    // Get school fees payment types IDs
    $sql_types = "SELECT id FROM school_payment_types 
                  WHERE is_active = 1 
                  AND (name LIKE '%school%fee%' OR name LIKE '%tuition%' OR name LIKE '%academic%fee%' 
                       OR name LIKE '%registration%fee%' OR name LIKE '%session%fee%')";
    $result = $conn->query($sql_types);
    $school_fee_ids = [];
    while ($row = $result->fetch_assoc()) {
        $school_fee_ids[] = $row['id'];
    }
    
    if (empty($school_fee_ids)) {
        return 0;
    }
    
    $placeholders = str_repeat('?,', count($school_fee_ids) - 1) . '?';
    
    // Get total from online payments for school fees (completed only)
    $sql_online = "SELECT SUM(base_amount + service_charge) as total_online 
                   FROM school_payments 
                   WHERE student_id = ? AND payment_status IN ('completed', 'Success') 
                   AND payment_type_id IN ($placeholders)";
    $stmt = $conn->prepare($sql_online);
    $params = array_merge([$reg_number], $school_fee_ids);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_online = $row['total_online'] ?? 0;
    $stmt->close();
    
    // Get total from cash payments for school fees (completed and approved only)
    $sql_cash = "SELECT SUM(base_amount + service_charge) as total_cash 
                 FROM cash_payments 
                 WHERE student_id = ? AND payment_status IN ('completed', 'Success') 
                 AND approval_status = 'approved' AND payment_type_id IN ($placeholders)";
    $stmt = $conn->prepare($sql_cash);
    $params = array_merge([$reg_number], $school_fee_ids);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_cash = $row['total_cash'] ?? 0;
    $stmt->close();
    
    return $total_online + $total_cash;
}

// Function to calculate total amount paid by a student for other payments
function getTotalOtherAmountPaid($reg_number) {
    global $conn;
    
    // Get other payment types IDs (excluding school fees)
    $sql_types = "SELECT id FROM school_payment_types 
                  WHERE is_active = 1 
                  AND NOT (name LIKE '%school%fee%' OR name LIKE '%tuition%' OR name LIKE '%academic%fee%' 
                           OR name LIKE '%registration%fee%' OR name LIKE '%session%fee%')";
    $result = $conn->query($sql_types);
    $other_payment_ids = [];
    while ($row = $result->fetch_assoc()) {
        $other_payment_ids[] = $row['id'];
    }
    
    if (empty($other_payment_ids)) {
        return 0;
    }
    
    $placeholders = str_repeat('?,', count($other_payment_ids) - 1) . '?';
    
    // Get total from online payments for other payments (completed only)
    $sql_online = "SELECT SUM(base_amount + service_charge) as total_online 
                   FROM school_payments 
                   WHERE student_id = ? AND payment_status IN ('completed', 'Success') 
                   AND payment_type_id IN ($placeholders)";
    $stmt = $conn->prepare($sql_online);
    $params = array_merge([$reg_number], $other_payment_ids);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_online = $row['total_online'] ?? 0;
    $stmt->close();
    
    // Get total from cash payments for other payments (completed and approved only)
    $sql_cash = "SELECT SUM(base_amount + service_charge) as total_cash 
                 FROM cash_payments 
                 WHERE student_id = ? AND payment_status IN ('completed', 'Success') 
                 AND approval_status = 'approved' AND payment_type_id IN ($placeholders)";
    $stmt = $conn->prepare($sql_cash);
    $params = array_merge([$reg_number], $other_payment_ids);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_cash = $row['total_cash'] ?? 0;
    $stmt->close();
    
    return $total_online + $total_cash;
}

// Function to calculate total amount paid by a student
function getTotalAmountPaid($reg_number) {
    $school_fees_paid = getTotalSchoolFeesPaid($reg_number);
    $other_paid = getTotalOtherAmountPaid($reg_number);
    return $school_fees_paid + $other_paid;
}

// Function to calculate remaining balance for school fees only
function getRemainingSchoolFeesBalance($reg_number) {
    $total_to_pay = getTotalAmountToPay($reg_number);
    $total_paid = getTotalSchoolFeesPaid($reg_number);
    return $total_to_pay - $total_paid;
}

// Function to calculate remaining balance for other payments
function getRemainingOtherBalance($reg_number) {
    $total_to_pay = getTotalOtherAmountToPay($reg_number);
    $total_paid = getTotalOtherAmountPaid($reg_number);
    return $total_to_pay - $total_paid;
}

// Function to calculate remaining balance
function getRemainingBalance($reg_number) {
    $school_fees_balance = getRemainingSchoolFeesBalance($reg_number);
    $other_balance = getRemainingOtherBalance($reg_number);
    return $school_fees_balance + $other_balance;
}

// Check if student is logged in via session
$student_logged_in = false;
$student_data = null;
$payment_history = null;
$search_message = '';
$total_school_fees_to_pay = 0;
$total_other_to_pay = 0;
$total_school_fees_paid = 0;
$total_other_paid = 0;
$remaining_school_fees_balance = 0;
$remaining_other_balance = 0;
$total_amount_paid = 0;
$remaining_balance = 0;

// Enhanced session checking with multiple fallbacks
$session_vars_to_check = [
    'student_id',
    'registration_number',
    'student_name'
];

$session_debug_info = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'session_name' => session_name(),
    'session_save_path' => session_save_path()
];

// Check if student is logged in via session with multiple validation methods
if (isset($_SESSION['student_id']) && isset($_SESSION['registration_number'])) {
    $student_logged_in = true;
    $registration_number = $_SESSION['registration_number'];
    $student_data = getStudentDetails($registration_number);
    
    if ($student_data) {
        $payment_history = getPaymentHistory($registration_number);
        $total_school_fees_to_pay = getTotalAmountToPay($registration_number);
        $total_other_to_pay = getTotalOtherAmountToPay($registration_number);
        $total_school_fees_paid = getTotalSchoolFeesPaid($registration_number);
        $total_other_paid = getTotalOtherAmountPaid($registration_number);
        $remaining_school_fees_balance = getRemainingSchoolFeesBalance($registration_number);
        $remaining_other_balance = getRemainingOtherBalance($registration_number);
        $total_amount_paid = getTotalAmountPaid($registration_number);
        $remaining_balance = getRemainingBalance($registration_number);
    } else {
        $search_message = '<div class="alert alert-danger">Student record not found in database!</div>';
    }
} else {
    // Session debug info for troubleshooting
    $session_debug_info['missing_vars'] = [];
    foreach ($session_vars_to_check as $var) {
        if (!isset($_SESSION[$var])) {
            $session_debug_info['missing_vars'][] = $var;
        }
    }
}

// Handle manual search if not logged in or for additional searches
if (isset($_POST['search_student']) && !empty($_POST['registration_number'])) {
    $reg_number = $_POST['registration_number'];
    $student_data = getStudentDetails($reg_number);
    
    if (!$student_data) {
        $search_message = '<div class="alert alert-danger">Student not found!</div>';
    } else {
        $payment_history = getPaymentHistory($reg_number);
        $total_school_fees_to_pay = getTotalAmountToPay($reg_number);
        $total_other_to_pay = getTotalOtherAmountToPay($reg_number);
        $total_school_fees_paid = getTotalSchoolFeesPaid($reg_number);
        $total_other_paid = getTotalOtherAmountPaid($reg_number);
        $remaining_school_fees_balance = getRemainingSchoolFeesBalance($reg_number);
        $remaining_other_balance = getRemainingOtherBalance($reg_number);
        $total_amount_paid = getTotalAmountPaid($reg_number);
        $remaining_balance = getRemainingBalance($reg_number);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: #2c3e50;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }

        .search-form {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .payment-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }

        .approval-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .approval-under_review {
            background-color: #fff3cd;
            color: #856404;
        }

        .approval-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .approval-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .payment-method {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            display: inline-block;
        }

        .method-cash {
            background-color: #27ae60;
            color: white;
        }

        .method-online {
            background-color: #3498db;
            color: white;
        }

        .student-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .student-info h5 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2c3e50;
            text-decoration: none;
        }

        .back-link:hover {
            color: #34495e;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../student/registration/student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-history"></i> Payment History</h3>
            </div>
            <div class="card-body">
                <?php if ($student_logged_in): ?>
                    <!-- Welcome message for logged-in students -->
                    <div class="alert alert-success mb-4" style="border-left: 4px solid #28a745;">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-user-check me-3 mt-1" style="color: #28a745; font-size: 1.2em;"></i>
                            <div>
                                <h6 class="alert-heading mb-2" style="color: #155724;">Welcome, <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?>!</h6>
                                <p class="mb-2" style="color: #155724; font-size: 0.95em;">
                                    You are logged in as <strong><?php echo htmlspecialchars($registration_number); ?></strong>. 
                                    Below is your complete payment history and financial summary.
                                </p>
                                <p class="mb-0" style="color: #155724; font-size: 0.9em;">
                                    <i class="fas fa-shield-alt me-1"></i> Your session is secure and your data is protected.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Session Debug Section (Development Only) -->
                    <div class="alert alert-info mb-4" style="border-left: 4px solid #17a2b8;">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-bug me-3 mt-1" style="color: #17a2b8; font-size: 1.2em;"></i>
                            <div>
                                <h6 class="alert-heading mb-2" style="color: #0c5460;">Session Debug (Development)</h6>
                                <p class="mb-2" style="color: #0c5460; font-size: 0.95em;">
                                    If you're logged in to the dashboard but seeing this message, there might be a session sharing issue.
                                </p>
                                <div class="mb-2">
                                    <button type="button" class="btn btn-info btn-sm" onclick="testSession()">
                                        <i class="fas fa-bug me-1"></i> Test Session
                                    </button>
                                    <a href="../student/registration/student_dashboard.php" class="btn btn-success btn-sm ms-2">
                                        <i class="fas fa-external-link-alt me-1"></i> Go to Dashboard
                                    </a>
                                    <span class="text-muted ms-2" style="font-size: 0.9em;">Check session status</span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <strong>Current Session ID:</strong> <?php echo session_id(); ?><br>
                                        <strong>Session Status:</strong> <?php echo session_status(); ?><br>
                                        <strong>Missing Variables:</strong> 
                                        <?php 
                                        if (isset($session_debug_info['missing_vars'])) {
                                            echo implode(', ', $session_debug_info['missing_vars']);
                                        } else {
                                            echo 'None';
                                        }
                                        ?>
                                    </small>
                                </div>
                                <div id="session-debug-output" class="mt-3" style="display: none;">
                                    <pre id="session-debug-content" class="bg-light p-2 rounded" style="font-size: 12px;"></pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Login prompt for non-logged-in users -->
                    <div class="alert alert-warning mb-4" style="border-left: 4px solid #ffc107;">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-sign-in-alt me-3 mt-1" style="color: #ffc107; font-size: 1.2em;"></i>
                            <div>
                                <h6 class="alert-heading mb-2" style="color: #856404;">Quick Access Available!</h6>
                                <p class="mb-2" style="color: #856404; font-size: 0.95em;">
                                    If you're a registered student, you can <strong>log in to your dashboard</strong> for instant access to your payment history without entering your registration number manually.
                                </p>
                                <div class="mb-2">
                                    <a href="../student/registration/login.php" class="btn btn-warning btn-sm">
                                        <i class="fas fa-sign-in-alt me-1"></i> Login to Dashboard
                                    </a>
                                    <span class="text-muted ms-2" style="font-size: 0.9em;">or continue with manual search below</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description Section for non-logged-in users -->
                    <div class="alert alert-info mb-4" style="border-left: 4px solid #17a2b8;">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-info-circle me-3 mt-1" style="color: #17a2b8; font-size: 1.2em;"></i>
                            <div>
                                <h6 class="alert-heading mb-2" style="color: #0c5460;">Why Registration Number Verification?</h6>
                                <p class="mb-2" style="color: #0c5460; font-size: 0.95em;">
                                    To ensure the security and privacy of your payment information, we require you to enter your 
                                    <strong>Registration Number</strong>. This verification step helps us:
                                </p>
                                <ul class="mb-0" style="color: #0c5460; font-size: 0.9em;">
                                    <li>Protect your personal payment data from unauthorized access</li>
                                    <li>Ensure only you can view your own payment history</li>
                                    <li>Maintain accurate and secure financial records</li>
                                    <li>Comply with data protection and privacy regulations</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Search Form for non-logged-in users -->
                    <div class="search-form">
                        <form method="POST" class="mb-4">
                            <div class="row align-items-end">
                                <div class="col-md-8">
                                    <label for="registration_number" class="form-label">Registration Number</label>
                                    <input type="text" class="form-control" id="registration_number" 
                                           name="registration_number" required 
                                           placeholder="Enter your registration number"
                                           value="<?php echo isset($_POST['registration_number']) ? htmlspecialchars($_POST['registration_number']) : ''; ?>">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="search_student" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php echo $search_message; ?>

                <?php if ($student_data): ?>
                    <!-- Student Information -->
                    <div class="student-info">
                        <h5><i class="fas fa-user-graduate"></i> Student Information</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Class:</strong> <?php echo htmlspecialchars($student_data['class']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($student_data['registration_number']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="row mb-4">
                        <!-- School Fees Section -->
                        <div class="col-12 mb-3">
                            <h5 class="text-primary"><i class="fas fa-graduation-cap"></i> School Fees Summary</h5>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="fas fa-money-bill-wave"></i> School Fees to Pay</h6>
                                    <h4 class="mb-0">₦<?php echo number_format($total_school_fees_to_pay, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="fas fa-check-circle"></i> School Fees Paid</h6>
                                    <h4 class="mb-0">₦<?php echo number_format($total_school_fees_paid, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card <?php echo $remaining_school_fees_balance > 0 ? 'bg-warning' : 'bg-info'; ?> text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">
                                        <i class="fas <?php echo $remaining_school_fees_balance > 0 ? 'fa-exclamation-triangle' : 'fa-check-double'; ?>"></i> 
                                        School Fees Balance
                                    </h6>
                                    <h4 class="mb-0">₦<?php echo number_format($remaining_school_fees_balance, 2); ?></h4>
                                    <?php if ($remaining_school_fees_balance > 0): ?>
                                        <small class="d-block mt-1">Payment Required</small>
                                    <?php else: ?>
                                        <small class="d-block mt-1">Fully Paid</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="fas fa-percentage"></i> Payment Progress</h6>
                                    <h4 class="mb-0">
                                        <?php 
                                        $progress = $total_school_fees_to_pay > 0 ? ($total_school_fees_paid / $total_school_fees_to_pay) * 100 : 0;
                                        echo round($progress, 1) . '%';
                                        ?>
                                    </h4>
                                    <small class="d-block mt-1">Complete</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other Payments Section -->
                    <!-- <?php if ($total_other_to_pay > 0): ?>
                    <div class="row mb-4">
                        <div class="col-12 mb-3">
                            <h5 class="text-info"><i class="fas fa-credit-card"></i> Other Payments Summary</h5>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="fas fa-list"></i> Other Fees to Pay</h6>
                                    <h4 class="mb-0">₦<?php echo number_format($total_other_to_pay, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="fas fa-check-circle"></i> Other Fees Paid</h6>
                                    <h4 class="mb-0">₦<?php echo number_format($total_other_paid, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card <?php echo $remaining_other_balance > 0 ? 'bg-warning' : 'bg-info'; ?> text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">
                                        <i class="fas <?php echo $remaining_other_balance > 0 ? 'fa-exclamation-triangle' : 'fa-check-double'; ?>"></i> 
                                        Other Fees Balance
                                    </h6>
                                    <h4 class="mb-0">₦<?php echo number_format($remaining_other_balance, 2); ?></h4>
                                    <?php if ($remaining_other_balance > 0): ?>
                                        <small class="d-block mt-1">Payment Required</small>
                                    <?php else: ?>
                                        <small class="d-block mt-1">Fully Paid</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="fas fa-percentage"></i> Payment Progress</h6>
                                    <h4 class="mb-0">
                                        <?php 
                                        $other_progress = $total_other_to_pay > 0 ? ($total_other_paid / $total_other_to_pay) * 100 : 0;
                                        echo round($other_progress, 1) . '%';
                                        ?>
                                    </h4>
                                    <small class="d-block mt-1">Complete</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?> -->

                    <!-- Overall Summary -->
                    <!-- <div class="row mb-4">
                        <div class="col-12 mb-3">
                            <h5 class="text-dark"><i class="fas fa-chart-pie"></i> Overall Summary</h5>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-dark text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="fas fa-calculator"></i> Total Amount to Pay</h6>
                                    <h4 class="mb-0">₦<?php echo number_format($total_school_fees_to_pay + $total_other_to_pay, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="fas fa-check-circle"></i> Total Amount Paid</h6>
                                    <h4 class="mb-0">₦<?php echo number_format($total_amount_paid, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card <?php echo $remaining_balance > 0 ? 'bg-warning' : 'bg-info'; ?> text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">
                                        <i class="fas <?php echo $remaining_balance > 0 ? 'fa-exclamation-triangle' : 'fa-check-double'; ?>"></i> 
                                        Total Remaining Balance
                                    </h6>
                                    <h4 class="mb-0">₦<?php echo number_format($remaining_balance, 2); ?></h4>
                                    <?php if ($remaining_balance > 0): ?>
                                        <small class="d-block mt-1">Payment Required</small>
                                    <?php else: ?>
                                        <small class="d-block mt-1">Fully Paid</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div> -->

                    <?php if ($payment_history): ?>
                        <!-- Payment History Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Payment Method</th>
                                        <th>Payment Type</th>
                                        <th>Amount</th>
                                        <th>Service Charge</th>
                                        <th>Total Amount</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                        <th>Approval Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_history as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <span class="payment-method method-<?php echo strtolower($payment['payment_method']); ?>">
                                                    <?php echo $payment['payment_method']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['payment_type_name']); ?></td>
                                            <td>₦<?php echo number_format($payment['base_amount'], 2); ?></td>
                                            <td>₦<?php echo number_format($payment['service_charge'], 2); ?></td>
                                            <td>₦<?php echo number_format($payment['base_amount'] + $payment['service_charge'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($payment['reference_code']); ?></td>
                                            <td>
                                                <span class="payment-status status-<?php echo strtolower($payment['payment_status']); ?>">
                                                    <?php echo ucfirst($payment['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($payment['payment_method'] === 'Cash' && isset($payment['approval_status'])): ?>
                                                    <span class="approval-status approval-<?php echo strtolower($payment['approval_status']); ?>">
                                                        <?php 
                                                        switch($payment['approval_status']) {
                                                            case 'under_review':
                                                                echo 'Under Review';
                                                                break;
                                                            case 'approved':
                                                                echo 'Approved';
                                                                break;
                                                            case 'rejected':
                                                                echo 'Rejected';
                                                                break;
                                                            default:
                                                                echo 'N/A';
                                                        }
                                                        ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No payment history found for this student.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Session test function
        function testSession() {
            fetch('student_payment_history.php?test_session=1')
                .then(response => response.json())
                .then(data => {
                    showSessionDebugOutput('Session Test Result:', data);
                })
                .catch(error => {
                    showSessionDebugOutput('Session Test Error:', { error: error.message });
                });
        }

        function showSessionDebugOutput(title, data) {
            const output = document.getElementById('session-debug-output');
            const content = document.getElementById('session-debug-content');
            
            content.innerHTML = title + '\n' + JSON.stringify(data, null, 2);
            output.style.display = 'block';
        }
    </script>
</body>
</html>
