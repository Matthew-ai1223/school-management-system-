<?php
include '../../confg.php';

// Add missing columns if they don't exist
$alter_queries = [
    "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'cash' AFTER expiration_date",
    "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

foreach ($alter_queries as $query) {
    try {
        $conn->query($query);
    } catch (Exception $e) {
        // Log error but continue
        error_log("Error executing query: " . $e->getMessage());
    }
}

// Initialize filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
$session_type = isset($_GET['session_type']) ? $_GET['session_type'] : '';
$payment_source = isset($_GET['payment_source']) ? $_GET['payment_source'] : '';

// Build query based on payment_source filter
if ($payment_source === 'new') {
    // Only cash_payments
    $query = "SELECT 
        cp.reference_number,
        cp.fullname,
        cp.session_type,
        cp.department,
        cp.payment_type,
        cp.payment_amount,
        cp.class,
        cp.school,
        COALESCE(cp.created_at, cp.updated_at) as payment_date,
        cp.expiration_date,
        cp.payment_method,
        'New Registration' as payment_source,
        CASE 
            WHEN cp.is_processed = 1 THEN 'Processed'
            ELSE 'Pending'
        END as status
    FROM cash_payments cp
    LEFT JOIN reference_numbers rn ON cp.reference_number = rn.reference_number
    WHERE 1=1";
    if ($start_date) {
        $query .= " AND DATE(COALESCE(cp.created_at, cp.updated_at)) >= '$start_date'";
    }
    if ($end_date) {
        $query .= " AND DATE(COALESCE(cp.created_at, cp.updated_at)) <= '$end_date'";
    }
    if ($payment_type) {
        $query .= " AND cp.payment_type = '$payment_type'";
    }
    if ($session_type) {
        $query .= " AND cp.session_type = '$session_type'";
    }
} else if ($payment_source === 'renewal') {
    // Only renew_payment
    $query = "SELECT 
        rp.reference_number,
        rp.fullname,
        rp.session_type,
        rp.department,
        rp.payment_type,
        rp.payment_amount,
        rp.class,
        rp.school,
        COALESCE(rp.created_at, rp.updated_at) as payment_date,
        rp.expiration_date,
        rp.payment_method,
        'Renewal' as payment_source,
        CASE 
            WHEN rp.is_processed = 1 THEN 'Processed'
            ELSE 'Pending'
        END as status
    FROM renew_payment rp
    WHERE 1=1";
    if ($start_date) {
        $query .= " AND DATE(COALESCE(rp.created_at, rp.updated_at)) >= '$start_date'";
    }
    if ($end_date) {
        $query .= " AND DATE(COALESCE(rp.created_at, rp.updated_at)) <= '$end_date'";
    }
    if ($payment_type) {
        $query .= " AND rp.payment_type = '$payment_type'";
    }
    if ($session_type) {
        $query .= " AND rp.session_type = '$session_type'";
    }
} else {
    // Both tables (All)
    $query = "SELECT 
        cp.reference_number,
        cp.fullname,
        cp.session_type,
        cp.department,
        cp.payment_type,
        cp.payment_amount,
        cp.class,
        cp.school,
        COALESCE(cp.created_at, cp.updated_at) as payment_date,
        cp.expiration_date,
        cp.payment_method,
        'New Registration' as payment_source,
        CASE 
            WHEN cp.is_processed = 1 THEN 'Processed'
            ELSE 'Pending'
        END as status
    FROM cash_payments cp
    LEFT JOIN reference_numbers rn ON cp.reference_number = rn.reference_number
    WHERE 1=1";
    if ($start_date) {
        $query .= " AND DATE(COALESCE(cp.created_at, cp.updated_at)) >= '$start_date'";
    }
    if ($end_date) {
        $query .= " AND DATE(COALESCE(cp.created_at, cp.updated_at)) <= '$end_date'";
    }
    if ($payment_type) {
        $query .= " AND cp.payment_type = '$payment_type'";
    }
    if ($session_type) {
        $query .= " AND cp.session_type = '$session_type'";
    }
    $query .= "\nUNION ALL\nSELECT \n    rp.reference_number,\n    rp.fullname,\n    rp.session_type,\n    rp.department,\n    rp.payment_type,\n    rp.payment_amount,\n    rp.class,\n    rp.school,\n    COALESCE(rp.created_at, rp.updated_at) as payment_date,\n    rp.expiration_date,\n    rp.payment_method,\n    'Renewal' as payment_source,\n    CASE \n        WHEN rp.is_processed = 1 THEN 'Processed'\n        ELSE 'Pending'\n    END as status\nFROM renew_payment rp\nWHERE 1=1";
    if ($start_date) {
        $query .= " AND DATE(COALESCE(rp.created_at, rp.updated_at)) >= '$start_date'";
    }
    if ($end_date) {
        $query .= " AND DATE(COALESCE(rp.created_at, rp.updated_at)) <= '$end_date'";
    }
    if ($payment_type) {
        $query .= " AND rp.payment_type = '$payment_type'";
    }
    if ($session_type) {
        $query .= " AND rp.session_type = '$session_type'";
    }
}
$query .= " ORDER BY payment_date DESC";

$result = $conn->query($query);

// Calculate totals
$total_amount = 0;
$total_records = 0;
$payments_by_type = [];
$payments_by_session = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .filters { background-color: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-card { transition: transform 0.2s; }
        .summary-card:hover { transform: translateY(-5px); }
        .nav-buttons { margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Payment Reports</h2>
            <div class="nav-buttons">
                <a href="pay.php" class="btn btn-primary me-2">
                    <i class="bi bi-cash"></i> Renew Payment
                </a>
                <a href="../cash_payments.php" class="btn btn-success me-2">
                    <i class="bi bi-credit-card"></i>Make New Payments
                </a>
                <!-- <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-house"></i> Dashboard
                </a> -->
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
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
                    <label class="form-label">Payment Source</label>
                    <select class="form-select" name="payment_source">
                        <option value="">All</option>
                        <option value="new" <?php echo $payment_source === 'new' ? 'selected' : ''; ?>>New Registration</option>
                        <option value="renewal" <?php echo $payment_source === 'renewal' ? 'selected' : ''; ?>>Renewal</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <a href="payment _report.php?payment_source=&start_date=&end_date=&payment_type=&session_type=" class="btn btn-outline-secondary w-100">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <?php
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $total_amount += $row['payment_amount'];
                    $total_records++;
                    
                    // Count by payment type
                    $type = $row['payment_type'];
                    if (!isset($payments_by_type[$type])) {
                        $payments_by_type[$type] = ['count' => 0, 'amount' => 0];
                    }
                    $payments_by_type[$type]['count']++;
                    $payments_by_type[$type]['amount'] += $row['payment_amount'];
                    
                    // Count by session
                    $session = $row['session_type'];
                    if (!isset($payments_by_session[$session])) {
                        $payments_by_session[$session] = ['count' => 0, 'amount' => 0];
                    }
                    $payments_by_session[$session]['count']++;
                    $payments_by_session[$session]['amount'] += $row['payment_amount'];
                }
                
                // Reset result pointer
                $result->data_seek(0);
            }
            ?>
            <div class="col-md-3">
                <div class="card summary-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Payments</h5>
                        <p class="card-text">
                            Count: <?php echo $total_records; ?><br>
                            Amount: ₦<?php echo number_format($total_amount, 2); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php foreach ($payments_by_type as $type => $data): ?>
            <div class="col-md-3">
                <div class="card summary-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo ucfirst($type); ?> Payments</h5>
                        <p class="card-text">
                            Count: <?php echo $data['count']; ?><br>
                            Amount: ₦<?php echo number_format($data['amount'], 2); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Payments Table -->
        <div class="card">
            <div class="card-body">
                <table id="paymentsTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference/Reg No</th>
                            <th>Name</th>
                            <th>Session</th>
                            <th>Department</th>
                            <th>Payment Type</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result): while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['reference_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo ucfirst($row['session_type']); ?></td>
                            <td><?php echo ucfirst($row['department']); ?></td>
                            <td><?php echo ucfirst($row['payment_type']); ?></td>
                            <td>₦<?php echo number_format($row['payment_amount'], 2); ?></td>
                            <td><?php echo isset($row['payment_method']) ? ucfirst($row['payment_method']) : '-'; ?></td>
                            <td><?php echo $row['payment_source']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $row['status'] === 'Processed' ? 'success' : 'warning'; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $row['expiration_date']; ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#paymentsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search payments:"
                }
            });
        });
    </script>
</body>
</html>
