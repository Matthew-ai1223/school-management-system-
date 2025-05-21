<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils.php';

// Debug mode - only show to localhost
$debugMode = ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1');

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Ensure both admission_number and registration_number columns exist
ensureStudentNumberColumns($conn);

// Get class teacher information
$userId = $_SESSION['user_id'];
$className = $_SESSION['class_name'] ?? '';

$teacherQuery = "SELECT ct.*, t.first_name, t.last_name
                FROM class_teachers ct
                JOIN teachers t ON ct.teacher_id = t.id
                JOIN users u ON ct.user_id = u.id
                WHERE ct.user_id = ? AND ct.is_active = 1";

$stmt = $conn->prepare($teacherQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Error: You are not assigned as a class teacher. Please contact the administrator.";
    exit;
}

$classTeacher = $result->fetch_assoc();
$classTeacherId = $classTeacher['id'];
$className = $className ?: $classTeacher['class_name']; // Use from session or DB

// Build the query with filters
$query = "SELECT p.*, s.first_name, s.last_name, 
          COALESCE(s.admission_number, s.registration_number) as registration_number 
          FROM payments p 
          JOIN students s ON p.student_id = s.id 
          WHERE s.class = ?";
$params = [$className];
$types = "s";

// Get filters
$paymentType = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

if (!empty($paymentType)) {
    $query .= " AND p.payment_type = ?";
    $types .= "s";
    $params[] = $paymentType;
}

if (!empty($status)) {
    $query .= " AND p.status = ?";
    $types .= "s";
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(p.payment_date) >= ?";
    $types .= "s";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(p.payment_date) <= ?";
    $types .= "s";
    $params[] = $dateTo;
}

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_number LIKE ? OR s.registration_number LIKE ? OR p.reference_number LIKE ?)";
    $types .= "sssss";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY p.payment_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$paymentsResult = $stmt->get_result();
$payments = [];

// Get payments for table
while ($row = $paymentsResult->fetch_assoc()) {
    $payments[] = $row;
}

// Debug information
if ($debugMode && count($payments) > 0) {
    echo '<div class="alert alert-info alert-dismissible">';
    echo '<h5><i class="icon fas fa-info"></i> Debug Information</h5>';
    echo '<p>First payment record structure:</p>';
    echo '<pre>';
    print_r($payments[0]);
    echo '</pre>';
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo '</div>';
}

// Calculate totals for statistics
$totalAmount = 0;
$totalPending = 0;
$totalCompleted = 0;
$totalFailed = 0;

foreach ($payments as $payment) {
    $totalAmount += $payment['amount'];
    
    if ($payment['status'] === 'pending') {
        $totalPending += $payment['amount'];
    } elseif ($payment['status'] === 'completed' || $payment['status'] === 'success') {
        $totalCompleted += $payment['amount'];
    } elseif ($payment['status'] === 'failed') {
        $totalFailed += $payment['amount'];
    }
}

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Student Payments - <?php echo $className; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Payments</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>₦<?php echo number_format($totalAmount, 2); ?></h3>
                            <p>Total Payments</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>₦<?php echo number_format($totalCompleted, 2); ?></h3>
                            <p>Total Completed</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>₦<?php echo number_format($totalPending, 2); ?></h3>
                            <p>Total Pending</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>₦<?php echo number_format($totalFailed, 2); ?></h3>
                            <p>Total Failed</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-money-bill-wave mr-2"></i> Student Payments
                            </h3>
                            <div class="card-tools">
                                <a href="update_payment.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Add New Payment
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filter Form -->
                            <form action="" method="GET" class="mb-4">
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <label for="payment_type">Payment Type</label>
                                        <select name="payment_type" id="payment_type" class="form-control">
                                            <option value="">All Types</option>
                                            <option value="tuition_fee" <?php echo $paymentType == 'tuition_fee' ? 'selected' : ''; ?>>Tuition Fee</option>
                                            <option value="registration_fee" <?php echo $paymentType == 'registration_fee' ? 'selected' : ''; ?>>Registration Fee</option>
                                            <option value="development_levy" <?php echo $paymentType == 'development_levy' ? 'selected' : ''; ?>>Development Levy</option>
                                            <option value="book_fee" <?php echo $paymentType == 'book_fee' ? 'selected' : ''; ?>>Book Fee</option>
                                            <option value="uniform_fee" <?php echo $paymentType == 'uniform_fee' ? 'selected' : ''; ?>>Uniform Fee</option>
                                            <option value="exam_fee" <?php echo $paymentType == 'exam_fee' ? 'selected' : ''; ?>>Examination Fee</option>
                                            <option value="transportation_fee" <?php echo $paymentType == 'transportation_fee' ? 'selected' : ''; ?>>Transportation Fee</option>
                                            <option value="other" <?php echo $paymentType == 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="status">Status</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="">All Statuses</option>
                                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label for="search">Search</label>
                                        <input type="text" name="search" id="search" class="form-control" placeholder="Search by name, registration number or reference..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mt-2">
                                    <div class="col-md-3 mb-2">
                                        <label for="date_from">Date From</label>
                                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="date_to">Date To</label>
                                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                                    </div>
                                    <div class="col-md-2 mb-2 ml-auto">
                                        <label class="d-block">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-search mr-1"></i> Filter
                                        </button>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="d-block">&nbsp;</label>
                                        <a href="payments.php" class="btn btn-outline-secondary btn-block">
                                            <i class="fas fa-sync-alt mr-1"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <!-- Payments Table -->
                            <?php if (count($payments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th width="5%">ID</th>
                                                <th width="15%">Student</th>
                                                <th width="12%">Type</th>
                                                <th width="10%">Amount</th>
                                                <th width="10%">Method</th>
                                                <th width="10%">Reference</th>
                                                <th width="10%">Date</th>
                                                <th width="8%">Status</th>
                                                <th width="10%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                    <td><?php echo $payment['id']; ?></td>
                                                    <td>
                                                        <a href="student_details.php?id=<?php echo $payment['student_id']; ?>">
                                                            <?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?>
                                                        </a>
                                                        <div class="small text-muted"><?php echo $payment['registration_number']; ?></div>
                                                    </td>
                                                    <td><?php echo formatPaymentType($payment['payment_type'] ?? ''); ?></td>
                                                    <td>₦<?php echo isset($payment['amount']) ? number_format($payment['amount'], 2) : '0.00'; ?></td>
                                                    <td><?php echo isset($payment['payment_method']) ? ucfirst($payment['payment_method']) : 'Unknown'; ?></td>
                                                    <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                                                    <td><?php echo isset($payment['payment_date']) ? date('M d, Y', strtotime($payment['payment_date'])) : 'Unknown'; ?></td>
                                                    <td>
                                                        <?php echo formatPaymentStatus($payment['status'] ?? ''); ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="payment_details.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <?php if ($payment['status'] === 'pending'): ?>
                                                            <a href="update_payment_status.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <span>Total: <?php echo count($payments); ?> payments</span>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> No Payments Found</h5>
                                    <p>No payment records match your search criteria.</p>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="update_payment.php" class="btn btn-primary">
                                        <i class="fas fa-plus mr-1"></i> Add New Payment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($payments) > 0): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">Export Options</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <button onclick="exportTable('csv')" class="btn btn-block btn-success">
                                        <i class="fas fa-file-csv mr-2"></i> Export to CSV
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button onclick="printReport()" class="btn btn-block btn-primary">
                                        <i class="fas fa-print mr-2"></i> Print Report
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <a href="update_payment.php" class="btn btn-block btn-warning">
                                        <i class="fas fa-plus mr-2"></i> Add New Payment
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function exportTable(format) {
        // Get current URL with parameters
        const currentUrl = window.location.href;
        // Create export URL by adding export parameter
        const exportUrl = currentUrl + (currentUrl.includes('?') ? '&' : '?') + 'export=' + format;
        // Redirect to export URL
        window.location.href = exportUrl;
    }

    function printReport() {
        // Clone the table and prepare for printing
        const table = document.querySelector('.table').cloneNode(true);
        
        // Hide actions column
        const actionCells = table.querySelectorAll('th:last-child, td:last-child');
        actionCells.forEach(cell => cell.style.display = 'none');
        
        // Create print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Payment Records - ${<?php echo json_encode($className); ?>}</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
                    <style>
                        body { padding: 20px; }
                        .header { margin-bottom: 20px; }
                        .table { width: 100%; }
                        .footer { margin-top: 30px; font-size: 12px; text-align: center; }
                        @media print {
                            .no-print { display: none; }
                            a { text-decoration: none; color: #000; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h3>Payment Records</h3>
                        <p>Class: ${<?php echo json_encode($className); ?>}</p>
                        <p>Generated: ${new Date().toLocaleString()}</p>
                    </div>
                    <div class="table-container">
                        ${table.outerHTML}
                    </div>
                    <div class="footer">
                        <p>Report generated from ${<?php echo json_encode(SCHOOL_NAME); ?>} Class Teacher Portal</p>
                    </div>
                    <div class="no-print text-center mt-4">
                        <button onclick="window.print();" class="btn btn-primary">Print Report</button>
                        <button onclick="window.close();" class="btn btn-secondary ml-2">Close</button>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
    }
</script>

<?php include '../admin/include/footer.php'; ?> 