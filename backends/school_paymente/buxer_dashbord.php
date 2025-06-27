<?php
require_once 'ctrl/db_config.php';
require_once 'ctrl/payment_types.php';

session_start();

// Create tables if they don't exist
function createPaymentTables($conn) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    try {
        // Create cash payments table
        $sql = "CREATE TABLE IF NOT EXISTS cash_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL,
            payment_type_id INT,
            amount DECIMAL(10,2) NOT NULL,
            base_amount DECIMAL(10,2) NOT NULL,
            service_charge DECIMAL(10,2) DEFAULT 0.00,
            reference_code VARCHAR(100) UNIQUE,
            payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
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

        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        return true;
    } catch (Exception $e) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        return false;
    }
}

// Initialize tables
createPaymentTables($conn);

// Function to get cash payments with filters
function getCashPayments($conn, $filters = []) {
    $where_conditions = [];
    $params = [];
    $types = "";
    
    // Base query
    $sql = "SELECT cp.*, s.first_name, s.last_name, s.class, spt.name as payment_type_name 
            FROM cash_payments cp 
            LEFT JOIN students s ON cp.student_id = s.registration_number 
            LEFT JOIN school_payment_types spt ON cp.payment_type_id = spt.id";
    
    // Add filters
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "DATE(cp.payment_date) >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "DATE(cp.payment_date) <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    if (!empty($filters['student_search'])) {
        $where_conditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR cp.student_id LIKE ?)";
        $search_term = "%" . $filters['student_search'] . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }
    
    if (!empty($filters['payment_type'])) {
        $where_conditions[] = "cp.payment_type_id = ?";
        $params[] = $filters['payment_type'];
        $types .= "i";
    }
    
    if (!empty($filters['bursar_id'])) {
        $where_conditions[] = "cp.bursar_id = ?";
        $params[] = $filters['bursar_id'];
        $types .= "s";
    }
    
    // Add WHERE clause if conditions exist
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY cp.payment_date DESC";
    
    // Add limit for pagination
    if (isset($filters['limit'])) {
        $sql .= " LIMIT " . $filters['limit'];
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    $stmt->close();
    return $payments;
}

// Function to get payment statistics
function getPaymentStats($conn, $bursar_id = null) {
    $where_clause = "";
    $params = [];
    $types = "";
    
    if ($bursar_id) {
        $where_clause = "WHERE bursar_id = ?";
        $params[] = $bursar_id;
        $types = "s";
    }
    
    $sql = "SELECT 
                COUNT(*) as total_payments,
                SUM(amount) as total_amount,
                COUNT(DISTINCT student_id) as unique_students,
                DATE(MAX(payment_date)) as last_payment_date
            FROM cash_payments 
            $where_clause";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    // Get today's amount
    $today = date('Y-m-d');
    $sql_today = "SELECT SUM(amount) as day_amount FROM cash_payments WHERE DATE(payment_date) = ?";
    if ($bursar_id) {
        $sql_today .= " AND bursar_id = ?";
    }
    $stmt_today = $conn->prepare($sql_today);
    if ($bursar_id) {
        $stmt_today->bind_param('ss', $today, $bursar_id);
    } else {
        $stmt_today->bind_param('s', $today);
    }
    $stmt_today->execute();
    $result_today = $stmt_today->get_result();
    $row_today = $result_today->fetch_assoc();
    $stats['day_amount'] = $row_today['day_amount'] ?? 0;
    $stmt_today->close();
    
    return $stats;
}

// Get payment types for filter
$paymentTypes = new PaymentTypes($conn);
$available_payment_types = $paymentTypes->getPaymentTypes();

// Handle filters
$filters = [];
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}
if (isset($_GET['student_search']) && !empty($_GET['student_search'])) {
    $filters['student_search'] = $_GET['student_search'];
}
if (isset($_GET['payment_type']) && !empty($_GET['payment_type'])) {
    $filters['payment_type'] = $_GET['payment_type'];
}

// Set bursar ID (you can modify this based on your authentication system)
$current_bursar_id = "BUR-001";
$filters['bursar_id'] = $current_bursar_id;

// Get payments and stats
$cash_payments = getCashPayments($conn, $filters);
$payment_stats = getPaymentStats($conn, $current_bursar_id);

// Handle receipt generation request
if (isset($_GET['generate_receipt'])) {
    $receipt_number = $_GET['receipt_number'];
    $payment_id = $_GET['payment_id'];
    
    // Get payment details
    $sql = "SELECT cp.*, s.first_name, s.last_name, s.class, s.email, spt.name as payment_type_name 
            FROM cash_payments cp 
            LEFT JOIN students s ON cp.student_id = s.registration_number 
            LEFT JOIN school_payment_types spt ON cp.payment_type_id = spt.id 
            WHERE cp.receipt_number = ? AND cp.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $receipt_number, $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($payment) {
        // Generate receipt HTML
        $receipt_html = generateReceiptHTML($payment);
        
        // Return receipt data
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'receipt_html' => $receipt_html,
            'receipt_number' => $receipt_number
        ]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
}

// Function to generate receipt HTML
function generateReceiptHTML($payment) {
    $school_name = "ACE MODEL COLLEGE";
    $school_address = "123 Education Street, Lagos, Nigeria";
    $school_phone = "+234 123 456 7890";
    $school_email = "info@acemodelcollege.com";
    
    $receipt_html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Receipt - ' . $payment['receipt_number'] . '</title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none !important; }
                .receipt-container { 
                    width: 100% !important; 
                    max-width: none !important; 
                    margin: 0 !important; 
                    padding: 20px !important; 
                }
            }
            
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }
            
            .receipt-container {
                max-width: 400px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .receipt-header {
                background: linear-gradient(135deg, #27ae60, #2ecc71);
                color: white;
                padding: 20px;
                text-align: center;
            }
            
            .school-name {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .school-info {
                font-size: 12px;
                opacity: 0.9;
            }
            
            .receipt-title {
                background: #f8f9fa;
                padding: 15px 20px;
                text-align: center;
                border-bottom: 2px solid #27ae60;
            }
            
            .receipt-title h2 {
                margin: 0;
                color: #2c3e50;
                font-size: 20px;
            }
            
            .receipt-body {
                padding: 20px;
            }
            
            .receipt-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            
            .receipt-row:last-child {
                border-bottom: none;
                font-weight: bold;
                font-size: 16px;
                color: #27ae60;
            }
            
            .receipt-row.total {
                border-top: 2px solid #27ae60;
                border-bottom: 2px solid #27ae60;
                font-size: 18px;
                font-weight: bold;
                color: #2c3e50;
                margin-top: 15px;
                padding-top: 15px;
            }
            
            .label {
                font-weight: 600;
                color: #555;
            }
            
            .value {
                text-align: right;
                color: #333;
            }
            
            .student-info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                border-left: 4px solid #27ae60;
            }
            
            .student-info h4 {
                margin: 0 0 10px 0;
                color: #2c3e50;
                font-size: 16px;
            }
            
            .student-detail {
                margin-bottom: 5px;
                font-size: 14px;
            }
            
            .receipt-footer {
                background: #f8f9fa;
                padding: 15px 20px;
                text-align: center;
                border-top: 1px solid #dee2e6;
            }
            
            .footer-text {
                font-size: 12px;
                color: #666;
                margin-bottom: 10px;
            }
            
            .signature-section {
                display: flex;
                justify-content: space-between;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #dee2e6;
            }
            
            .signature-box {
                text-align: center;
                flex: 1;
            }
            
            .signature-line {
                border-top: 1px solid #333;
                margin-top: 30px;
                margin-bottom: 5px;
            }
            
            .signature-label {
                font-size: 12px;
                color: #666;
            }
            
            .cash-badge {
                background: #27ae60;
                color: white;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: bold;
                display: inline-block;
                margin-left: 10px;
            }
            
            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #27ae60;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .print-button:hover {
                background: #229954;
            }
        </style>
    </head>
    <body>
        <button class="print-button no-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        
        <div class="receipt-container">
            <div class="receipt-header">
                <div class="school-name">' . $school_name . '</div>
                <div class="school-info">
                    ' . $school_address . '<br>
                    Phone: ' . $school_phone . ' | Email: ' . $school_email . '
                </div>
            </div>
            
            <div class="receipt-title">
                <h2>OFFICIAL RECEIPT</h2>
                <span class="cash-badge">CASH PAYMENT</span>
            </div>
            
            <div class="receipt-body">
                <div class="student-info">
                    <h4><i class="fas fa-user-graduate"></i> Student Information</h4>
                    <div class="student-detail"><strong>Name:</strong> ' . htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) . '</div>
                    <div class="student-detail"><strong>Class:</strong> ' . htmlspecialchars($payment['class']) . '</div>
                    <div class="student-detail"><strong>Registration No:</strong> ' . htmlspecialchars($payment['student_id']) . '</div>
                </div>
                
                <div class="receipt-row">
                    <span class="label">Receipt Number:</span>
                    <span class="value">' . htmlspecialchars($payment['receipt_number']) . '</span>
                </div>
                
                <div class="receipt-row">
                    <span class="label">Reference Code:</span>
                    <span class="value">' . htmlspecialchars($payment['reference_code']) . '</span>
                </div>
                
                <div class="receipt-row">
                    <span class="label">Payment Date:</span>
                    <span class="value">' . date('M d, Y h:i A', strtotime($payment['payment_date'])) . '</span>
                </div>
                
                <div class="receipt-row">
                    <span class="label">Payment Type:</span>
                    <span class="value">' . htmlspecialchars($payment['payment_type_name']) . '</span>
                </div>
                
                <div class="receipt-row">
                    <span class="label">Base Amount:</span>
                    <span class="value">₦' . number_format($payment['base_amount'], 2) . '</span>
                </div>
                
                <div class="receipt-row">
                    <span class="label">Service Charge:</span>
                    <span class="value">₦' . number_format($payment['service_charge'], 2) . '</span>
                </div>
                
                <div class="receipt-row total">
                    <span class="label">Total Amount:</span>
                    <span class="value">₦' . number_format($payment['amount'], 2) . '</span>
                </div>
                
                <div class="receipt-row">
                    <span class="label">Processed By:</span>
                    <span class="value">' . htmlspecialchars($payment['bursar_name']) . '</span>
                </div>
                
                <div class="receipt-row">
                    <span class="label">Status:</span>
                    <span class="value">' . ucfirst($payment['payment_status']) . '</span>
                </div>';
    
    if (!empty($payment['notes'])) {
        $receipt_html .= '
                <div class="receipt-row">
                    <span class="label">Notes:</span>
                    <span class="value">' . htmlspecialchars($payment['notes']) . '</span>
                </div>';
    }
    
    $receipt_html .= '
            </div>
            
            <div class="receipt-footer">
                <div class="footer-text">
                    This is an official receipt for payment made to ' . $school_name . '<br>
                    Please keep this receipt for your records
                </div>
                
                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-label">Student/Parent Signature</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-label">Bursar Signature</div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    </body>
    </html>';
    
    return $receipt_html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bursar Dashboard - Cash Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #27ae60;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --border-radius: 10px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-fluid {
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            color: var(--primary-color);
        }

        .page-header h2 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 0.5rem;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--secondary-color), #2ecc71);
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-card p {
            margin-bottom: 0;
            opacity: 0.9;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: var(--light-bg);
            border-bottom: 2px solid #dee2e6;
            color: var(--primary-color);
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
        }

        .payment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }

        .approval-status {
            padding: 0.25rem 0.75rem;
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

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success {
            background-color: var(--secondary-color);
            border: none;
        }

        .btn-success:hover {
            background-color: #229954;
            transform: translateY(-2px);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background-color: #234567;
            transform: translateY(-2px);
        }

        .filter-section {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }

        .form-control {
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.2);
            border-color: var(--secondary-color);
        }

        .cash-badge {
            background-color: var(--secondary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75em;
            font-weight: 600;
        }

        .bursar-info {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 1rem;
            }

            .page-header h2 {
                font-size: 2rem;
            }

            .stats-card h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="page-header">
            <h2><i class="fas fa-tachometer-alt"></i> Bursar Dashboard</h2>
            <p>Manage and monitor cash payments</p>
        </div>

        <!-- Bursar Information -->
        <div class="bursar-info">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5><i class="fas fa-user-tie"></i> Bursar Information</h5>
                    <p class="mb-1"><strong>Name:</strong> Administrator</p>
                    <p class="mb-0"><strong>ID:</strong> <?php echo $current_bursar_id; ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="payment_interface_cash.php" class="btn btn-light">
                        <i class="fas fa-plus"></i> New Cash Payment
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo number_format($payment_stats['total_payments']); ?></h3>
                    <p><i class="fas fa-receipt"></i> Total Payments</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3>₦<?php echo number_format($payment_stats['total_amount'], 2); ?></h3>
                    <p><i class="fas fa-money-bill-wave"></i> Total Amount</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo number_format($payment_stats['unique_students']); ?></h3>
                    <p><i class="fas fa-users"></i> Students Served</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo $payment_stats['last_payment_date'] ? date('M d', strtotime($payment_stats['last_payment_date'])) : 'N/A'; ?></h3>
                    <p><i class="fas fa-calendar"></i> Last Payment</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3>₦<?php echo number_format($payment_stats['day_amount'], 2); ?></h3>
                    <p><i class="fas fa-calendar-day"></i> Day Amount</p>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filter-section">
            <h5><i class="fas fa-filter"></i> Filter Payments</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="student_search" class="form-label">Search Student</label>
                    <input type="text" class="form-control" id="student_search" name="student_search" 
                           placeholder="Name or Registration Number"
                           value="<?php echo isset($_GET['student_search']) ? htmlspecialchars($_GET['student_search']) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <label for="payment_type" class="form-label">Payment Type</label>
                    <select class="form-control" id="payment_type" name="payment_type">
                        <option value="">All Types</option>
                        <?php foreach($available_payment_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" 
                                    <?php echo (isset($_GET['payment_type']) && $_GET['payment_type'] == $type['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="buxer_dashbord.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-list"></i> Cash Payments History
                    <span class="badge bg-success ms-2"><?php echo count($cash_payments); ?> payments</span>
                </h5>
                
                <?php if (empty($cash_payments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No cash payments found</h5>
                        <p class="text-muted">Start processing cash payments to see them here.</p>
                        <a href="payment_interface_cash.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Process New Payment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Payment Type</th>
                                    <th>Amount</th>
                                    <th>Receipt No.</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                    <th>Approval Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cash_payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($payment['payment_date'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['student_id']); ?></small>
                                                <br>
                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($payment['class']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="cash-badge">CASH</span>
                                            <br>
                                            <?php echo htmlspecialchars($payment['payment_type_name']); ?>
                                        </td>
                                        <td>
                                            <strong>₦<?php echo number_format($payment['amount'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($payment['receipt_number']); ?></code>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($payment['reference_code']); ?></code>
                                        </td>
                                        <td>
                                            <span class="payment-status status-<?php echo strtolower($payment['payment_status']); ?>">
                                                <?php echo ucfirst($payment['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="approval-status approval-<?php echo strtolower($payment['approval_status'] ?? 'under_review'); ?>">
                                                <?php 
                                                switch($payment['approval_status'] ?? 'under_review') {
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
                                                        echo 'Under Review';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="printReceipt('<?php echo $payment['receipt_number']; ?>', <?php echo $payment['id']; ?>)">
                                                    <i class="fas fa-print"></i> Print
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="text-center mt-4">
            <a href="payment_interface_cash.php" class="btn btn-success me-2">
                <i class="fas fa-plus"></i> New Cash Payment
            </a>
            <!-- <a href="../admin/dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a> -->
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentDetailsModalLabel">
                        <i class="fas fa-receipt"></i> Payment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="paymentDetailsContent">
                    <!-- Payment details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReceipt()">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPaymentDetails(paymentId) {
            // This function would load payment details via AJAX
            // For now, we'll show a simple message
            document.getElementById('paymentDetailsContent').innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                    <p>Loading payment details...</p>
                </div>
            `;
            
            // Show the modal
            new bootstrap.Modal(document.getElementById('paymentDetailsModal')).show();
            
            // Simulate loading payment details
            setTimeout(() => {
                document.getElementById('paymentDetailsContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Payment details feature will be implemented in the next update.
                    </div>
                `;
            }, 1000);
        }

        function printReceipt() {
            // This function would handle receipt printing
            alert('Receipt printing feature will be implemented in the next update.');
        }

        // Function to print receipt from dashboard
        function printReceipt(receiptNumber, paymentId) {
            // Show loading
            const printButton = event.target;
            const originalText = printButton.innerHTML;
            printButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            printButton.disabled = true;

            // Fetch receipt data
            fetch(`buxer_dashbord.php?generate_receipt=1&receipt_number=${receiptNumber}&payment_id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create a new window for printing
                        const printWindow = window.open('', '_blank', 'width=800,height=600');
                        printWindow.document.write(data.receipt_html);
                        printWindow.document.close();
                        
                        // Wait for content to load then print
                        printWindow.onload = function() {
                            printWindow.print();
                            printWindow.close();
                        };
                    } else {
                        alert('Error generating receipt: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error generating receipt. Please try again.');
                })
                .finally(() => {
                    // Restore button
                    printButton.innerHTML = originalText;
                    printButton.disabled = false;
                });
        }
    </script>
</body>
</html>
