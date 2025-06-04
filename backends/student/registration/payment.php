<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../utils.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

// Get student information
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$registration_number = $_SESSION['registration_number'];

// Connect to database
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student details
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get payment history
$payments_query = "SELECT p.*, 
                         DATE_FORMAT(p.payment_date, '%M %d, %Y') as formatted_date,
                         DATE_FORMAT(p.created_at, '%M %d, %Y %h:%i %p') as created_date
                  FROM payments p
                  WHERE p.student_id = ? 
                  ORDER BY p.payment_date DESC, p.created_at DESC";

$stmt = $conn->prepare($payments_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$payments_result = $stmt->get_result();

// Calculate payment statistics
$total_paid = 0;
$total_pending = 0;
$payments = [];

while ($payment = $payments_result->fetch_assoc()) {
    $payments[] = $payment;
    if ($payment['status'] === 'completed') {
        $total_paid += $payment['amount'];
    } elseif ($payment['status'] === 'pending') {
        $total_pending += $payment['amount'];
    }
}

// Get payment types for new payment form
$payment_types_query = "SELECT DISTINCT payment_type FROM payments WHERE payment_type IS NOT NULL";
$payment_types_result = $conn->query($payment_types_query);
$payment_types = [];
while ($row = $payment_types_result->fetch_assoc()) {
    $payment_types[] = $row['payment_type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - <?php echo SCHOOL_NAME; ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1a237e;
            --secondary-color: #0d47a1;
            --accent-color: #2962ff;
            --success-color: #43a047;
            --warning-color: #ffb300;
            --danger-color: #e53935;
            --light-bg: #e3f2fd;
            --border-radius: 8px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Source Sans Pro', Arial, sans-serif;
        }

        .container {
            padding: 2rem;
        }

        .payment-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }

        .payment-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--accent-color);
        }

        .table-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .table thead th {
            background-color: var(--light-bg);
            border-bottom: 2px solid var(--primary-color);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 30px;
        }

        .btn-payment {
            background-color: var(--accent-color);
            color: white;
            border-radius: 30px;
            padding: 0.5rem 1.5rem;
        }

        .btn-payment:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .back-button {
            margin-bottom: 1rem;
        }

        .payment-form {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <div class="back-button">
            <a href="student_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Payment Header -->
        <div class="payment-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-money-bill-wave"></i> Payment Management</h2>
                    <p class="mb-0">Student: <?php echo htmlspecialchars($student_name); ?></p>
                    <p class="mb-0">Registration Number: <?php echo htmlspecialchars($registration_number); ?></p>
                </div>
                <div class="col-md-4 text-md-right">
                    <button class="btn btn-light" data-toggle="modal" data-target="#newPaymentModal">
                        <i class="fas fa-plus"></i> Make New Payment
                    </button>
                </div>
            </div>
        </div>

        <!-- Payment Statistics -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <h5>Total Paid</h5>
                    <h3 class="text-success">₦<?php echo number_format($total_paid, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h5>Pending Payments</h5>
                    <h3 class="text-warning">₦<?php echo number_format($total_pending, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h5>Total Transactions</h5>
                    <h3 class="text-primary"><?php echo count($payments); ?></h3>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="table-container">
            <h4 class="mb-4"><i class="fas fa-history"></i> Payment History</h4>
            <?php if (count($payments) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Payment Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <?php echo $payment['formatted_date']; ?>
                                <small class="d-block text-muted">
                                    Created: <?php echo $payment['created_date']; ?>
                                </small>
                            </td>
                            <td>
                                <?php 
                                $payment_type = str_replace('_', ' ', $payment['payment_type']);
                                echo ucwords($payment_type); 
                                ?>
                            </td>
                            <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                            <td>
                                <?php 
                                $method = str_replace('_', ' ', $payment['payment_method']);
                                echo ucwords($method); 
                                ?>
                            </td>
                            <td>
                                <?php if ($payment['reference_number']): ?>
                                    <span class="text-primary"><?php echo $payment['reference_number']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_class = '';
                                switch ($payment['status']) {
                                    case 'completed':
                                        $status_class = 'success';
                                        break;
                                    case 'pending':
                                        $status_class = 'warning';
                                        break;
                                    case 'failed':
                                        $status_class = 'danger';
                                        break;
                                    default:
                                        $status_class = 'secondary';
                                }
                                ?>
                                <span class="badge badge-<?php echo $status_class; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($payment['notes']): ?>
                                    <span class="text-muted"><?php echo htmlspecialchars($payment['notes']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No payment records found.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- New Payment Modal -->
    <div class="modal fade" id="newPaymentModal" tabindex="-1" role="dialog" aria-labelledby="newPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="newPaymentModalLabel">Make New Payment</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm" action="process_payment.php" method="POST">
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        
                        <div class="form-group">
                            <label for="payment_type">Payment Type</label>
                            <select class="form-control" id="payment_type" name="payment_type" required>
                                <option value="">Select Payment Type</option>
                                <?php foreach ($payment_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="amount">Amount (₦)</label>
                            <input type="number" class="form-control" id="amount" name="amount" required min="0" step="0.01">
                        </div>

                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select class="form-control" id="payment_method" name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="card_payment">Card Payment</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reference_number">Reference Number (Optional)</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number">
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Please verify all details before proceeding with the payment.
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Proceed with Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    $(document).ready(function() {
        // Handle payment form submission
        $('#paymentForm').on('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
            
            $.ajax({
                url: 'process_payment.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        
                        if (data.status === 'success') {
                            alert('Payment processed successfully!');
                            location.reload(); // Reload page to show new payment
                        } else {
                            alert('Error processing payment: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error processing response: ' + response);
                    }
                    
                    // Reset button state
                    submitBtn.html(originalText).prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    alert('An error occurred while processing the payment: ' + error);
                    submitBtn.html(originalText).prop('disabled', false);
                }
            });
        });
    });
    </script>
</body>
</html>
