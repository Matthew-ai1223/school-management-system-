<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Get all payments with latest status
    $sql = "SELECT p.*, 
            (SELECT ph.status FROM payment_history ph WHERE ph.payment_id = p.id ORDER BY ph.created_at DESC LIMIT 1) as current_status,
            (SELECT ph.notes FROM payment_history ph WHERE ph.payment_id = p.id ORDER BY ph.created_at DESC LIMIT 1) as latest_notes
            FROM payments p 
            ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - ACE Model College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .history-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 2rem;
            text-align: center;
        }
        .payment-item {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
            transition: transform 0.3s ease;
        }
        .payment-item:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-verified {
            background: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .receipt-image {
            max-width: 200px;
            max-height: 150px;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .receipt-image:hover {
            transform: scale(1.1);
        }
        .modal-image {
            max-width: 100%;
            max-height: 80vh;
        }
        .stats-card {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="history-card">
                    <div class="card-header">
                        <h1 class="mb-0">
                            <i class="fas fa-history me-3"></i>
                            Payment History
                        </h1>
                        <p class="mb-0">View all your submitted payments and their status</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php else: ?>
                            <!-- Statistics -->
                            <div class="stats-card">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo count($payments); ?></div>
                                            <div>Total Payments</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-item">
                                            <div class="stat-number">
                                                <?php echo count(array_filter($payments, function($p) { return $p['current_status'] === 'pending'; })); ?>
                                            </div>
                                            <div>Pending</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-item">
                                            <div class="stat-number">
                                                <?php echo count(array_filter($payments, function($p) { return $p['current_status'] === 'verified'; })); ?>
                                            </div>
                                            <div>Verified</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-item">
                                            <div class="stat-number">
                                                <?php echo count(array_filter($payments, function($p) { return $p['current_status'] === 'rejected'; })); ?>
                                            </div>
                                            <div>Rejected</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($payments)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                    <h4 class="text-muted">No payments found</h4>
                                    <p class="text-muted">You haven't submitted any payments yet.</p>
                                    <a href="index.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>
                                        Make a Payment
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="payments-list">
                                    <?php foreach ($payments as $payment): ?>
                                        <div class="payment-item">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5 class="mb-2">
                                                                <i class="fas fa-<?php echo $payment['payment_type'] === 'school' ? 'school' : 'chalkboard-teacher'; ?> me-2"></i>
                                                                <?php echo ucfirst($payment['payment_type']); ?> Payment
                                                            </h5>
                                                            <p class="mb-1">
                                                                <strong>Category:</strong> 
                                                                <span class="badge bg-info"><?php echo htmlspecialchars($payment['payment_category']); ?></span>
                                                            </p>
                                                            <p class="mb-1">
                                                                <strong>Depositor:</strong> <?php echo htmlspecialchars($payment['depositor_name']); ?>
                                                            </p>
                                                            <p class="mb-1">
                                                                <strong>Student:</strong> <?php echo htmlspecialchars($payment['student_name']); ?>
                                                            </p>
                                                            <?php if ($payment['payment_type'] === 'school' && !empty($payment['student_class'])): ?>
                                                                <p class="mb-1">
                                                                    <strong>Class:</strong> <?php echo htmlspecialchars($payment['student_class']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <?php if ($payment['payment_type'] === 'school' && !empty($payment['registration_number'])): ?>
                                                                <p class="mb-1">
                                                                    <strong>Reg. No:</strong> <?php echo htmlspecialchars($payment['registration_number']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <p class="mb-1">
                                                                <strong>Amount:</strong> ₦<?php echo number_format($payment['amount'], 2); ?>
                                                            </p>
                                                            <p class="mb-1">
                                                                <strong>Date:</strong> <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?>
                                                            </p>
                                                            <p class="mb-1">
                                                                <strong>Bank:</strong> <?php echo htmlspecialchars($payment['bank_name']); ?>
                                                            </p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p class="mb-1">
                                                                <strong>Account:</strong> <?php echo htmlspecialchars($payment['account_number']); ?>
                                                            </p>
                                                            <p class="mb-1">
                                                                <strong>Account Name:</strong> <?php echo htmlspecialchars($payment['account_name']); ?>
                                                            </p>
                                                            <p class="mb-1">
                                                                <strong>Submitted:</strong> <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                                                            </p>
                                                            <?php if ($payment['latest_notes']): ?>
                                                                <p class="mb-1">
                                                                    <strong>Notes:</strong> <?php echo htmlspecialchars($payment['latest_notes']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-center">
                                                    <div class="mb-3">
                                                        <span class="status-badge status-<?php echo $payment['current_status']; ?>">
                                                            <i class="fas fa-<?php 
                                                                echo $payment['current_status'] === 'pending' ? 'clock' : 
                                                                    ($payment['current_status'] === 'verified' ? 'check-circle' : 'times-circle'); 
                                                            ?> me-2"></i>
                                                            <?php echo ucfirst($payment['current_status']); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <img src="<?php echo UPLOAD_DIR . $payment['receipt_image']; ?>" 
                                                             alt="Receipt" 
                                                             class="receipt-image"
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#receiptModal"
                                                             data-receipt="<?php echo UPLOAD_DIR . $payment['receipt_image']; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to Payment System
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalReceiptImage" src="" alt="Receipt" class="modal-image">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle receipt image modal
        document.addEventListener('DOMContentLoaded', function() {
            const receiptImages = document.querySelectorAll('.receipt-image');
            const modalImage = document.getElementById('modalReceiptImage');
            
            receiptImages.forEach(function(img) {
                img.addEventListener('click', function() {
                    const receiptSrc = this.getAttribute('data-receipt');
                    modalImage.src = receiptSrc;
                });
            });
        });
    </script>
</body>
</html> 