<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils.php';

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get class teacher information
$userId = $_SESSION['user_id'];
$className = $_SESSION['class_name'] ?? '';

// Get payment ID from URL parameter
$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($paymentId <= 0) {
    header('Location: payments.php');
    exit;
}

// Get payment details with student information
$paymentQuery = "SELECT p.*, s.first_name, s.last_name, s.admission_number, s.registration_number, s.class
                FROM payments p
                JOIN students s ON p.student_id = s.id
                WHERE p.id = ?";

$stmt = $conn->prepare($paymentQuery);
$stmt->bind_param("i", $paymentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Payment not found
    $_SESSION['error'] = "Payment record not found.";
    header('Location: payments.php');
    exit;
}

$payment = $result->fetch_assoc();

// Verify that the payment belongs to a student in this teacher's class
$teacherQuery = "SELECT ct.class_name
                FROM class_teachers ct
                WHERE ct.user_id = ? AND ct.is_active = 1";

$stmt = $conn->prepare($teacherQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$teacherResult = $stmt->get_result();
$teacherClass = $teacherResult->fetch_assoc()['class_name'];

if ($payment['class'] !== $teacherClass) {
    // Payment belongs to a student not in this teacher's class
    $_SESSION['error'] = "You do not have permission to view this payment record.";
    header('Location: payments.php');
    exit;
}

// Process status update if requested
if (isset($_POST['update_status']) && !empty($_POST['new_status'])) {
    $newStatus = $_POST['new_status'];
    $updateNotes = isset($_POST['update_notes']) ? trim($_POST['update_notes']) : '';
    
    // Update payment status
    $updateQuery = "UPDATE payments SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n\nStatus updated to ', ?, ' on ', NOW(), '. Notes: ', ?) WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("sssi", $newStatus, $newStatus, $updateNotes, $paymentId);
    
    if ($stmt->execute()) {
        // Record activity
        $activityQuery = "INSERT INTO class_teacher_activities (
                class_teacher_id, student_id, activity_type, description, activity_date
            ) VALUES (?, ?, 'payment_update', ?, NOW())";
            
        $description = "Updated payment #$paymentId status to $newStatus";
        
        $stmt = $conn->prepare($activityQuery);
        $stmt->bind_param("iis", $_SESSION['class_teacher_id'], $payment['student_id'], $description);
        $stmt->execute();
        
        $_SESSION['success'] = "Payment status has been updated to " . ucfirst($newStatus);
        header("Location: payment_details.php?id=$paymentId");
        exit;
    } else {
        $_SESSION['error'] = "Failed to update payment status: " . $conn->error;
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
                    <h1 class="m-0">Payment Details</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="payments.php">Payments</a></li>
                        <li class="breadcrumb-item active">Payment Details</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Success!</h5>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Payment Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Student Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Name</th>
                                            <td><a href="student_details.php?id=<?php echo $payment['student_id']; ?>"><?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?></a></td>
                                        </tr>
                                        <tr>
                                            <th>Admission/Reg Number</th>
                                            <td><?php echo !empty($payment['admission_number']) ? $payment['admission_number'] : $payment['registration_number']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Class</th>
                                            <td><?php echo $payment['class']; ?></td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="col-md-6">
                                    <h5>Payment Details</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Payment ID</th>
                                            <td><?php echo $payment['id']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Reference Number</th>
                                            <td><?php echo !empty($payment['reference_number']) ? $payment['reference_number'] : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Date</th>
                                            <td><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5>Payment Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Payment Type</th>
                                            <td><?php echo formatPaymentType($payment['payment_type'] ?? ''); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Amount</th>
                                            <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Payment Method</th>
                                            <td><?php echo ucfirst($payment['payment_method'] ?? 'Unknown'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td><?php echo formatPaymentStatus($payment['status'] ?? ''); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Created By</th>
                                            <td>
                                                <?php 
                                                $createdBy = $payment['created_by'] ?? 0;
                                                if ($createdBy > 0) {
                                                    $userQuery = "SELECT name FROM users WHERE id = $createdBy LIMIT 1";
                                                    $userResult = $conn->query($userQuery);
                                                    if ($userResult && $userResult->num_rows > 0) {
                                                        echo $userResult->fetch_assoc()['name'];
                                                    } else {
                                                        echo "User ID: $createdBy";
                                                    }
                                                } else {
                                                    echo "System";
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Created At</th>
                                            <td><?php echo !empty($payment['created_at']) ? date('F d, Y h:i A', strtotime($payment['created_at'])) : 'N/A'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <?php if (!empty($payment['notes'])): ?>
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5>Notes</h5>
                                    <div class="card">
                                        <div class="card-body bg-light">
                                            <?php echo nl2br(htmlspecialchars($payment['notes'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="payments.php" class="btn btn-default">Back to Payments</a>
                            <a href="update_payment.php?student_id=<?php echo $payment['student_id']; ?>&redirect=student" class="btn btn-primary">
                                <i class="fas fa-plus mr-1"></i> Record Another Payment
                            </a>
                            <?php if ($payment['status'] !== 'completed'): ?>
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#updateStatusModal">
                                <i class="fas fa-check-circle mr-1"></i> Update Status
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-outline card-warning">
                        <div class="card-header">
                            <h3 class="card-title">Payment Receipt</h3>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <img src="../assets/img/logo.png" alt="School Logo" style="max-height: 100px;">
                                <h4 class="mt-2"><?php echo SCHOOL_NAME; ?></h4>
                                <p class="text-muted"><?php echo SCHOOL_ADDRESS; ?></p>
                            </div>
                            
                            <div class="text-center">
                                <h5>PAYMENT RECEIPT</h5>
                                <p>Receipt Number: <?php echo !empty($payment['reference_number']) ? $payment['reference_number'] : 'P-' . $payment['id']; ?></p>
                            </div>
                            
                            <hr>
                            
                            <table class="table table-sm">
                                <tr>
                                    <td>Student Name:</td>
                                    <td><?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?></td>
                                </tr>
                                <tr>
                                    <td>Student ID:</td>
                                    <td><?php echo !empty($payment['admission_number']) ? $payment['admission_number'] : $payment['registration_number']; ?></td>
                                </tr>
                                <tr>
                                    <td>Payment Type:</td>
                                    <td><?php echo formatPaymentType($payment['payment_type'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <td>Amount:</td>
                                    <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Payment Method:</td>
                                    <td><?php echo ucfirst($payment['payment_method'] ?? 'Unknown'); ?></td>
                                </tr>
                                <tr>
                                    <td>Date:</td>
                                    <td><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td>Status:</td>
                                    <td><?php echo formatPaymentStatus($payment['status'] ?? ''); ?></td>
                                </tr>
                            </table>
                            
                            <hr>
                            
                            <div class="text-center">
                                <p class="mb-0"><small>This receipt is electronically generated.</small></p>
                                <p><small>Generated on: <?php echo date('Y-m-d H:i:s'); ?></small></p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button onclick="printReceipt()" class="btn btn-primary btn-block">
                                <i class="fas fa-print mr-1"></i> Print Receipt
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<?php if ($payment['status'] !== 'completed'): ?>
<div class="modal fade" id="updateStatusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Update Payment Status</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_status">New Status</label>
                        <select name="new_status" id="new_status" class="form-control" required>
                            <option value="completed">Completed</option>
                            <option value="pending" <?php echo $payment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $payment['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="update_notes">Notes</label>
                        <textarea name="update_notes" id="update_notes" class="form-control" rows="3" placeholder="Enter any notes about this status update"></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function printReceipt() {
    // Clone the receipt card and prepare for printing
    const receipt = document.querySelector('.card-outline.card-warning').cloneNode(true);
    
    // Remove the footer with print button
    receipt.querySelector('.card-footer').remove();
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Payment Receipt</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
                <style>
                    body { padding: 20px; }
                    .card { box-shadow: none; border: 1px solid #ddd; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            ${receipt.outerHTML}
                        </div>
                    </div>
                    <div class="row mt-4 no-print">
                        <div class="col-md-12 text-center">
                            <button onclick="window.print();" class="btn btn-primary">Print Receipt</button>
                            <button onclick="window.close();" class="btn btn-secondary ml-2">Close</button>
                        </div>
                    </div>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<?php include '../admin/include/footer.php'; ?> 