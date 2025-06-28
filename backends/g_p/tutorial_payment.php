<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACE Tutorial Payment - ACE Model College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .payment-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(45deg, #fc466b, #3f5efb);
            color: white;
            border: none;
            padding: 2rem;
            text-align: center;
        }
        .account-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            border-left: 5px solid #fc466b;
        }
        .account-info {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-verify {
            background: linear-gradient(45deg, #fc466b, #3f5efb);
            border: none;
            border-radius: 50px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-verify:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(252, 70, 107, 0.4);
            color: white;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        .step-number {
            background: #fc466b;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-lg-8 col-md-10">
                <div class="payment-card">
                    <div class="card-header">
                        <h1 class="mb-0">
                            <i class="fas fa-chalkboard-teacher me-3"></i>
                            ACE Tutorial Payment
                        </h1>
                        <p class="mb-0">Tutorial fees and extra-curricular learning payments</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="warning-box">
                            <h5><i class="fas fa-exclamation-triangle text-warning me-2"></i>Important Notice</h5>
                            <p class="mb-0">Please make your payment to the account details below and return to this page for verification. Your payment will not be processed until you complete the verification process.</p>
                        </div>

                        <div class="account-details">
                            <h4 class="text-center mb-4">
                                <i class="fas fa-university me-2"></i>
                                Account Details
                            </h4>
                            
                            <div class="account-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-credit-card me-2"></i>Account Number:</strong>
                                        <p class="h5 text-primary"><?php echo TUTORIAL_ACCOUNT_NUMBER; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-user me-2"></i>Account Name:</strong>
                                        <p class="h5 text-primary"><?php echo TUTORIAL_ACCOUNT_NAME; ?></p>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-building me-2"></i>Bank:</strong>
                                        <p class="h5 text-primary"><?php echo TUTORIAL_BANK; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-calendar me-2"></i>Payment Date:</strong>
                                        <p class="h5 text-primary"><?php echo date('d/m/Y'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="payment-steps">
                            <h5 class="mb-3"><i class="fas fa-list-ol me-2"></i>Payment Steps:</h5>
                            <div class="step mb-3">
                                <span class="step-number">1</span>
                                <span>Make payment to the account details above using your bank app or visit any bank branch</span>
                            </div>
                            <div class="step mb-3">
                                <span class="step-number">2</span>
                                <span>Take a screenshot or photo of your payment receipt</span>
                            </div>
                            <div class="step mb-3">
                                <span class="step-number">3</span>
                                <span>Click the "Verify Payment" button below to upload your receipt</span>
                            </div>
                            <div class="step mb-3">
                                <span class="step-number">4</span>
                                <span>Fill in the verification form with your details</span>
                            </div>
                            <div class="step mb-3">
                                <span class="step-number">5</span>
                                <span>Submit and wait for admin verification</span>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <a href="verify_payment.php?type=tutorial" class="btn btn-verify btn-lg">
                                <i class="fas fa-check-circle me-2"></i>
                                Verify Payment
                            </a>
                        </div>

                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to Payment Selection
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 