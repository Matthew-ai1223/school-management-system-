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
    $table = $session === 'morning' ? 'morning_students' : 'afternoon_students';
    // Extend expiration date by 30 days from today
    $new_expiration = date('Y-m-d', strtotime('+30 days'));
    $update_sql = "UPDATE $table SET payment_type = ?, payment_amount = ?, expiration_date = ? WHERE reg_number = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('sdss', $payment_type, $amount, $new_expiration, $reg_number);
    if ($stmt->execute()) {
        $success = 'Payment renewed successfully! Expiration extended to ' . $new_expiration;
        // Fetch updated student info
        $stmt = $conn->prepare("SELECT *, ? as session FROM $table WHERE reg_number = ?");
        $stmt->bind_param('ss', $session, $reg_number);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
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
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Renew Student Payment (Cash)</h2>
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
            <input type="hidden" name="session" value="<?php echo htmlspecialchars($student['session']); ?>">
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
                    <button type="submit" name="renew_payment" class="btn btn-success">Renew Payment (Cash)</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
