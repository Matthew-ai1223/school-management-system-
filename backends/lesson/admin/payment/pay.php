<?php
include '../../confg.php';

$student = null;
$error = '';
$success = '';

// Handle search by reg number
if (isset($_POST['search_reg'])) {
    $reg_number = trim($_POST['reg_number']);
    if ($reg_number) {
        // Search in morning_students
        $stmt = $conn->prepare("SELECT *, 'morning' as session FROM morning_students WHERE reg_number = ?");
        $stmt->bind_param('s', $reg_number);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
        } else {
            // Search in afternoon_students
            $stmt = $conn->prepare("SELECT *, 'afternoon' as session FROM afternoon_students WHERE reg_number = ?");
            $stmt->bind_param('s', $reg_number);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $student = $result->fetch_assoc();
            } else {
                $error = 'No student found with that registration number.';
            }
        }
        $stmt->close();
    } else {
        $error = 'Please enter a registration number.';
    }
}

// Handle payment renewal
if (isset($_POST['renew_payment']) && isset($_POST['reg_number'])) {
    $reg_number = $_POST['reg_number'];
    $session = $_POST['session'];
    $payment_type = $_POST['payment_type'];
    $amount = floatval($_POST['amount']);
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
    $table = $session === 'morning' ? 'morning_students' : 'afternoon_students';
    // Extend expiration date by 30 days from today
    $new_expiration = date('Y-m-d', strtotime('+30 days'));
    $update_sql = "UPDATE $table SET payment_type = ?, payment_amount = ?, expiration_date = ?, payment_method = ?, is_active = 1, is_processed = 0 WHERE reg_number = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('sdsss', $payment_type, $amount, $new_expiration, $payment_method, $reg_number);
    if ($stmt->execute()) {
        $success = 'Payment renewed successfully! Expiration extended to ' . $new_expiration . '. Payment is pending admin approval.';
        // Fetch updated student info
        $stmt = $conn->prepare("SELECT *, ? as session FROM $table WHERE reg_number = ?");
        $stmt->bind_param('ss', $session, $reg_number);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();

            // Insert renewal record into renew_payment table
            $reg_number = $student['reg_number'];
            $fullname = $student['fullname'];
            $session_type = $student['session'];
            $department = $student['department'];
            $payment_type_val = $student['payment_type'];
            $payment_amount = $student['payment_amount'];
            $class = isset($student['class']) ? $student['class'] : '';
            $school = isset($student['school']) ? $student['school'] : '';
            $expiration_date = $student['expiration_date'];
            $payment_method_var = $payment_method;
            $processed_by = $student['reg_number'];

            $insert_sql = "INSERT INTO renew_payment (reference_number, fullname, session_type, department, payment_type, payment_amount, class, school, expiration_date, payment_method, created_at, updated_at, processed_by, is_processed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, 0)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param(
                'sssssdsssss',
                $reg_number,
                $fullname,
                $session_type,
                $department,
                $payment_type_val,
                $payment_amount,
                $class,
                $school,
                $expiration_date,
                $payment_method_var,
                $processed_by
            );
            $insert_stmt->execute();
            $insert_stmt->close();

            // --- Generate QR code and receipt ---
            require_once __DIR__ . '/../../student/generate_receipt.php';
            require_once __DIR__ . '/../QR/functions.php';

            // Prepare user and payment details for receipt
            $user = [
                'fullname' => $student['fullname'],
                'email' => $student['email'],
                'phone' => $student['phone'],
                'department' => $student['department'],
                'reg_number' => $student['reg_number'],
                'photo' => isset($student['photo']) && file_exists($student['photo']) ? $student['photo'] : null
            ];
            $payment_details = [
                'reference' => $student['payment_reference'] ?? $student['reg_number'],
                'payment_type' => $student['payment_type'],
                'amount' => $student['payment_amount'],
                'expiration_date' => $student['expiration_date'],
                'registration_date' => $student['registration_date'] ?? date('Y-m-d'),
                'payment_method' => $payment_method
            ];

            // Generate QR code (returns SVG path)
            $studentData = [
                'fullname' => $student['fullname'],
                'department' => $student['department'],
                'reg_number' => $student['reg_number'],
                'status' => isset($student['expiration_date']) ? (new DateTime() > new DateTime($student['expiration_date']) ? 'Expired' : 'Active') : 'Unknown',
                'payment_reference' => $payment_details['reference'],
                'payment_type' => $payment_details['payment_type'],
                'payment_amount' => $payment_details['amount'],
                'registration_date' => $payment_details['registration_date'],
                'expiration_date' => $payment_details['expiration_date']
            ];
            $qrPath = generateStudentQR($studentData);

            // Generate receipt with QR code (extend Receipt class to add QR)
            class ReceiptWithQR extends Receipt {
                public $qrPath;
                function generateReceipt($user, $payment_details, $table) {
                    parent::generateReceipt($user, $payment_details, $table);
                    
                    // Generate QR code
                    $qrPath = generateStudentQR([
                        'fullname' => $user['fullname'],
                        'department' => $user['department'],
                        'reg_number' => $user['reg_number'],
                        'payment_reference' => $payment_details['reference'],
                        'payment_type' => $payment_details['payment_type'],
                        'payment_amount' => $payment_details['amount'],
                        'registration_date' => isset($payment_details['registration_date']) ? $payment_details['registration_date'] : date('Y-m-d'),
                        'expiration_date' => $payment_details['expiration_date']
                    ]);

                    if ($qrPath && file_exists($qrPath)) {
                        try {
                            // Add QR code to receipt
                            $this->Image($qrPath, 160, 80, 30, 30);
                            // Clean up the temporary QR code file
                            @unlink($qrPath);
                        } catch (Exception $e) {
                            error_log('Failed to add QR code to PDF: ' . $e->getMessage());
                            $this->SetXY(160, 80);
                            $this->SetFont('Arial', 'I', 8);
                            $this->Cell(30, 30, 'QR Code Error', 1, 0, 'C');
                        }
                    } else {
                        $this->SetXY(160, 80);
                        $this->SetFont('Arial', 'I', 8);
                        $this->Cell(30, 30, 'QR Code Error', 1, 0, 'C');
                    }
                }
            }
            $pdf = new ReceiptWithQR();
            $pdf->qrPath = $qrPath;
            $pdf->generateReceipt($user, $payment_details, $table);
            $uploads_dir = __DIR__ . '/../../student/uploads';
            if (!file_exists($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }
            // Use a safe filename for the receipt (replace / with _)
            $safe_reg_number = str_replace('/', '_', $student['reg_number']);
            $receipt_filename = 'receipt_' . $safe_reg_number . '_' . time() . '.pdf';
            $receipt_path = $uploads_dir . '/' . $receipt_filename;
            $pdf->Output('F', $receipt_path);
            $success .= '<br><a class="btn btn-info mt-2" href="../../student/uploads/' . $receipt_filename . '" target="_blank">Download Receipt with QR Code</a>';
        }
    } else {
        $error = 'Failed to renew payment. Please try again.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Renew Student Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Renew Student Payment (Cash)</h2>
        <a href="payment_report.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Payment Reports
        </a>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <form method="POST" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="reg_number" class="form-label mb-0">Registration Number</label>
                <input type="text" class="form-control" id="reg_number" name="reg_number" required value="<?php echo isset($_POST['reg_number']) ? htmlspecialchars($_POST['reg_number']) : ''; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" name="search_reg" class="btn btn-primary">Search</button>
            </div>
        </div>
    </form>

    <?php if ($student): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Student Information</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($student['fullname']); ?></p>
                <p><strong>Session:</strong> <?php echo ucfirst($student['session']); ?></p>
                <p><strong>Reg Number:</strong> <?php echo htmlspecialchars($student['reg_number']); ?></p>
                <p><strong>Current Payment Type:</strong> <?php echo htmlspecialchars($student['payment_type']); ?></p>
                <p><strong>Current Amount:</strong> ₦<?php echo number_format($student['payment_amount'], 2); ?></p>
                <p><strong>Expiration Date:</strong> <?php echo htmlspecialchars($student['expiration_date']); ?></p>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="reg_number" value="<?php echo htmlspecialchars($student['reg_number']); ?>">
            <input type="hidden" name="session" id="session" value="<?php echo htmlspecialchars($student['session']); ?>">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Renew Payment</h5>
                    <div class="mb-3">
                        <label for="payment_type" class="form-label">Payment Type</label>
                        <select class="form-select" id="payment_type" name="payment_type" required>
                            <option value="full" <?php if ($student['payment_type'] === 'full') echo 'selected'; ?>>Full Payment</option>
                            <option value="half" <?php if ($student['payment_type'] === 'half') echo 'selected'; ?>>Half Payment</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount (₦)</label>
                        <input type="number" class="form-control" id="amount" name="amount" required min="0" step="0.01" value="<?php echo htmlspecialchars($student['payment_amount']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_method_cash" value="cash" checked>
                            <label class="form-check-label" for="payment_method_cash">Cash</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_method_online" value="online">
                            <label class="form-check-label" for="payment_method_online">Online</label>
                        </div>
                    </div>
                    <button type="submit" name="renew_payment" class="btn btn-success">Renew Payment (Cash)</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentType = document.getElementById('payment_type');
    const amountInput = document.getElementById('amount');
    const sessionInput = document.getElementById('session');

    function updateAmount() {
        const session = sessionInput ? sessionInput.value : '';
        const type = paymentType.value;
        let amount = 0;

        if (session === 'morning') {
            amount = (type === 'full') ? 7000 : 3500;
        } else if (session === 'afternoon') {
            amount = (type === 'full') ? 3000 : 1500;
        }
        amountInput.value = amount;
    }

    if (paymentType && amountInput && sessionInput) {
        paymentType.addEventListener('change', updateAmount);
    }
});
</script>
</html>
