<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Reactivation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .container {
            max-width: 800px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
        }

        .form-section {
            background-color: var(--light-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .form-section-title {
            color: var(--secondary-color);
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #357abd;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* Loading Overlay Styles */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loader {
            width: 80px;
            height: 80px;
            border: 5px solid var(--light-bg);
            border-radius: 50%;
            border-top: 5px solid var(--primary-color);
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        .loading-text {
            color: var(--secondary-color);
            font-size: 1.2rem;
            font-weight: 500;
            text-align: center;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Processing Button State */
        .btn-processing {
            position: relative;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .btn-processing .spinner-border {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <!-- Add Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
        <div class="loading-text">Processing Reactivation...</div>
        <div class="loading-subtext" style="color: #666; margin-top: 10px; font-size: 0.9rem;">Please do not close this window</div>
    </div>

    <div class="container">
        <h2 class="text-center mb-4">Account Reactivation</h2>
        
        <?php
        session_start();
        include '../confg.php';

        if (!isset($_GET['email']) || !isset($_GET['table'])) {
            echo '<div class="alert alert-danger">Invalid reactivation link.</div>';
            exit;
        }

        $email = $_GET['email'];
        $table = $_GET['table'];

        // Verify if the account exists and is deactivated
        $stmt = $conn->prepare("SELECT * FROM $table WHERE email = ? AND is_active = FALSE");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            echo '<div class="alert alert-danger">Account not found or already active.</div>';
            exit;
        }
        ?>

        <div class="form-section">
            <h3 class="form-section-title">Account Information</h3>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name</label>
                    <p class="form-control-static"><?php echo htmlspecialchars($user['fullname']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <p class="form-control-static"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Department</label>
                    <p class="form-control-static"><?php echo ucfirst(htmlspecialchars($user['department'])); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Class Type</label>
                    <p class="form-control-static"><?php echo $table === 'morning_students' ? 'Morning' : 'Afternoon'; ?> Class</p>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">Payment Information</h3>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Payment Type</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_type" id="full_payment" value="full" checked>
                        <label class="form-check-label" for="full_payment">
                            Full Payment (₦<?php echo $table === 'morning_students' ? '10,000' : '4,000'; ?>)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_type" id="half_payment" value="half">
                        <label class="form-check-label" for="half_payment">
                            Half Payment (₦<?php echo $table === 'morning_students' ? '5,200' : '2,200'; ?>)
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-primary w-100" onclick="payWithPaystack()" id="reactivateBtn">Reactivate Account</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function getPaymentAmount() {
            const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
            const isMorning = <?php echo $table === 'morning_students' ? 'true' : 'false'; ?>;
            return paymentType === 'full' ? (isMorning ? 10000 : 4000) : (isMorning ? 5200 : 2200);
        }

        function generateReference() {
            const prefix = '<?php echo $table === 'morning_students' ? 'MORNING' : 'AFTERNOON'; ?>';
            const timestamp = new Date().getTime();
            const random = Math.floor(Math.random() * 1000000);
            return `${prefix}_${timestamp}_${random}`;
        }

        function payWithPaystack() {
            const amount = getPaymentAmount();
            const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
            const submitBtn = document.getElementById('reactivateBtn');

            // Update button state
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-processing');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Initializing Payment...';

            try {
                const handler = PaystackPop.setup({
                    key: 'pk_test_fff1d31f74a43da37f1322e466e0e27d1c1900f7',
                    email: '<?php echo htmlspecialchars($user['email']); ?>',
                    amount: amount * 100, // Convert to kobo
                    currency: 'NGN',
                    ref: generateReference(),
                    metadata: {
                        custom_fields: [
                            {
                                display_name: "Payment Type",
                                variable_name: "payment_type",
                                value: paymentType
                            },
                            {
                                display_name: "Student Type",
                                variable_name: "student_type",
                                value: '<?php echo $table === 'morning_students' ? 'Morning' : 'Afternoon'; ?>'
                            }
                        ]
                    },
                    callback: function(response) {
                        // Show loading overlay
                        showLoading();

                        const formData = new FormData();
                        formData.append('reference', response.reference);
                        formData.append('table', '<?php echo $table; ?>');
                        formData.append('email', '<?php echo htmlspecialchars($user['email']); ?>');
                        formData.append('payment_type', paymentType);
                        formData.append('amount', amount);

                        // Send form data to server
                        fetch('process_reactivation.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.text().then(text => {
                                    throw new Error('Server response: ' + text);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.status === 'success') {
                                // Download receipt if available
                                if (data.data && data.data.receipt_url) {
                                    window.open(data.data.receipt_url, '_blank');
                                }
                                alert('Account reactivated successfully!');
                                window.location.href = 'login.php';
                            } else {
                                throw new Error(data.message || 'Reactivation failed');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred during reactivation: ' + error.message);
                        })
                        .finally(() => {
                            // Hide loading overlay
                            hideLoading();
                            // Reset button state
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('btn-processing');
                            submitBtn.innerHTML = 'Reactivate Account';
                        });
                    },
                    onClose: function() {
                        // Reset button state if payment modal is closed
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-processing');
                        submitBtn.innerHTML = 'Reactivate Account';
                        alert('Transaction cancelled');
                    }
                });
                handler.openIframe();
            } catch (error) {
                console.error('Payment initialization error:', error);
                alert('Failed to initialize payment. Please try again.');
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-processing');
                submitBtn.innerHTML = 'Reactivate Account';
            }
        }
    </script>
</body>
</html> 