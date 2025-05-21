<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get payment ID and student ID
$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (!$paymentId) {
    $_SESSION['error_message'] = "Invalid payment ID";
    header('Location: payments.php');
    exit;
}

// Get payment details
$stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->bind_param("i", $paymentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Payment not found";
    header('Location: payments.php');
    exit;
}

$payment = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($status)) {
        $_SESSION['error_message'] = "Status is required";
    } else {
        // Update payment status
        $stmt = $conn->prepare("UPDATE payments SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n[Status updated on ".date('Y-m-d H:i:s')."] ', ?) WHERE id = ?");
        $stmt->bind_param("ssi", $status, $notes, $paymentId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Payment status has been updated successfully";
            
            // Redirect based on where we came from
            if ($studentId) {
                header("Location: student_details.php?id=$studentId");
            } else {
                header("Location: payments.php");
            }
            exit;
        } else {
            $_SESSION['error_message'] = "Error updating payment status: " . $stmt->error;
        }
    }
}

// Include header
include 'include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Update Payment Status</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <?php if ($studentId): ?>
                            <li class="breadcrumb-item"><a href="student_details.php?id=<?php echo $studentId; ?>">Student Details</a></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="payments.php">Payments</a></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active">Update Payment Status</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                    <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Payment Information</h3>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Type</label>
                                <p><?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'] ?? 'N/A')); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount</label>
                                <p>₦<?php echo number_format($payment['amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Method</label>
                                <p><?php echo ucfirst($payment['payment_method']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Reference Number</label>
                                <p><?php echo htmlspecialchars($payment['reference_number'] ?? $payment['reference'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Date</label>
                                <p><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Current Status</label>
                                <p>
                                    <span class="badge bg-<?php 
                                        if ($payment['status'] === 'success' || $payment['status'] === 'completed') {
                                            echo 'success';
                                        } elseif ($payment['status'] === 'pending') {
                                            echo 'warning';
                                        } else {
                                            echo 'danger';
                                        }
                                    ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Update Status</h3>
                </div>
                
                <form method="POST" action="">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="status">New Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="">-- Select Status --</option>
                                <option value="completed" <?php echo $payment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="pending" <?php echo $payment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="failed" <?php echo $payment['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Enter any additional notes regarding this status change"></textarea>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Update Status</button>
                        <a href="<?php echo $studentId ? "student_details.php?id=$studentId" : 'payments.php'; ?>" class="btn btn-default">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php include 'include/footer.php'; ?> 