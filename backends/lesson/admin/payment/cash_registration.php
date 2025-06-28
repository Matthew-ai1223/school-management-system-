<?php
include '../../confg.php';

// Function to generate random reference number
function generateRandomReference($session, $payment_type) {
    $prefix = strtoupper(substr($session, 0, 3)); // MOR or AFT
    $payment_prefix = strtoupper(substr($payment_type, 0, 1)); // F or H
    
    do {
        // Generate a random 8-digit number
        $random_number = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        $reference = $prefix . $payment_prefix . $random_number;
        
        // Check if reference already exists
        $sql = "SELECT id FROM reference_numbers WHERE reference_number = ?";
        $stmt = $GLOBALS['conn']->prepare($sql);
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);
    
    return $reference;
}

// Function to store reference number
function storeReferenceNumber($reference, $session, $payment_type, $created_by) {
    $sql = "INSERT INTO reference_numbers (reference_number, session_type, payment_type, created_by) VALUES (?, ?, ?, ?)";
    $stmt = $GLOBALS['conn']->prepare($sql);
    $stmt->bind_param("ssss", $reference, $session, $payment_type, $created_by);
    return $stmt->execute();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $session = $_POST['session'];
    $department = $_POST['department'];
    $payment_type = $_POST['payment_type'];
    $class = isset($_POST['class']) ? trim($_POST['class']) : '';
    $school = isset($_POST['school']) ? trim($_POST['school']) : '';

    // Validate inputs
    if (empty($fullname) || empty($session) || empty($department) || empty($payment_type)) {
        $error = 'All required fields must be filled.';
    } else {
        // Calculate amount based on session and payment type
        if ($session === 'morning') {
            $amount = ($payment_type === 'full') ? 10000 : 5200;
        } else {
            $amount = ($payment_type === 'full') ? 4000 : 2200;
        }

        // Generate random reference number
        $reference = generateRandomReference($session, $payment_type);
        
        // Store reference number in reference_numbers table
        if (!storeReferenceNumber($reference, $session, $payment_type, 'admin')) {
            $error = 'Failed to generate reference number. Please try again.';
        } else {
            // Store payment details in cash_payments table
            $expiration_date = date('Y-m-d', strtotime('+30 days'));
            
            $sql = "INSERT INTO cash_payments (reference_number, fullname, session_type, department, payment_type, payment_amount, class, school, expiration_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssdsss", $reference, $fullname, $session, $department, $payment_type, $amount, $class, $school, $expiration_date);
            
            if ($stmt->execute()) {
                // Generate receipt with QR code
                require_once 'generate_cash_receipt.php';
                
                $payment_data = [
                    'fullname' => $fullname,
                    'department' => $department,
                    'reference_number' => $reference,
                    'session_type' => $session,
                    'payment_type' => $payment_type,
                    'payment_amount' => $amount,
                    'class' => $class,
                    'school' => $school,
                    'registration_date' => date('Y-m-d'),
                    'expiration_date' => $expiration_date
                ];
                
                $receipt_filename = generateCashPaymentReceipt($payment_data);
                
                $success = "Cash registration successful!<br>
                           <strong>Reference Number: $reference</strong><br>
                           <strong>Amount: ₦" . number_format($amount, 2) . "</strong><br>
                           <strong>Expiration Date: $expiration_date</strong><br><br>
                           <a href='../../student/uploads/$receipt_filename' target='_blank' class='btn btn-info mt-2'>Download Receipt with QR Code</a><br><br>
                           <small class='text-muted'>Student can use this reference number to complete registration at: <a href='../../student/reg.php' target='_blank'>Student Registration Page</a></small>";
            } else {
                $error = 'Failed to process registration. Please try again.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .container { max-width: 800px; }
        .card { border-radius: 15px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .card-header { background-color: #0d6efd; color: white; border-radius: 15px 15px 0 0 !important; }
        .form-label { font-weight: 500; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Cash Registration</h3>
            <a href="../cash_payments.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Cash Payments
            </a>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Cash Registration</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" id="registrationForm">
                    <div class="mb-3">
                        <label for="fullname" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="fullname" name="fullname" required>
                    </div>

                    <div class="mb-3">
                        <label for="session" class="form-label">Session</label>
                        <select class="form-select" id="session" name="session" onchange="toggleSessionFields()" required>
                            <option value="">Select Session</option>
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="sciences">Sciences</option>
                            <option value="commercial">Commercial</option>
                            <option value="art">Art</option>
                        </select>
                    </div>

                    <div id="afternoonFields" style="display: none;">
                        <div class="mb-3">
                            <label for="class" class="form-label">Class</label>
                            <input type="text" class="form-control" id="class" name="class">
                        </div>

                        <div class="mb-3">
                            <label for="school" class="form-label">School</label>
                            <input type="text" class="form-control" id="school" name="school">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Type</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_type" id="full_payment" value="full" checked>
                            <label class="form-check-label" for="full_payment" id="full_payment_label">
                                Full Payment (₦10,000)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_type" id="half_payment" value="half">
                            <label class="form-check-label" for="half_payment" id="half_payment_label">
                                Half Payment (₦5,200)
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Process Registration</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSessionFields() {
            const session = document.getElementById('session').value;
            const afternoonFields = document.getElementById('afternoonFields');
            const fullPaymentLabel = document.getElementById('full_payment_label');
            const halfPaymentLabel = document.getElementById('half_payment_label');
            
            if (session === 'afternoon') {
                afternoonFields.style.display = 'block';
                document.getElementById('class').required = true;
                document.getElementById('school').required = true;
                fullPaymentLabel.textContent = 'Full Payment (₦4,000)';
                halfPaymentLabel.textContent = 'Half Payment (₦2,200)';
            } else {
                afternoonFields.style.display = 'none';
                document.getElementById('class').required = false;
                document.getElementById('school').required = false;
                fullPaymentLabel.textContent = 'Full Payment (₦10,000)';
                halfPaymentLabel.textContent = 'Half Payment (₦5,200)';
            }
        }
    </script>
</body>
</html> 