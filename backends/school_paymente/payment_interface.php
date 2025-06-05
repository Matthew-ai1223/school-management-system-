<?php
require_once 'ctrl/db_config.php';
require_once 'ctrl/payment_types.php';

session_start();

// Replace with your PayStack public key
define('PAYSTACK_PUBLIC_KEY', 'pk_test_fff1d31f74a43da37f1322e466e0e27d1c1900f7');
define('PAYSTACK_SECRET_KEY', 'sk_test_ba85c77b3ea04ae33627b38ca46cf8e3b5a4edc5');

$paymentTypes = new PaymentTypes($conn);
$available_payments = $paymentTypes->getPaymentTypes();

// Function to get student details
function getStudentDetails($reg_number) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM students WHERE registration_number = ?");
    $stmt->bind_param("s", $reg_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    return $student;
}

// Handle student search
$student_data = null;
$search_message = '';
if (isset($_POST['search_student'])) {
    $reg_number = $_POST['registration_number'];
    $student_data = getStudentDetails($reg_number);
    if (!$student_data) {
        $search_message = '<div class="alert alert-danger">Student not found!</div>';
    }
}

// Handle payment verification callback
if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "cache-control: no-cache"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "Payment verification failed";
    } else {
        $result = json_decode($response);
        
        if ($result->status && $result->data->status === 'success') {
            $sql = "UPDATE school_payments SET payment_status = 'completed' WHERE reference_code = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            
            echo "<script>window.location.href = 'payment_success.php?reference=" . $reference . "';</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Payment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
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

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            color: var(--primary-color);
            position: relative;
        }

        .page-header h2 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 0.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            border-color: var(--secondary-color);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border: none;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--primary-color);
            border: none;
        }

        .btn-secondary:hover {
            background-color: #234567;
            transform: translateY(-2px);
        }

        .student-info {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            border-left: 4px solid var(--secondary-color);
        }

        .student-info h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .student-info p {
            margin-bottom: 0.5rem;
            color: #555;
            display: flex;
            align-items: center;
        }

        .student-info i {
            margin-right: 10px;
            color: var(--secondary-color);
            width: 20px;
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .payment-options {
            margin-top: 1rem;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        @media (max-width: 768px) {
            .container {
                padding-top: 1rem;
            }

            .page-header h2 {
                font-size: 2rem;
            }

            .card-body {
                padding: 1.25rem;
            }
        }

        /* Loading animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--secondary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>School Payment Portal</h2>
            <p>Simple, secure, and convenient way to make school payments</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <!-- Student Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-search"></i> Search Student
                        </h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="registration_number" class="form-label">
                                    <i class="fas fa-id-card"></i> Registration Number
                                </label>
                                <input type="text" class="form-control" id="registration_number" 
                                       name="registration_number" required 
                                       placeholder="Enter your registration number">
                            </div>
                            <button type="submit" name="search_student" class="btn btn-secondary w-100">
                                <i class="fas fa-search"></i> Search Student
                            </button>
                        </form>
                    </div>
                </div>

                <?php echo $search_message; ?>

                <?php if ($student_data): ?>
                <!-- Student Information -->
                <div class="student-info">
                    <h5><i class="fas fa-user-graduate"></i> Student Information</h5>
                    <p><i class="fas fa-user"></i> <strong>Name:</strong> 
                        <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?>
                    </p>
                    <p><i class="fas fa-chalkboard"></i> <strong>Class:</strong> 
                        <?php echo htmlspecialchars($student_data['class']); ?>
                    </p>
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> 
                        <?php echo htmlspecialchars($student_data['email']); ?>
                    </p>
                </div>

                <!-- Payment Form -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-credit-card"></i> Make Payment
                        </h5>
                        <form id="paymentForm">
                            <input type="hidden" id="student_id" 
                                   value="<?php echo htmlspecialchars($student_data['registration_number']); ?>">
                            <input type="hidden" id="email" 
                                   value="<?php echo htmlspecialchars($student_data['email']); ?>">
                            
                            <div class="mb-3">
                                <label for="payment_type" class="form-label">
                                    <i class="fas fa-list"></i> Payment Type
                                </label>
                                <select class="form-control" id="payment_type" required>
                                    <option value="">Select Payment Type</option>
                                    <?php foreach($available_payments as $payment): ?>
                                        <option value="<?php echo $payment['id']; ?>" 
                                                data-amount="<?php echo $payment['amount']; ?>"
                                                data-min-amount="<?php echo $payment['min_payment_amount']; ?>">
                                            <?php echo $payment['name']; ?> 
                                            (Min: ₦<?php echo number_format($payment['min_payment_amount'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3" id="custom_amount_div">
                                <label for="custom_amount" class="form-label">
                                    <i class="fas fa-money-bill"></i> Enter Amount to Pay
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="custom_amount" 
                                           placeholder="Enter amount you want to pay" min="0" step="100" required
                                           oninput="validatePaymentAmount()">
                                </div>
                                <small class="text-danger" id="min_amount_notice"></small>
                                <div class="form-text" id="payment_info"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100" id="paymentButton" onclick="payWithPaystack(event)">
                                <i class="fas fa-lock"></i> Proceed to Secure Payment
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Confirmation Modal -->
    <div class="modal fade" id="paymentConfirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">
                        <i class="fas fa-receipt"></i> Confirm Payment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="payment-details">
                        <div class="student-details mb-3">
                            <h6 class="text-primary"><i class="fas fa-user-graduate"></i> Student Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <span id="confirm-student-name"></span></p>
                            <p class="mb-1"><strong>Class:</strong> <span id="confirm-student-class"></span></p>
                            <p class="mb-0"><strong>Email:</strong> <span id="confirm-student-email"></span></p>
                        </div>
                        <div class="payment-info mb-3">
                            <h6 class="text-primary"><i class="fas fa-file-invoice"></i> Payment Information</h6>
                            <p class="mb-1"><strong>Payment Type:</strong> <span id="confirm-payment-type"></span></p>
                            <p class="mb-1"><strong>Base Amount:</strong> <span id="confirm-base-amount"></span></p>
                            <p class="mb-1"><strong>Service Charge :</strong> <span id="confirm-service-charge"></span></p>
                            <p class="mb-0"><strong>Total Amount:</strong> <span id="confirm-total-amount" class="text-primary fw-bold"></span></p>
                        </div>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle"></i> Please verify all details before proceeding with the payment.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmPaymentBtn">
                        <i class="fas fa-check"></i> Confirm & Pay
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Format number to Nigerian Naira
        function formatNaira(amount) {
            return '₦' + amount.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Calculate payment charge
        function calculateCharge(amount) {
            const percentageCharge = amount * 0.015; // 1.5%
            const flatCharge = 100; // ₦100 flat fee
            return percentageCharge + flatCharge;
        }

        // Validate payment amount
        function validatePaymentAmount() {
            let payment_select = document.getElementById('payment_type');
            let selectedOption = payment_select.options[payment_select.selectedIndex];
            let customAmountInput = document.getElementById('custom_amount');
            let minAmountNotice = document.getElementById('min_amount_notice');
            let paymentButton = document.getElementById('paymentButton');
            let paymentInfo = document.getElementById('payment_info');
            
            if (!selectedOption.value || !customAmountInput.value) {
                paymentButton.disabled = true;
                paymentInfo.textContent = '';
                return false;
            }

            let minAmount = parseFloat(selectedOption.dataset.minAmount);
            let defaultAmount = parseFloat(selectedOption.dataset.amount);
            let enteredAmount = parseFloat(customAmountInput.value);

            if (isNaN(enteredAmount) || enteredAmount <= 0) {
                minAmountNotice.textContent = 'Please enter a valid amount';
                paymentButton.disabled = true;
                paymentInfo.textContent = '';
                return false;
            }

            if (enteredAmount < minAmount) {
                minAmountNotice.textContent = `Minimum payment required: ${formatNaira(minAmount)}`;
                paymentButton.disabled = true;
                paymentInfo.textContent = '';
                return false;
            }

            if (enteredAmount > defaultAmount) {
                minAmountNotice.textContent = `Maximum payment allowed: ${formatNaira(defaultAmount)}`;
                paymentButton.disabled = true;
                paymentInfo.textContent = '';
                return false;
            }

            const charge = calculateCharge(enteredAmount);
            const totalAmount = enteredAmount + charge;

            minAmountNotice.textContent = '';
            paymentInfo.innerHTML = `
                Payment Amount: ${formatNaira(enteredAmount)}<br>
                Service Charge : ${formatNaira(charge)}<br>
                <strong>Total Amount: ${formatNaira(totalAmount)}</strong>
            `;
            paymentButton.disabled = false;
            return true;
        }

        function showPaymentConfirmation() {
            // Get student details
            document.getElementById('confirm-student-name').textContent = 
                '<?php echo isset($student_data) ? htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']) : ''; ?>';
            document.getElementById('confirm-student-class').textContent = 
                '<?php echo isset($student_data) ? htmlspecialchars($student_data['class']) : ''; ?>';
            document.getElementById('confirm-student-email').textContent = 
                '<?php echo isset($student_data) ? htmlspecialchars($student_data['email']) : ''; ?>';

            // Get payment details
            let payment_select = document.getElementById('payment_type');
            let selectedOption = payment_select.options[payment_select.selectedIndex];
            let customAmountInput = document.getElementById('custom_amount');
            let amount = parseFloat(customAmountInput.value);
            let charge = calculateCharge(amount);
            let totalAmount = amount + charge;

            document.getElementById('confirm-payment-type').textContent = selectedOption.text;
            document.getElementById('confirm-base-amount').textContent = formatNaira(amount);
            document.getElementById('confirm-service-charge').textContent = formatNaira(charge);
            document.getElementById('confirm-total-amount').textContent = formatNaira(totalAmount);

            // Show the modal
            let confirmationModal = new bootstrap.Modal(document.getElementById('paymentConfirmationModal'));
            confirmationModal.show();
        }

        function payWithPaystack(e) {
            e.preventDefault();
            
            let payment_select = document.getElementById('payment_type');
            let customAmountInput = document.getElementById('custom_amount');

            if (!payment_select.value) {
                alert('Please select a payment type');
                return;
            }

            if (!customAmountInput.value) {
                alert('Please enter the amount you want to pay');
                return;
            }

            if (!validatePaymentAmount()) {
                return;
            }

            // Show confirmation modal instead of proceeding directly
            showPaymentConfirmation();
        }

        // Handle confirmation button click
        document.getElementById('confirmPaymentBtn').addEventListener('click', function() {
            // Hide the confirmation modal
            let confirmationModal = bootstrap.Modal.getInstance(document.getElementById('paymentConfirmationModal'));
            confirmationModal.hide();

            showLoading();
            
            let student_id = document.getElementById('student_id').value;
            let email = document.getElementById('email').value;
            let payment_type_id = document.getElementById('payment_type').value;
            let amount = parseFloat(document.getElementById('custom_amount').value);
            let charge = calculateCharge(amount);
            let totalAmount = amount + charge;

            let handler = PaystackPop.setup({
                key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>', 
                email: email,
                amount: totalAmount * 100, // Convert to kobo
                currency: 'NGN',
                ref: 'SCH'+Math.floor((Math.random() * 1000000000) + 1),
                metadata: {
                    student_id: student_id,
                    payment_type_id: payment_type_id,
                    base_amount: amount,
                    service_charge: charge
                },
                callback: function(response) {
                    let xhr = new XMLHttpRequest();
                    xhr.open('POST', 'ctrl/save_payment.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        try {
                            let result = JSON.parse(xhr.responseText);
                            if(xhr.status === 200 && result.status === 'success') {
                                window.location.href = 'payment_success.php?reference=' + response.reference;
                            } else {
                                alert('Error: ' + (result.message || 'Failed to save payment details'));
                            }
                        } catch(e) {
                            alert('Error processing payment response');
                        }
                        hideLoading();
                    };
                    xhr.onerror = function() {
                        alert('Network error occurred while saving payment');
                        hideLoading();
                    };
                    xhr.send('reference=' + response.reference + 
                           '&student_id=' + student_id + 
                           '&payment_type_id=' + payment_type_id + 
                           '&base_amount=' + amount +
                           '&service_charge=' + charge);
                },
                onClose: function() {
                    hideLoading();
                    alert('Transaction cancelled');
                }
            });
            handler.openIframe();
        });

        // Add event listeners
        document.getElementById('payment_type').addEventListener('change', function() {
            let selectedOption = this.options[this.selectedIndex];
            let customAmountInput = document.getElementById('custom_amount');
            let paymentInfo = document.getElementById('payment_info');
            
            if (selectedOption.value) {
                let minAmount = parseFloat(selectedOption.dataset.minAmount) || 0;
                let defaultAmount = parseFloat(selectedOption.dataset.amount);
                
                customAmountInput.min = minAmount;
                if (!customAmountInput.value) {
                    customAmountInput.value = defaultAmount;
                }
                paymentInfo.textContent = `Suggested amount: ${formatNaira(defaultAmount)}`;
                validatePaymentAmount();
            } else {
                customAmountInput.value = '';
                paymentInfo.textContent = '';
                validatePaymentAmount();
            }
        });

        // Add event listener for custom amount input
        document.getElementById('custom_amount').addEventListener('input', validatePaymentAmount);
    </script>
</body>
</html>
