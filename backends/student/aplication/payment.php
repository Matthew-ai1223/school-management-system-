<?php
// Session configuration
ini_set('session.cookie_lifetime', 3600); // 1 hour
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.use_strict_mode', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cache_limiter', 'nocache');
session_name('ACE_APPLICATION');

require_once '../../config.php';
require_once '../../database.php';
require_once '../../auth.php';
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

// Get application type from URL parameter or form submission
$applicationType = isset($_POST['application_type']) ? $_POST['application_type'] : (isset($_GET['type']) ? $_GET['type'] : 'kiddies');
if (!in_array($applicationType, ['kiddies', 'college'])) {
    $applicationType = 'kiddies';
}

// Get fee amount based on application type
$fee_amount = $applicationType === 'kiddies' ? KIDDIES_APPLICATION_FEE : COLLEGE_APPLICATION_FEE;

// Handle payment submission
if (isset($_POST['process_payment'])) {
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Validate required fields
        $required_fields = ['payment_method', 'email', 'phone', 'full_name', 'application_type'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        $payment_method = $_POST['payment_method'];
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new Exception("Invalid email address");
        }
        $phone = $_POST['phone'];
        $full_name = $_POST['full_name'];
        $applicationType = $_POST['application_type'];
        
        // Generate unique reference (only alphanumeric characters)
        $prefix = $applicationType === 'kiddies' ? 'KID' : 'COL';
        $timestamp = time();
        $random = rand(1000, 9999);
        $reference = $prefix . $timestamp . $random;
        
        // Check if reference is already used (just in case)
        while (isReferenceUsed($conn, $reference)) {
            $random = rand(1000, 9999);
            $reference = $prefix . $timestamp . $random;
        }
        
        // Save payment record
        $sql = "INSERT INTO application_payments (
            reference, 
            application_type, 
            amount, 
            payment_method, 
            email, 
            phone, 
            full_name, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }

        $status = PAYMENT_STATUS_PENDING;
        $stmt->bind_param("ssdsssss", 
            $reference,
            $applicationType,
            $fee_amount,
            $payment_method,
            $email,
            $phone,
            $full_name,
            $status
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save payment record: " . $stmt->error);
        }

        // Store in session with timestamp
        $_SESSION['payment_reference'] = $reference;
        $_SESSION['payment_amount'] = $fee_amount;
        $_SESSION['payment_email'] = $email;
        $_SESSION['application_type'] = $applicationType;
        $_SESSION['payment_timestamp'] = time();
        
        // Log session data
        error_log("Payment session data set: " . print_r($_SESSION, true));
        
        // For AJAX requests, return the reference
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['reference' => $reference]);
            exit;
        }
        
        // For regular form submissions
        header("Location: process_paystack.php?reference=" . urlencode($reference));
        exit();

    } catch (Exception $e) {
        $error_message = "Payment Error: " . $e->getMessage();
        error_log($error_message);
        error_log("Debug backtrace: " . print_r(debug_backtrace(), true));
        
        // For AJAX requests, return error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(400);
            echo json_encode(['error' => $error_message]);
            exit;
        }
    }
}

// Handle payment verification
if (isset($_POST['verify_reference'])) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $reference = trim($_POST['payment_reference']);
        error_log("Verifying reference: " . $reference);
        
        // First check if this reference has already been used in an application
        $sql = "SELECT a.* FROM applications a 
                WHERE JSON_EXTRACT(a.applicant_data, '$.payment_reference') = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("This payment reference has already been used for another application.");
        }
        
        // Now verify the reference exists and payment is completed
        $sql = "SELECT * FROM application_payments WHERE reference = ? AND status = ?";
        $stmt = $conn->prepare($sql);
        $completed_status = PAYMENT_STATUS_COMPLETED;
        $stmt->bind_param("ss", $reference, $completed_status);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        error_log("Payment verification result: " . print_r($payment, true));
        
        if ($payment) {
            // Store in session and redirect to application form
            $_SESSION['payment_verified'] = true;
            $_SESSION['payment_reference'] = $payment['reference'];
            $_SESSION['application_type'] = $payment['application_type'];
            $_SESSION['payment_timestamp'] = time();
            
            error_log("Payment verified successfully. Session data: " . print_r($_SESSION, true));
            
            $redirect_url = "application_form.php?type=" . urlencode($payment['application_type']) . 
                          "&reference=" . urlencode($reference) . 
                          "&timestamp=" . time();
            
            header("Location: " . $redirect_url);
            exit();
        } else {
            throw new Exception("Invalid reference code or payment not completed. Please check your reference code and try again.");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Payment verification error: " . $error_message);
        error_log("Debug backtrace: " . print_r(debug_backtrace(), true));
    }
}

// Handle payment status messages
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
if ($payment_status === 'failed') {
    $error_message = "Payment failed. Please try again.";
} elseif ($payment_status === 'cancelled') {
    $error_message = "Payment was cancelled. Please try again.";
} elseif ($payment_status === 'invalid') {
    $error_message = isset($_GET['error']) ? $_GET['error'] : "Invalid payment request. Please try again.";
}

// Add this function to check if reference is already used
function isReferenceUsed($conn, $reference) {
    $sql = "SELECT a.* FROM applications a 
            WHERE JSON_EXTRACT(a.applicant_data, '$.payment_reference') = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Fee Payment - <?php echo SCHOOL_NAME; ?></title>
    
    <!-- Include the same CSS as apply.html -->
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:300,400,600,700" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:300,400" rel="stylesheet">
    <link rel="stylesheet" href="../../../css/bootstrap.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Add SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <!-- Add SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Base styles */
        :root {
            --primary-color: #1a237e;
            --secondary-color: #2962ff;
            --success-color: #00c853;
            --warning-color: #ffd600;
            --danger-color: #f50057;
            --light-gray: #f8f9fa;
            --border-color: #e0e0e0;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 15px;
            --transition: all 0.3s ease;
        }

        /* Container styles */
        .payment-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0;
        }

        /* Header section */
        .school-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 30px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            text-align: center;
            margin-bottom: 0;
        }

        .school-header h1 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .school-header h2 {
            font-size: 1.5em;
            font-weight: 400;
            opacity: 0.9;
        }

        /* Card styles */
        .card {
            background: white;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            overflow: hidden;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-body {
            padding: 30px;
        }

        .card-title {
            color: var(--primary-color);
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 20px;
        }

        /* Form styles */
        .form-label {
            font-weight: 500;
            color: #444;
            margin-bottom: 8px;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(41, 98, 255, 0.15);
        }

        .form-control::placeholder {
            color: #aaa;
        }

        /* Button styles */
        .btn {
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 50px;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            box-shadow: 0 4px 15px rgba(41, 98, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(41, 98, 255, 0.4);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Alert styles */
        .alert {
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            border: none;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-danger {
            background-color: rgba(245, 0, 87, 0.1);
            color: var(--danger-color);
        }

        .alert-success {
            background-color: rgba(0, 200, 83, 0.1);
            color: var(--success-color);
        }

        /* Badge styles */
        .badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-success {
            background-color: var(--success-color);
            color: white;
        }

        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }

        /* Fee amount display */
        .fee-amount {
            font-size: 2.5em;
            font-weight: 700;
            color: var(--primary-color);
            margin: 25px 0;
            text-align: center;
            display: block;
        }

        .fee-amount::before {
            content: '₦';
            font-size: 0.7em;
            vertical-align: top;
            margin-right: 2px;
        }

        /* Application type selector */
        .application-type-switch {
            background: var(--light-gray);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        .btn-check:checked + .btn-outline-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
        }

        /* Reference section */
        .reference-section {
            background: var(--light-gray);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-top: 40px;
            border-left: 4px solid var(--primary-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .payment-container {
                margin: 20px;
            }

            .school-header {
                padding: 30px 20px;
            }

            .school-header h1 {
                font-size: 2em;
            }

            .card-body {
                padding: 20px;
            }

            .fee-amount {
                font-size: 2em;
            }
        }

        /* Loading state */
        .btn .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
            margin-right: 8px;
        }

        /* Custom checkbox/radio styles */
        .btn-check:checked + .btn-outline-primary .fee-amount {
            color: white;
        }

        /* SweetAlert2 Custom Styles */
        .swal-wide {
            max-width: 500px !important;
            font-family: 'Source Sans Pro', sans-serif !important;
        }
        .swal-title {
            color: var(--danger-color) !important;
            font-weight: 600 !important;
        }
        .swal-button {
            font-weight: 600 !important;
            padding: 12px 25px !important;
            border-radius: 50px !important;
        }
        .swal2-popup {
            border-radius: var(--border-radius) !important;
        }
        .swal2-icon {
            border-color: var(--danger-color) !important;
            color: var(--danger-color) !important;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="fh5co-nav" role="navigation">
        <div class="top">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12 text-right">
                        <p class="site">www.acecollege.com</p>
                        <p class="num">Call: +234 803 465 0368</p>
                        <ul class="fh5co-social">
                            <li><a href="https://www.facebook.com/p/ACE-Model-College-100083036906992/"><i class="icon-facebook2"></i></a></li>
                            <li><a href="https://www.instagram.com/ace_model_college/"><i class="icon-instagram"></i></a></li>
                            <li><a href="https://www.youtube.com/@ace_model_college"><i class="icon-youtube"></i></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="top-menu">
            <div class="container">
                <div class="row">
                    <div class="col-xs-2">
                        <div id="fh5co-logo" style="display: flex; align-items: center; gap: 10px;">
                            <a href="../../../index.html"><img src="../../../images/logo.png" alt="ACE College Logo" style="width: 100px; height: 70px;"></a>
                            <a href="../../../index.html"><img src="../../../images/logo_2.jpg" alt="ACE Kiddies Logo" style="width: 70px; height: 70px; border-radius: 50%;"></a>
                        </div>
                    </div>
                    <div class="col-xs-10 text-right menu-1">
                        <ul>
                            <li><a href="../../../index.html">Home</a></li>
                            <li><a href="../../../gallery.html">Gallery</a></li>
                            <li><a href="../../../about.html">About</a></li>
                            <li><a href="../../../contact.html">Contact</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container payment-container">
        <div class="school-header">
            <h1>ACE MODEL COLLEGE</h1>
            <h2>Application Fee Payment</h2>
            <?php if (defined('PAYSTACK_PUBLIC_KEY')): ?>
                <div class="badge badge-success">Payment System Ready</div>
            <?php else: ?>
                <div class="badge badge-danger">Payment System Not Configured</div>
            <?php endif; ?>
        </div>

        <?php 
        // Remove the Bootstrap alert and add script to show SweetAlert
        if (isset($error_message)): 
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: '<?php echo addslashes(htmlspecialchars($error_message)); ?>',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1a237e',
                    customClass: {
                        popup: 'swal-wide',
                        title: 'swal-title',
                        confirmButton: 'swal-button'
                    }
                });
            });
        </script>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <!-- <h5 class="card-title">Select Application Type</h5>
                <div class="application-type-switch">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="app_type" id="kiddies" value="kiddies" <?php echo $applicationType === 'kiddies' ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-primary" for="kiddies">
                            Kiddies Application
                            <div class="fee-amount"><?php echo number_format(KIDDIES_APPLICATION_FEE, 2); ?></div>
                        </label>

                        <input type="radio" class="btn-check" name="app_type" id="college" value="college" <?php echo $applicationType === 'college' ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-primary" for="college">
                            College Application
                            <div class="fee-amount"><?php echo number_format(COLLEGE_APPLICATION_FEE, 2); ?></div>
                        </label>
                    </div>
                </div> -->

                <form method="POST" id="paymentForm" class="needs-validation" novalidate>
                    <input type="hidden" name="application_type" id="application_type" value="<?php echo htmlspecialchars($applicationType); ?>">
                    <input type="hidden" name="payment_method" value="paystack">

                    <div class="mb-4">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required 
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        <div class="invalid-feedback">Please enter your full name</div>
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <div class="invalid-feedback">Please enter a valid email address</div>
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        <div class="invalid-feedback">Please enter your phone number</div>
                    </div>

                    <div class="d-grid gap-3">
                        <button type="button" onclick="processPayment()" class="btn btn-primary btn-lg" id="submitBtn">
                            Pay Now (₦<?php echo number_format($fee_amount, 2); ?>)
                        </button>
                        <a href="../../../index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="reference-section">
            <h5 class="card-title">Already Made a Payment?</h5>
            <p class="text-muted">If you've already made a payment but couldn't proceed, go to your Gmail account used to make the payment and check your Inbox for the payment receipt, copy the reference code and paste it below.</p>
            <p class="text-muted">If you don't have a payment receipt, please <a href="https://www.acecollege.com/contact.html">Contact</a> the college for assistance.</p>
            <form method="POST" id="referenceForm" class="mt-3">
                <div class="mb-3">
                    <label for="payment_reference" class="form-label">Payment Reference Code</label>
                    <input type="text" class="form-control" id="payment_reference" name="payment_reference" 
                           placeholder="Enter your payment reference code" required>
                </div>
                <button type="submit" name="verify_reference" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Verify Reference
                </button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer id="fh5co-footer" role="contentinfo" style="background-image: url(../../../images/foote.png);">
        <div class="overlay"></div>
        <div class="container">
            <div class="row row-pb-md">
                <div class="col-md-3 fh5co-widget" style="text-align:center;">
                    <img src="../../../images/logo.png" alt="ACE College Logo" style="width:90px; height:65px; margin-bottom:15px; display:block; margin-left:auto; margin-right:auto;">
                    <h3>About ACE College</h3>
                    <p>ACE College is committed to providing quality education that develops academic excellence, moral values, and leadership skills in our students.</p>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6 col-md-push-1 fh5co-widget">
                    <h3>Quick Links</h3>
                    <ul class="fh5co-footer-links">
                        <li><a href="../../../index.html">Home</a></li>
                        <li><a href="../../../gallery.html">Gallery</a></li>
                        <li><a href="../../../about.html">About</a></li>
                        <li><a href="../../../contact.html">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6 col-md-push-1 fh5co-widget">
                    <h3>Connect with Us</h3>
                    <div style="margin: 18px 0 8px 0;">
                        <a href="https://www.facebook.com/p/ACE-Model-College-100083036906992/" target="_blank" style="margin:0 8px; color:#3b5998; font-size:22px;"><i class="icon-facebook2"></i></a>
                        <a href="https://www.instagram.com/ace_model_college/" target="_blank" style="margin:0 8px; color:#E1306C; font-size:22px;"><i class="icon-instagram"></i></a>
                        <a href="https://www.youtube.com/@ace_model_college" target="_blank" style="margin:0 8px; color:#FF0000; font-size:22px;"><i class="icon-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="row copyright">
                <div class="col-md-12 text-center">
                    <p>
                        <small class="block">&copy; <span id="footer-year"></span> ACE College. All Rights Reserved.</small>
                        <small class="block">Designed by ACE College IT Department</small>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <div class="gototop js-top">
        <a href="#" class="js-gotop"><i class="icon-arrow-up"></i></a>
    </div>

    <!-- JavaScript -->
    <script src="../../../js/jquery.min.js"></script>
    <script src="../../../js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var yearSpan = document.getElementById('footer-year');
            if (yearSpan) {
                yearSpan.textContent = new Date().getFullYear();
            }
        });

        // Handle application type selection
        document.querySelectorAll('input[name="app_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const applicationType = this.value;
                document.getElementById('application_type').value = applicationType;
                const feeAmount = applicationType === 'kiddies' ? <?php echo KIDDIES_APPLICATION_FEE; ?> : <?php echo COLLEGE_APPLICATION_FEE; ?>;
                document.getElementById('submitBtn').innerHTML = `Pay Now (₦${feeAmount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})})`;
            });
        });

        // Form validation
        const form = document.getElementById('paymentForm');
        const submitBtn = document.getElementById('submitBtn');

        function processPayment() {
            console.log('Processing payment...');
            
            if (!form.checkValidity()) {
                console.log('Form validation failed');
                form.classList.add('was-validated');
                
                // Show SweetAlert for validation errors
                Swal.fire({
                    title: 'Form Validation Error',
                    text: 'Please fill in all required fields correctly.',
                    icon: 'warning',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1a237e'
                });
                return;
            }

            const fullName = document.getElementById('full_name').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const applicationType = document.getElementById('application_type').value;
            const amount = applicationType === 'kiddies' ? <?php echo KIDDIES_APPLICATION_FEE; ?> : <?php echo COLLEGE_APPLICATION_FEE; ?>;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

            // Save payment record first
            const formData = new FormData();
            formData.append('process_payment', '1');
            formData.append('payment_method', 'paystack');
            formData.append('full_name', fullName);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('application_type', applicationType);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                if (!data.reference) {
                    throw new Error('No payment reference received');
                }
                initializePaystack(data.reference, email, amount, fullName, applicationType);
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = `Pay Now (₦${amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})})`;
                
                // Show SweetAlert for payment errors
                Swal.fire({
                    title: 'Payment Error',
                    text: error.message || 'Error initializing payment. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1a237e'
                });
            });
        }

        function initializePaystack(reference, email, amount, fullName, applicationType) {
            console.log('Initializing Paystack...', { reference, email, amount });
            
            try {
                let handler = PaystackPop.setup({
                    key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                    email: email,
                    amount: amount * 100, // Convert to kobo
                    currency: 'NGN',
                    ref: reference,
                    metadata: {
                        custom_fields: [
                            {
                                display_name: "Application Type",
                                variable_name: "application_type",
                                value: applicationType
                            },
                            {
                                display_name: "Full Name",
                                variable_name: "full_name",
                                value: fullName
                            }
                        ]
                    },
                    callback: function(response) {
                        console.log('Payment callback received:', response);
                        window.location.href = `verify_paystack.php?reference=${response.reference}`;
                    },
                    onClose: function() {
                        console.log('Payment window closed');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = `Pay Now (₦${amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})})`;
                    }
                });

                handler.openIframe();
            } catch (error) {
                console.error('Paystack initialization error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = `Pay Now (₦${amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})})`;
                alert('Failed to initialize payment. Please try again.');
            }
        }

        // Phone number validation
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.substr(0, 11);
            }
            this.value = value;
        });

        // Email validation
        document.getElementById('email').addEventListener('input', function() {
            this.value = this.value.trim();
        });

        // Add SweetAlert for reference verification errors
        document.getElementById('referenceForm').addEventListener('submit', function(e) {
            const referenceInput = document.getElementById('payment_reference');
            if (!referenceInput.value.trim()) {
                e.preventDefault();
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please enter a payment reference code.',
                    icon: 'warning',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1a237e'
                });
            }
        });
    </script>
</body>
</html>
