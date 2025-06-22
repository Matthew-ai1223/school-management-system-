<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once 'include/settings.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Get payment verification status
$settings = Settings::getInstance();
$payment_verification_required = $settings->getSetting('payment_verification_required') == '1';

// Handle form submission for manual reference code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $reference = trim($_POST['reference'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        
        if (empty($reference) || $amount <= 0 || empty($email) || empty($full_name)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Check if reference already exists
        $stmt = $mysqli->prepare("SELECT id FROM application_payments WHERE reference = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Payment reference already exists');
        }
        
        // Insert payment record
        $query = "INSERT INTO application_payments (reference, amount, email, phone, full_name, status, payment_method, payment_date) 
                 VALUES (?, ?, ?, ?, ?, 'completed', 'manual', NOW())";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('sdsss', $reference, $amount, $email, $phone, $full_name);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Payment reference added successfully';
        } else {
            throw new Exception('Failed to add payment reference');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    // Return JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Get all payment records
$query = "SELECT * FROM application_payments ORDER BY payment_date DESC";
$payments = $mysqli->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #2196F3;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Payment Verification</h2>
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <label class="toggle-switch">
                                <input type="checkbox" id="paymentVerificationToggle" 
                                       <?php echo $payment_verification_required ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="ms-2" id="toggleStatus">
                                Payment Verification is <?php echo $payment_verification_required ? 'Required' : 'Optional'; ?>
                            </span>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                            <i class="bi bi-plus"></i> Add Manual Payment
                        </button>
                    </div>
                </div>

                <!-- Status Messages -->
                <div id="statusMessage" class="alert" style="display: none;"></div>

                <?php if (isset($response) && !$response['success']): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($response['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Payments Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($payments->num_rows > 0): ?>
                                        <?php while ($payment = $payments->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                                                <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['email']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['phone']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($payment['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                <td><?php echo $payment['payment_date'] ? date('Y-m-d H:i', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No payment records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Manual Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addPaymentForm">
                        <div class="mb-3">
                            <label class="form-label">Payment Reference *</label>
                            <input type="text" name="reference" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (₦) *</label>
                            <input type="number" name="amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="students@acecollege.ng" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="savePayment()">Save Payment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add this to your existing JavaScript
        document.getElementById('paymentVerificationToggle').addEventListener('change', function() {
            const statusMessage = document.getElementById('statusMessage');
            const toggleStatus = document.getElementById('toggleStatus');
            
            // Show loading state
            statusMessage.className = 'alert alert-info';
            statusMessage.style.display = 'block';
            statusMessage.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating payment verification setting...';
            
            fetch('ajax/toggle_payment_verification.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusMessage.className = 'alert alert-success';
                    statusMessage.innerHTML = '<i class="bi bi-check-circle"></i> ' + data.message;
                    toggleStatus.textContent = 'Payment Verification is ' + 
                        (data.new_status === '1' ? 'Required' : 'Optional');
                } else {
                    throw new Error(data.message || 'Failed to update setting');
                }
            })
            .catch(error => {
                statusMessage.className = 'alert alert-danger';
                statusMessage.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + error.message;
                // Revert the toggle if there was an error
                this.checked = !this.checked;
            });
        });

        function savePayment() {
            const form = document.getElementById('addPaymentForm');
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the payment.');
            });
        }
    </script>
</body>
</html>
