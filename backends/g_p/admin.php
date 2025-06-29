<?php
require_once 'config.php';

session_start();

// Simple admin authentication (you can enhance this)
$admin_username = 'admin';
$admin_password = 'admin123';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = "Invalid username or password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle payment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    // if (isset($_SESSION['admin_logged_in'])) {
        $payment_id = $_POST['payment_id'];
        $new_status = $_POST['status'];
        $admin_notes = sanitizeInput($_POST['admin_notes']);
        
        try {
            $pdo = getDBConnection();
            
            // Update payment status
            $sql = "UPDATE payments SET verification_status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_status, $payment_id]);
            
            // Add to payment history
            $sql = "INSERT INTO payment_history (payment_id, status, notes, admin_user) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$payment_id, $new_status, $admin_notes, $admin_username]);
            
            $success_message = "Payment status updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    // }
}

// Check if admin is logged in
// if (!isset($_SESSION['admin_logged_in'])) {
    // Show login form
    // ?>
    <!-- <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - ACE Model College</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .login-card {
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
            .form-control {
                border-radius: 10px;
                border: 2px solid #e9ecef;
                padding: 12px 15px;
            }
            .form-control:focus {
                border-color: #667eea;
                box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            }
            .btn-login {
                background: linear-gradient(45deg, #667eea, #764ba2);
                border: none;
                border-radius: 50px;
                padding: 12px 30px;
                font-size: 1.1rem;
                font-weight: 600;
                color: white;
                transition: all 0.3s ease;
            }
            .btn-login:hover {
                transform: scale(1.05);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-lg-4 col-md-6">
                    <div class="login-card">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fas fa-user-shield me-3"></i>
                                Admin Login
                            </h2>
                            <p class="mb-0">ACE Model College Payment System</p>
                        </div>
                        <div class="card-body p-4">
                            <?php if (isset($login_error)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $login_error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-2"></i>Username
                                    </label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="text-center">
                                    <button type="submit" name="login" class="btn btn-login btn-lg w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Login
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-3">
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
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html> -->
    <?php
    // exit;
// }

// Fetch payments for admin dashboard
try {
    $pdo = getDBConnection();
    
    // Get filter parameters
    $filter_type = isset($_GET['type']) ? $_GET['type'] : '';
    $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
    $filter_category = isset($_GET['category']) ? $_GET['category'] : '';
    $filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    
    // Build query with filters
    $sql = "SELECT p.*, 
            (SELECT ph.status FROM payment_history ph WHERE ph.payment_id = p.id ORDER BY ph.created_at DESC LIMIT 1) as current_status,
            (SELECT ph.notes FROM payment_history ph WHERE ph.payment_id = p.id ORDER BY ph.created_at DESC LIMIT 1) as latest_notes
            FROM payments p 
            WHERE 1=1";
    $params = [];
    
    if ($filter_type) {
        $sql .= " AND p.payment_type = ?";
        $params[] = $filter_type;
    }
    
    if ($filter_status) {
        $sql .= " AND p.verification_status = ?";
        $params[] = $filter_status;
    }
    
    if ($filter_category) {
        $sql .= " AND p.payment_category = ?";
        $params[] = $filter_category;
    }
    
    if ($filter_start_date && !empty($filter_start_date)) {
        $sql .= " AND DATE(p.created_at) >= ?";
        $params[] = $filter_start_date;
    }
    
    if ($filter_end_date && !empty($filter_end_date)) {
        $sql .= " AND DATE(p.created_at) <= ?";
        $params[] = $filter_end_date;
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_sql = "SELECT 
                    payment_type,
                    verification_status,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                  FROM payments 
                  GROUP BY payment_type, verification_status";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment categories for filter dropdown
    $categories_sql = "SELECT DISTINCT payment_category FROM payments ORDER BY payment_category";
    $categories_stmt = $pdo->prepare($categories_sql);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ACE Model College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
            transition: transform 0.3s ease;
        }
        .payment-card:hover {
            transform: translateY(-5px);
        }
        .school-payment {
            border-left-color: #11998e;
        }
        .tutorial-payment {
            border-left-color: #fc466b;
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
            max-width: 150px;
            max-height: 100px;
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
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .btn-action {
            border-radius: 25px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-approve {
            background: #28a745;
            border: none;
            color: white;
        }
        .btn-reject {
            background: #dc3545;
            border: none;
            color: white;
        }
        .btn-approve:hover {
            background: #218838;
            color: white;
            transform: scale(1.05);
        }
        .btn-reject:hover {
            background: #c82333;
            color: white;
            transform: scale(1.05);
        }
        .quick-date-links {
            margin-top: 10px;
        }
        .quick-date-links a {
            margin-right: 10px;
            font-size: 0.9rem;
            color: #667eea;
            text-decoration: none;
        }
        .quick-date-links a:hover {
            text-decoration: underline;
        }
        .date-inputs {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px;
            border: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="mb-0">
                        <i class="fas fa-user-shield me-3"></i>
                        Admin Dashboard
                    </h2>
                    <p class="mb-0">ACE Model College Payment System</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group me-3" role="group">
                        <a href="https://acecollege.ng/backends/school_paymente/admin_payment_history.php" 
                           class="btn btn-outline-light" target="_blank">
                            <i class="fas fa-school me-2"></i>
                            School Payment History
                        </a>
                        <a href="https://acecollege.ng/backends/lesson/admin/payment/admin_payment_report.php" 
                           class="btn btn-outline-light" target="_blank">
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            Lesson Payment History
                        </a>
                    </div>
                    <a href="?logout=1" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h4 class="text-primary">Total Payments</h4>
                    <h2 class="mb-0"><?php echo count($payments); ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h4 class="text-warning">Pending</h4>
                    <h2 class="mb-0">
                        <?php echo count(array_filter($payments, function($p) { return $p['verification_status'] === 'pending'; })); ?>
                    </h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h4 class="text-success">Verified</h4>
                    <h2 class="mb-0">
                        <?php echo count(array_filter($payments, function($p) { return $p['verification_status'] === 'verified'; })); ?>
                    </h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h4 class="text-danger">Rejected</h4>
                    <h2 class="mb-0">
                        <?php echo count(array_filter($payments, function($p) { return $p['verification_status'] === 'rejected'; })); ?>
                    </h2>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <h5 class="mb-3">
                <i class="fas fa-filter me-2"></i>
                Filter Payments
            </h5>
            <form method="GET" class="row">
                <div class="col-md-2">
                    <label class="form-label">Payment Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="school" <?php echo $filter_type === 'school' ? 'selected' : ''; ?>>School Payment</option>
                        <option value="tutorial" <?php echo $filter_type === 'tutorial' ? 'selected' : ''; ?>>Tutorial Payment</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="verified" <?php echo $filter_status === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>" <?php echo $filter_category === $category ? 'selected' : ''; ?>>
                                <?php echo ucfirst($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <div class="date-inputs">
                        <div class="row">
                            <div class="col-6">
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?php echo $filter_start_date; ?>" 
                                       placeholder="From date">
                            </div>
                            <div class="col-6">
                                <input type="date" name="end_date" class="form-control" 
                                       value="<?php echo $filter_end_date; ?>" 
                                       placeholder="To date">
                            </div>
                        </div>
                        <div class="quick-date-links">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['start_date' => date('Y-m-d', strtotime('-7 days')), 'end_date' => date('Y-m-d')])); ?>">Last 7 days</a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['start_date' => date('Y-m-d', strtotime('-30 days')), 'end_date' => date('Y-m-d')])); ?>">Last 30 days</a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')])); ?>">This month</a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['start_date' => '', 'end_date' => ''])); ?>">Clear dates</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-2"></i>
                        Filter
                    </button>
                    <a href="admin.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Payments List -->
        <div class="payments-list">
            <?php if (empty($payments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No payments found</h4>
                    <p class="text-muted">No payments match your current filters.</p>
                </div>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <div class="payment-card <?php echo $payment['payment_type'] === 'school' ? 'school-payment' : 'tutorial-payment'; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="mb-2">
                                            <i class="fas fa-<?php echo $payment['payment_type'] === 'school' ? 'school text-success' : 'chalkboard-teacher text-primary'; ?> me-2"></i>
                                            <?php echo ucfirst($payment['payment_type']); ?> Payment
                                            <span class="badge bg-<?php echo $payment['payment_type'] === 'school' ? 'success' : 'primary'; ?> ms-2">
                                                <?php echo strtoupper($payment['payment_type']); ?>
                                            </span>
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
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <strong>Bank:</strong> <?php echo htmlspecialchars($payment['bank_name']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Account:</strong> <?php echo htmlspecialchars($payment['account_number']); ?>
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
                                    <span class="status-badge status-<?php echo $payment['verification_status']; ?>">
                                        <i class="fas fa-<?php 
                                            echo $payment['verification_status'] === 'pending' ? 'clock' : 
                                                ($payment['verification_status'] === 'verified' ? 'check-circle' : 'times-circle'); 
                                        ?> me-2"></i>
                                        <?php echo ucfirst($payment['verification_status']); ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <img src="<?php echo UPLOAD_DIR . $payment['receipt_image']; ?>" 
                                         alt="Receipt" 
                                         class="receipt-image"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#receiptModal"
                                         data-receipt="<?php echo UPLOAD_DIR . $payment['receipt_image']; ?>">
                                </div>
                                
                                <?php if ($payment['verification_status'] === 'pending'): ?>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-action btn-approve" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#actionModal"
                                                data-payment-id="<?php echo $payment['id']; ?>"
                                                data-action="verified"
                                                data-payment-info="<?php echo htmlspecialchars($payment['depositor_name'] . ' - ' . $payment['student_name'] . ' - ₦' . number_format($payment['amount'], 2)); ?>">
                                            <i class="fas fa-check me-1"></i>
                                            Approve
                                        </button>
                                        <button type="button" class="btn btn-action btn-reject"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#actionModal"
                                                data-payment-id="<?php echo $payment['id']; ?>"
                                                data-action="rejected"
                                                data-payment-info="<?php echo htmlspecialchars($payment['depositor_name'] . ' - ' . $payment['student_name'] . ' - ₦' . number_format($payment['amount'], 2)); ?>">
                                            <i class="fas fa-times me-1"></i>
                                            Reject
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Already <?php echo $payment['verification_status']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Payment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="payment_id" id="actionPaymentId">
                        <input type="hidden" name="status" id="actionStatus">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Details</label>
                            <p class="form-control-plaintext" id="actionPaymentInfo"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                      placeholder="Add any notes about this payment..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Are you sure you want to <span id="actionText"></span> this payment?
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Update Status
                        </button>
                    </div>
                </form>
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
            
            // Handle action modal
            const actionButtons = document.querySelectorAll('[data-bs-target="#actionModal"]');
            actionButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const paymentId = this.getAttribute('data-payment-id');
                    const action = this.getAttribute('data-action');
                    const paymentInfo = this.getAttribute('data-payment-info');
                    
                    document.getElementById('actionPaymentId').value = paymentId;
                    document.getElementById('actionStatus').value = action;
                    document.getElementById('actionPaymentInfo').textContent = paymentInfo;
                    document.getElementById('actionText').textContent = action === 'verified' ? 'approve' : 'reject';
                });
            });
        });
    </script>
</body>
</html>
