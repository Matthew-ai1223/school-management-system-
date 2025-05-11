<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../payment_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize database connection
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error_message = '';

try {
    if (!isset($_GET['reference'])) {
        throw new Exception("No payment reference provided");
    }

    $reference = $_GET['reference'];
    
    // Get payment details from database
    $sql = "SELECT * FROM application_payments WHERE reference = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $reference);
    if (!$stmt->execute()) {
        throw new Exception("Database query failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    
    if (!$payment) {
        throw new Exception("Invalid payment reference");
    }

    // Verify payment hasn't already been processed
    if ($payment['status'] !== PAYMENT_STATUS_PENDING) {
        throw new Exception("Payment has already been processed");
    }

    // Store payment details in session
    $_SESSION['payment_reference'] = $payment['reference'];
    $_SESSION['payment_amount'] = $payment['amount'];
    $_SESSION['payment_email'] = $payment['email'];
    $_SESSION['application_type'] = $payment['application_type'];

} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Payment Processing Error: " . $error_message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://js.paystack.co/v1/inline.js"></script>
</head>
<body>
    <div class="container mt-5">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <h4>Error Processing Payment</h4>
                <p><?php echo htmlspecialchars($error_message); ?></p>
                <a href="payment.php" class="btn btn-primary">Return to Payment Page</a>
            </div>
        <?php else: ?>
            <div class="text-center" id="loadingSection">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h4 class="mt-3">Initializing Payment...</h4>
                <p class="text-muted">Please wait while we connect to Paystack...</p>
                <div id="paymentDetails" class="mt-4">
                    <p><strong>Amount:</strong> ₦<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['email']); ?></p>
                    <p><strong>Reference:</strong> <?php echo htmlspecialchars($payment['reference']); ?></p>
                </div>
            </div>
            
            <div class="alert alert-danger mt-4 d-none" id="errorSection">
                <h4>Payment Error</h4>
                <p id="errorMessage"></p>
                <a href="payment.php" class="btn btn-primary">Return to Payment Page</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$error_message): ?>
    <script>
        // Function to show error
        function showError(message) {
            document.getElementById('loadingSection').classList.add('d-none');
            document.getElementById('errorSection').classList.remove('d-none');
            document.getElementById('errorMessage').textContent = message;
            console.error('Payment error:', message);
        }

        // Function to initialize Paystack
        function initializePaystack() {
            try {
                console.log('Initializing Paystack...');
                let handler = PaystackPop.setup({
                    key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                    email: '<?php echo $payment['email']; ?>',
                    amount: <?php echo $payment['amount'] * 100; ?>,
                    currency: 'NGN',
                    ref: '<?php echo $payment['reference']; ?>',
                    metadata: {
                        custom_fields: [
                            {
                                display_name: "Application Type",
                                variable_name: "application_type",
                                value: "<?php echo ucfirst($payment['application_type']); ?>"
                            },
                            {
                                display_name: "Full Name",
                                variable_name: "full_name",
                                value: "<?php echo $payment['full_name']; ?>"
                            }
                        ]
                    },
                    callback: function(response) {
                        console.log('Payment callback received:', response);
                        window.location.href = 'verify_paystack.php?reference=' + response.reference;
                    },
                    onClose: function() {
                        console.log('Payment window closed');
                        window.location.href = 'payment.php?payment_status=cancelled&type=<?php echo $payment['application_type']; ?>';
                    }
                });
                
                console.log('Opening Paystack iframe...');
                handler.openIframe();
            } catch (error) {
                console.error('Paystack initialization error:', error);
                showError(error.message || 'Failed to initialize payment. Please try again.');
            }
        }

        // Initialize when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Document ready, waiting 1 second before initializing Paystack...');
            // Short delay to ensure everything is loaded
            setTimeout(initializePaystack, 1000);
        });
    </script>
    <?php endif; ?>
</body>
</html> 