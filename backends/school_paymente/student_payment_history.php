<?php
require_once 'ctrl/db_config.php';
require_once 'ctrl/payment_types.php';

session_start();

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
            payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
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

// Handle student search
$student_data = null;
$payment_history = null;
$search_message = '';

if (isset($_POST['search_student'])) {
    $reg_number = $_POST['registration_number'];
    $student_data = getStudentDetails($reg_number);
    
    if (!$student_data) {
        $search_message = '<div class="alert alert-danger">Student not found!</div>';
    } else {
        $payment_history = getPaymentHistory($reg_number);
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
                <!-- Description Section -->
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

                <!-- Search Form -->
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
</body>
</html>
