<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$user = $auth->getCurrentUser();

// Add after Database::getInstance();
$mysqli = $db->getConnection();

// Get payment ID
$payment_id = $_GET['id'] ?? 0;

if (!$payment_id) {
    header('Location: payments.php');
    exit;
}

// Get payment details
$query = "SELECT p.*, s.first_name, s.last_name, s.registration_number, s.application_type 
          FROM payments p 
          JOIN students s ON p.student_id = s.id 
          WHERE p.id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header('Location: payments.php');
    exit;
}

$payment = $result->fetch_assoc();

// Handle PDF generation
if (isset($_GET['pdf'])) {
    $pdf = new PDFGenerator();
    $pdf->AliasNbPages();
    
    $payment['student_name'] = $payment['first_name'] . ' ' . $payment['last_name'];
    $pdf->generatePaymentReceipt($payment);
    $pdf->Output();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Payment Details</h2>
                    <div>
                        <a href="?id=<?php echo $payment_id; ?>&pdf=1" class="btn btn-info me-2" target="_blank">
                            <i class="bi bi-file-pdf"></i> Download Receipt
                        </a>
                        <a href="payments.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Payments
                        </a>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Payment ID:</strong> <?php echo htmlspecialchars($payment['id']); ?></p>
                                <p><strong>Reference Number:</strong> <?php echo htmlspecialchars($payment['reference_number']); ?></p>
                                <p><strong>Payment Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?></p>
                                <p><strong>Amount:</strong> ₦<?php echo number_format($payment['amount'], 2); ?></p>
                                <p><strong>Payment Method:</strong> <?php echo ucfirst($payment['payment_method']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Date:</strong> <?php echo date('Y-m-d H:i', strtotime($payment['payment_date'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </p>
                                <?php if ($payment['status'] === 'pending'): ?>
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-success me-2" onclick="updatePaymentStatus('completed')">
                                            <i class="bi bi-check"></i> Mark as Completed
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="updatePaymentStatus('failed')">
                                            <i class="bi bi-x"></i> Mark as Failed
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Student Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($payment['registration_number']); ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
                                <p><strong>Application Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['application_type'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <a href="student_details.php?id=<?php echo $payment['student_id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-person"></i> View Student Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updatePaymentStatus(status) {
            if (confirm('Are you sure you want to mark this payment as ' + status + '?')) {
                fetch('update_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=<?php echo $payment_id; ?>&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment status updated successfully!');
                        window.location.reload();
                    } else {
                        alert(data.message || 'An error occurred while updating payment status.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating payment status.');
                });
            }
        }
    </script>
</body>
</html> 