<?php
include '../confg.php';

// Initialize filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
$session_type = isset($_GET['session_type']) ? $_GET['session_type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$reference_status = isset($_GET['reference_status']) ? $_GET['reference_status'] : '';

// Handle actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $reference = $_POST['reference'] ?? '';
    
    if ($action === 'delete' && !empty($reference)) {
        // Delete cash payment and reference number
        $conn->query("DELETE FROM cash_payments WHERE reference_number = '$reference'");
        $conn->query("DELETE FROM reference_numbers WHERE reference_number = '$reference'");
        $success_message = "Reference number deleted successfully.";
    }
}

// Build the SQL query with filters
$sql = "SELECT cp.*, rn.is_used, rn.used_at, rn.created_by 
        FROM cash_payments cp 
        LEFT JOIN reference_numbers rn ON cp.reference_number = rn.reference_number 
        WHERE 1=1";

// Add filters
if ($start_date) {
    $sql .= " AND DATE(cp.registration_date) >= '$start_date'";
}
if ($end_date) {
    $sql .= " AND DATE(cp.registration_date) <= '$end_date'";
}
if ($payment_type) {
    $sql .= " AND cp.payment_type = '$payment_type'";
}
if ($session_type) {
    $sql .= " AND cp.session_type = '$session_type'";
}
if ($status === 'processed') {
    $sql .= " AND cp.is_processed = 1";
} elseif ($status === 'pending') {
    $sql .= " AND cp.is_processed = 0";
}
if ($reference_status === 'used') {
    $sql .= " AND rn.is_used = 1";
} elseif ($reference_status === 'unused') {
    $sql .= " AND (rn.is_used = 0 OR rn.is_used IS NULL)";
}

$sql .= " ORDER BY cp.registration_date DESC";
$result = $conn->query($sql);

// Get filtered statistics
$stats_sql = "SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN cp.is_processed = 1 THEN 1 ELSE 0 END) as processed_count,
    SUM(CASE WHEN cp.is_processed = 0 THEN 1 ELSE 0 END) as pending_count,
    SUM(cp.payment_amount) as total_amount
FROM cash_payments cp 
LEFT JOIN reference_numbers rn ON cp.reference_number = rn.reference_number 
WHERE 1=1";

// Add same filters to statistics
if ($start_date) {
    $stats_sql .= " AND DATE(cp.registration_date) >= '$start_date'";
}
if ($end_date) {
    $stats_sql .= " AND DATE(cp.registration_date) <= '$end_date'";
}
if ($payment_type) {
    $stats_sql .= " AND cp.payment_type = '$payment_type'";
}
if ($session_type) {
    $stats_sql .= " AND cp.session_type = '$session_type'";
}
if ($status === 'processed') {
    $stats_sql .= " AND cp.is_processed = 1";
} elseif ($status === 'pending') {
    $stats_sql .= " AND cp.is_processed = 0";
}
if ($reference_status === 'used') {
    $stats_sql .= " AND rn.is_used = 1";
} elseif ($reference_status === 'unused') {
    $stats_sql .= " AND (rn.is_used = 0 OR rn.is_used IS NULL)";
}

$stats_result = $conn->query($stats_sql)->fetch_assoc();
$total_payments = $stats_result['total_count'];
$processed_payments = $stats_result['processed_count'];
$pending_payments = $stats_result['pending_count'];
$total_amount = $stats_result['total_amount'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Payments Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 15px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .card-header { background-color: #0d6efd; color: white; border-radius: 15px 15px 0 0 !important; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .filters-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
        .filters-card .card-header { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
        .form-label { font-weight: 500; color: #495057; }
        .btn-filter { transition: all 0.3s ease; }
        .btn-filter:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h2 class="text-center mb-4">Cash Payments Management</h2>

        <!-- Filters -->
        <div class="card mb-4 filters-card">
            <div class="card-header">
                <h5 class="mb-0">Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Payment Type</label>
                        <select class="form-select" name="payment_type">
                            <option value="">All</option>
                            <option value="full" <?php echo $payment_type === 'full' ? 'selected' : ''; ?>>Full Payment</option>
                            <option value="half" <?php echo $payment_type === 'half' ? 'selected' : ''; ?>>Half Payment</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Session Type</label>
                        <select class="form-select" name="session_type">
                            <option value="">All</option>
                            <option value="morning" <?php echo $session_type === 'morning' ? 'selected' : ''; ?>>Morning</option>
                            <option value="afternoon" <?php echo $session_type === 'afternoon' ? 'selected' : ''; ?>>Afternoon</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All</option>
                            <option value="processed" <?php echo $status === 'processed' ? 'selected' : ''; ?>>Processed</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Reference Status</label>
                        <select class="form-select" name="reference_status">
                            <option value="">All</option>
                            <option value="used" <?php echo $reference_status === 'used' ? 'selected' : ''; ?>>Used</option>
                            <option value="unused" <?php echo $reference_status === 'unused' ? 'selected' : ''; ?>>Unused</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100 btn-filter">Apply Filters</button>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <a href="cash_payments.php" class="btn btn-outline-secondary w-100 btn-filter">Clear Filters</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?php echo $total_payments; ?></h3>
                        <p class="mb-0">Total Payments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?php echo $processed_payments; ?></h3>
                        <p class="mb-0">Processed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?php echo $pending_payments; ?></h3>
                        <p class="mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3>₦<?php echo number_format($total_amount, 2); ?></h3>
                        <p class="mb-0">Total Amount</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- Cash Payments Table -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Cash Payments</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Name</th>
                                <th>Session</th>
                                <th>Department</th>
                                <th>Payment Type</th>
                                <th>Amount</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['reference_number']); ?></strong>
                                        <?php if ($row['is_used']): ?>
                                            <span class="badge bg-success">Used</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Unused</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['session_type'] === 'morning' ? 'primary' : 'info'; ?>">
                                            <?php echo ucfirst($row['session_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($row['department']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['payment_type'] === 'full' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($row['payment_type']); ?>
                                        </span>
                                    </td>
                                    <td>₦<?php echo number_format($row['payment_amount'], 2); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($row['registration_date'])); ?></td>
                                    <td>
                                        <?php if ($row['is_processed']): ?>
                                            <span class="badge bg-success">Processed</span>
                                            <br><small class="text-muted"><?php echo date('M d, Y', strtotime($row['processed_at'])); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$row['is_used']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="reference" value="<?php echo $row['reference_number']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="payment/cash_registration.php" class="btn btn-primary">Create New Cash Payment</a>
                <a href="payment/payment _report.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html> 