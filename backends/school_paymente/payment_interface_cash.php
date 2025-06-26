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

        // Create bursars table
        $sql = "CREATE TABLE IF NOT EXISTS bursars (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bursar_id VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            throw new Exception("Error creating bursars table: " . $conn->error);
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

$paymentTypes = new PaymentTypes($conn);
$available_payments = $paymentTypes->getPaymentTypes();

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

// Function to generate receipt number
function generateReceiptNumber() {
    return 'RCP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
}

// Function to generate reference code
function generateReferenceCode() {
    return 'CASH-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 10));
}

// Handle student search
$student_data = null;
$search_message = '';
$success_message = '';

if (isset($_POST['search_student'])) {
    $reg_number = $_POST['registration_number'];
    $student_data = getStudentDetails($reg_number);
    if (!$student_data) {
        $search_message = '<div class="alert alert-danger">Student not found!</div>';
    }
}

// Handle cash payment submission
if (isset($_POST['process_cash_payment'])) {
    $student_id = $_POST['student_id'];
    $payment_type_id = $_POST['payment_type'];
    $amount = $_POST['amount'];
    $bursar_id = $_POST['bursar_id'];
    $bursar_name = $_POST['bursar_name'];
    $notes = $_POST['notes'];
    
    // Generate unique codes
    $reference_code = generateReferenceCode();
    $receipt_number = generateReceiptNumber();
    
    // Get payment type details
    $stmt = $conn->prepare("SELECT * FROM school_payment_types WHERE id = ?");
    $stmt->bind_param("i", $payment_type_id);
    $stmt->execute();
    $payment_type = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $base_amount = $amount;
    $service_charge = 0.00; // No service charge for cash payments
    
    // Insert cash payment
    $sql = "INSERT INTO cash_payments (student_id, payment_type_id, amount, base_amount, service_charge, 
            reference_code, receipt_number, bursar_id, bursar_name, notes, approval_status, payment_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'under_review', 'pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sidddsssss", $student_id, $payment_type_id, $amount, $base_amount, 
                      $service_charge, $reference_code, $receipt_number, $bursar_id, $bursar_name, $notes);
    
    if ($stmt->execute()) {
        $payment_id = $conn->insert_id;
        
        $success_message = '<div class="alert alert-success">'
            . '<i class="fas fa-check-circle"></i> Cash payment processed successfully!<br>'
            . '<strong>Receipt Number:</strong> ' . $receipt_number . '<br>'
            . '<strong>Reference Code:</strong> ' . $reference_code . '<br>'
            . '<strong>Status:</strong> <span class="badge bg-warning">Under Review</span><br>'
            . '<strong>Note:</strong> Payment is pending admin approval<br><br>'
            . '<div class="d-flex gap-2">'
            . '<button type="button" class="btn btn-primary btn-sm" onclick="printReceipt(\'' . $receipt_number . '\', \'' . $payment_id . '\')">'
            . '<i class="fas fa-print"></i> Print Receipt'
            . '</button>'
            . '<button type="button" class="btn btn-outline-primary btn-sm" onclick="downloadReceipt(\'' . $receipt_number . '\', \'' . $payment_id . '\')">'
            . '<i class="fas fa-download"></i> Download PDF'
            . '</button>'
            . '</div>'
            . '</div>';
        
        // Clear student data to allow new search
        $student_data = null;
    } else {
        $search_message = '<div class="alert alert-danger">Error processing payment: ' . $conn->error . '</div>';
    }
    $stmt->close();
}

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
    <title>Cash Payment Interface</title>
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

        .container {
            padding-top: 2rem;
            padding-bottom: 2rem;
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

        .form-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
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

        .btn-secondary {
            background-color: var(--primary-color);
            border: none;
        }

        .btn-secondary:hover {
            background-color: #234567;
            transform: translateY(-2px);
        }

        .student-info {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            border-left: 4px solid var(--secondary-color);
        }

        .student-info h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .student-info p {
            margin-bottom: 0.5rem;
            color: #555;
            display: flex;
            align-items: center;
        }

        .student-info i {
            margin-right: 10px;
            color: var(--secondary-color);
            width: 20px;
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .cash-payment-badge {
            background-color: var(--secondary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85em;
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
            .container {
                padding-top: 1rem;
            }

            .page-header h2 {
                font-size: 2rem;
            }

            .card-body {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-money-bill-wave"></i> Cash Payment Interface</h2>
            <p>Process cash payments for students - Bursar Access Only</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <!-- Bursar Information -->
                <div class="bursar-info">
                    <h5><i class="fas fa-user-tie"></i> Bursar Information</h5>
                    <p class="mb-1"><strong>Name:</strong> <span id="bursar-name">Administrator</span></p>
                    <p class="mb-0"><strong>ID:</strong> <span id="bursar-id">BUR-001</span></p>
                </div>

                <?php echo $success_message; ?>
                <?php echo $search_message; ?>

                <!-- Student Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-search"></i> Search Student
                        </h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="registration_number" class="form-label">
                                    <i class="fas fa-id-card"></i> Student Registration Number
                                </label>
                                <input type="text" class="form-control" id="registration_number" 
                                       name="registration_number" required 
                                       placeholder="Enter student registration number">
                            </div>
                            <button type="submit" name="search_student" class="btn btn-secondary w-100">
                                <i class="fas fa-search"></i> Search Student
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($student_data): ?>
                <!-- Student Information -->
                <div class="student-info">
                    <h5><i class="fas fa-user-graduate"></i> Student Information</h5>
                    <p><i class="fas fa-user"></i> <strong>Name:</strong> 
                        <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?>
                    </p>
                    <p><i class="fas fa-chalkboard"></i> <strong>Class:</strong> 
                        <?php echo htmlspecialchars($student_data['class']); ?>
                    </p>
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> 
                        <?php echo htmlspecialchars($student_data['email']); ?>
                    </p>
                    <p><i class="fas fa-id-card"></i> <strong>Registration Number:</strong> 
                        <?php echo htmlspecialchars($student_data['registration_number']); ?>
                    </p>
                </div>

                <!-- Cash Payment Form -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-money-bill-wave"></i> Process Cash Payment
                            <span class="cash-payment-badge ms-2">CASH</span>
                        </h5>
                        <form method="POST" id="cashPaymentForm">
                            <input type="hidden" name="student_id" 
                                   value="<?php echo htmlspecialchars($student_data['registration_number']); ?>">
                            <input type="hidden" name="bursar_id" value="BUR-001">
                            <input type="hidden" name="bursar_name" value="Administrator">
                            
                            <div class="mb-3">
                                <label for="payment_type" class="form-label">
                                    <i class="fas fa-list"></i> Payment Type
                                </label>
                                <select class="form-control" name="payment_type" id="payment_type" required>
                                    <option value="">Select Payment Type</option>
                                    <?php foreach($available_payments as $payment): ?>
                                        <option value="<?php echo $payment['id']; ?>" 
                                                data-amount="<?php echo $payment['amount']; ?>"
                                                data-min-amount="<?php echo $payment['min_payment_amount']; ?>">
                                            <?php echo $payment['name']; ?> 
                                            (₦<?php echo number_format($payment['amount'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="amount" class="form-label">
                                    <i class="fas fa-money-bill"></i> Amount Received
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" name="amount" id="amount" 
                                           placeholder="Enter amount received" min="0" step="100" required
                                           oninput="validateAmount()">
                                </div>
                                <small class="text-danger" id="amount_notice"></small>
                                <div class="form-text" id="payment_info"></div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-sticky-note"></i> Notes (Optional)
                                </label>
                                <textarea class="form-control" name="notes" id="notes" rows="3" 
                                          placeholder="Any additional notes about this payment"></textarea>
                            </div>
                            
                            <button type="submit" name="process_cash_payment" class="btn btn-success w-100" id="processButton">
                                <i class="fas fa-check-circle"></i> Process Cash Payment
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Navigation Links -->
                <div class="text-center mt-4">
                    <a href="buxer_dashbord.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-tachometer-alt"></i> Bursar Dashboard
                    </a>
                    <a href="../admin/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Admin
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateAmount() {
            const paymentType = document.getElementById('payment_type');
            const amount = document.getElementById('amount');
            const amountNotice = document.getElementById('amount_notice');
            const paymentInfo = document.getElementById('payment_info');
            const processButton = document.getElementById('processButton');

            if (paymentType.value && amount.value) {
                const selectedOption = paymentType.options[paymentType.selectedIndex];
                const minAmount = parseFloat(selectedOption.dataset.minAmount);
                const fullAmount = parseFloat(selectedOption.dataset.amount);
                const enteredAmount = parseFloat(amount.value);

                if (enteredAmount < minAmount) {
                    amountNotice.textContent = `Minimum amount required: ₦${minAmount.toLocaleString()}`;
                    processButton.disabled = true;
                } else if (enteredAmount > fullAmount) {
                    amountNotice.textContent = '';
                    paymentInfo.innerHTML = `<i class="fas fa-info-circle text-info"></i> Amount exceeds full payment. Change will be: ₦${(enteredAmount - fullAmount).toLocaleString()}`;
                    processButton.disabled = false;
                } else {
                    amountNotice.textContent = '';
                    paymentInfo.innerHTML = `<i class="fas fa-info-circle text-info"></i> Remaining balance: ₦${(fullAmount - enteredAmount).toLocaleString()}`;
                    processButton.disabled = false;
                }
            } else {
                amountNotice.textContent = '';
                paymentInfo.textContent = '';
                processButton.disabled = true;
            }
        }

        // Auto-fill amount when payment type is selected
        document.getElementById('payment_type').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('amount').value = selectedOption.dataset.amount;
                validateAmount();
            }
        });

        // Function to print receipt
        function printReceipt(receiptNumber, paymentId) {
            // Show loading
            const printButton = event.target;
            const originalText = printButton.innerHTML;
            printButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            printButton.disabled = true;

            // Fetch receipt data
            fetch(`payment_interface_cash.php?generate_receipt=1&receipt_number=${receiptNumber}&payment_id=${paymentId}`)
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

        // Function to download receipt as PDF
        function downloadReceipt(receiptNumber, paymentId) {
            // Show loading
            const downloadButton = event.target;
            const originalText = downloadButton.innerHTML;
            downloadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            downloadButton.disabled = true;

            // Fetch receipt data
            fetch(`payment_interface_cash.php?generate_receipt=1&receipt_number=${receiptNumber}&payment_id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create a new window for PDF generation
                        const pdfWindow = window.open('', '_blank', 'width=800,height=600');
                        pdfWindow.document.write(data.receipt_html);
                        pdfWindow.document.close();
                        
                        // Wait for content to load then trigger PDF download
                        pdfWindow.onload = function() {
                            // Use browser's print to PDF functionality
                            pdfWindow.print();
                            pdfWindow.close();
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
                    downloadButton.innerHTML = originalText;
                    downloadButton.disabled = false;
                });
        }

        // Function to open receipt in new window for viewing
        function viewReceipt(receiptNumber, paymentId) {
            fetch(`payment_interface_cash.php?generate_receipt=1&receipt_number=${receiptNumber}&payment_id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const viewWindow = window.open('', '_blank', 'width=800,height=600');
                        viewWindow.document.write(data.receipt_html);
                        viewWindow.document.close();
                    } else {
                        alert('Error loading receipt: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading receipt. Please try again.');
                });
        }
    </script>
</body>
</html>
