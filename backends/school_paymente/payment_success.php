<?php
require_once 'ctrl/db_config.php';

session_start();

if (!isset($_GET['reference'])) {
    header('Location: payment_interface.php');
    exit();
}

$reference = $_GET['reference'];

// Set the connection charset and collation
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4");
$conn->query("SET collation_connection = utf8mb4_unicode_ci");

// Get payment details
$sql = "SELECT p.*, pt.name as payment_type_name, s.first_name, s.last_name, s.class, s.email 
        FROM school_payments p 
        INNER JOIN school_payment_types pt ON p.payment_type_id = pt.id 
        INNER JOIN students s ON p.student_id = s.registration_number 
        WHERE p.reference_code = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reference);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();

if (!$payment) {
    header('Location: payment_interface.php');
    exit();
}

// Format the date
$payment_date = new DateTime($payment['payment_date']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - School Payment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --light-bg: #f8f9fa;
            --border-radius: 10px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        .success-checkmark {
            color: var(--success-color);
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .receipt-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-top: 2rem;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px dashed #dee2e6;
        }

        .receipt-body {
            margin-bottom: 2rem;
        }

        .receipt-footer {
            text-align: center;
            padding-top: 1rem;
            border-top: 2px dashed #dee2e6;
        }

        .info-group {
            margin-bottom: 1.5rem;
        }

        .info-group h6 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .total-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        @media print {
            .no-print {
                display: none;
            }
            body {
                background: white;
            }
            .receipt-card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-4">
            <div class="success-checkmark">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="text-success">Payment Successful!</h2>
            <p class="text-muted">Your payment has been processed successfully.</p>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Please Note:</strong> This payment will be reflected on your student dashboard after approval by the school administrator. The approval process typically takes less than 48 hours.
            </div>
        </div>

        <div class="receipt-card">
            <div class="receipt-header">
                <h3>Payment Receipt</h3>
                <p class="text-muted mb-0">Reference: <?php echo htmlspecialchars($reference); ?></p>
            </div>

            <div class="receipt-body">
                <div class="info-group">
                    <h6><i class="fas fa-user-graduate"></i> Student Information</h6>
                    <p class="mb-1">Name: <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
                    <p class="mb-1">Class: <?php echo htmlspecialchars($payment['class']); ?></p>
                    <p class="mb-0">Email: <?php echo htmlspecialchars($payment['email']); ?></p>
                </div>

                <div class="info-group">
                    <h6><i class="fas fa-file-invoice"></i> Payment Information</h6>
                    <p class="mb-1">Payment Type: <?php echo htmlspecialchars($payment['payment_type_name']); ?></p>
                    <p class="mb-1">Date: <?php echo $payment_date->format('F j, Y g:i A'); ?></p>
                    <p class="mb-0">Status: <span class="badge bg-success">Completed</span></p>
                </div>

                <div class="info-group">
                    <h6><i class="fas fa-money-bill-wave"></i> Amount Details</h6>
                    <div class="amount-row">
                        <span>Base Amount:</span>
                        <span>₦<?php echo number_format($payment['base_amount'], 2); ?></span>
                    </div>
                    <div class="amount-row">
                        <span>Service Charge:</span>
                        <span>₦<?php echo number_format($payment['service_charge'], 2); ?></span>
                    </div>
                    <div class="amount-row total-amount">
                        <span>Total Amount:</span>
                        <span>₦<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="receipt-footer">
                <p class="mb-0">Thank you for your payment!</p>
            </div>
        </div>

        <div class="action-buttons no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="payment_interface.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Payment Page
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 