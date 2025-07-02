<?php
include '../../confg.php';

// Add missing columns if they don't exist
$alter_queries = [
    "ALTER TABLE cash_payments ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'cash' AFTER expiration_date"
];

foreach ($alter_queries as $query) {
    try {
        $conn->query($query);
    } catch (Exception $e) {
        // Log error but continue
        error_log("Error executing query: " . $e->getMessage());
    }
}

// Handle approval action
if (isset($_GET['approve']) && !empty($_GET['approve'])) {
    $reference = $_GET['approve'];
    $payment_source = $_GET['source'] ?? 'cash';
    
    if ($payment_source === 'cash') {
        // Approve the payment in cash_payments table
        $stmt = $conn->prepare("UPDATE cash_payments SET is_processed = 1, processed_at = NOW(), processed_by = 'admin' WHERE reference_number = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->close();
    } else if ($payment_source === 'renewal') {
        // Approve renewal payment in renew_payment table (use reference_number)
        $stmt = $conn->prepare("UPDATE renew_payment SET is_processed = 1, processed_at = NOW(), processed_by = 'admin' WHERE reference_number = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->close();
    } else {
        // Approve renewal payment in student tables (legacy, fallback)
        $table = $_GET['table'] ?? 'morning_students';
        $stmt = $conn->prepare("UPDATE $table SET is_processed = 1, processed_at = NOW(), processed_by = 'admin' WHERE reg_number = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->close();
    }
    
    // Redirect to avoid resubmission
    header('Location: admin_payment_report.php?approved=' . urlencode($reference));
    exit;
}

// Filters
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
        CASE WHEN cp.is_processed = 1 THEN 'Approved' ELSE 'Pending' END as status,
        cp.is_processed,
        'cash' as source_type,
        'cash_payments' as table_name
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
        CASE WHEN rp.is_processed = 1 THEN 'Approved' ELSE 'Pending' END as status,
        rp.is_processed,
        'renewal' as source_type,
        'renew_payment' as table_name
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
        CASE WHEN cp.is_processed = 1 THEN 'Approved' ELSE 'Pending' END as status,
        cp.is_processed,
        'cash' as source_type,
        'cash_payments' as table_name
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
    $query .= "\nUNION ALL\nSELECT \n    rp.reference_number,\n    rp.fullname,\n    rp.session_type,\n    rp.department,\n    rp.payment_type,\n    rp.payment_amount,\n    rp.class,\n    rp.school,\n    COALESCE(rp.created_at, rp.updated_at) as payment_date,\n    rp.expiration_date,\n    rp.payment_method,\n    'Renewal' as payment_source,\n    CASE WHEN rp.is_processed = 1 THEN 'Approved' ELSE 'Pending' END as status,\n    rp.is_processed,\n    'renewal' as source_type,\n    'renew_payment' as table_name\nFROM renew_payment rp\nWHERE 1=1";
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

// Totals
$total_amount = 0;
$total_records = 0;
$payments_by_type = [];
$payments_by_session = [];
$today_payments = ['count' => 0, 'amount' => 0];
$today = date('Y-m-d');

if (
    $result
) {
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Payment Approval Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #e0eafc 0%, #cfdef3 100%);
            min-height: 100vh;
        }
        .header-bar {
            background: linear-gradient(90deg, #007bff 0%, #00c6ff 100%);
            color: #fff;
            padding: 32px 0 24px 0;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            margin-bottom: 32px;
        }
        .header-bar h2 {
            font-weight: 700;
            letter-spacing: 1px;
        }
        .filters {
            background: #fff;
            padding: 20px 24px 10px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .card-body {
            padding: 2rem;
        }
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }
        #paymentsTable {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }
        #paymentsTable thead th {
            background: #f7faff;
            color: #007bff;
            font-weight: 600;
            border-bottom: 2px solid #e3e6f0;
        }
        #paymentsTable tbody tr {
            transition: background 0.2s;
        }
        #paymentsTable tbody tr:hover {
            background: #eaf6ff;
        }
        .badge-success {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            color: #222;
            font-weight: 600;
            font-size: 0.95em;
            border-radius: 8px;
            padding: 6px 14px;
        }
        .badge-warning {
            background: linear-gradient(90deg, #f7971e 0%, #ffd200 100%);
            color: #222;
            font-weight: 600;
            font-size: 0.95em;
            border-radius: 8px;
            padding: 6px 14px;
        }
        .btn-approve {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            color: #222;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 6px 18px;
            transition: box-shadow 0.2s, background 0.2s;
        }
        .btn-approve:hover {
            background: linear-gradient(90deg, #38f9d7 0%, #43e97b 100%);
            box-shadow: 0 2px 8px rgba(67,233,123,0.15);
            color: #111;
        }
        @media (max-width: 768px) {
            .card-body { padding: 1rem; }
            .filters { padding: 12px 8px 2px 8px; }
        }
    </style>
</head>
<body>
    <div class="header-bar text-center">
        <h2>Admin Payment Approval Report</h2>
        <p class="mb-0">View, filter, and approve pending payments from both new registrations and renewals. All data is updated in real time.</p>
    </div>
    <div class="container-fluid py-2">
        <?php if (isset($_GET['approved'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success!</strong> Payment with reference <?php echo htmlspecialchars($_GET['approved']); ?> has been approved.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
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
            <?php foreach ($payments_by_session as $session => $data): ?>
            <div class="col-md-3">
                <div class="card summary-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo ucfirst($session); ?> Session</h5>
                        <p class="card-text">
                            Count: <?php echo $data['count']; ?><br>
                            Amount: ₦<?php echo number_format($data['amount'], 2); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- End Summary Cards -->
        
        <div class="filters mb-4">
            <form method="GET" class="row g-3 align-items-end">
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
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
                <div class="col-md-2">
                    <a href="admin_payment_report.php" class="btn btn-outline-secondary w-100">Clear Filters</a>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                <table id="paymentsTable" class="table table-striped align-middle mb-0">
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
                            <th>Action</th>
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
                                <span class="badge badge-<?php echo $row['status'] === 'Approved' ? 'success' : 'warning'; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $row['expiration_date']; ?></td>
                            <td>
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <a href="?approve=<?php echo urlencode($row['reference_number']); ?>&source=<?php echo $row['source_type']; ?>&table=<?php echo $row['table_name']; ?>" 
                                       class="btn btn-approve btn-sm" 
                                       onclick="return confirm('Approve this payment?');">Approve</a>
                                <?php else: ?>
                                    <span class="text-success">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
                </div>
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
