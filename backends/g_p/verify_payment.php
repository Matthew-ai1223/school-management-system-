<?php
require_once 'config.php';

$payment_type = isset($_GET['type']) ? $_GET['type'] : '';
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $depositor_name = sanitizeInput($_POST['depositor_name']);
    $student_name = sanitizeInput($_POST['student_name']);
    $student_class = sanitizeInput($_POST['student_class'] ?? '');
    $registration_number = sanitizeInput($_POST['registration_number'] ?? '');
    $payment_category = sanitizeInput($_POST['payment_category']);
    $amount = sanitizeInput($_POST['amount']);
    $payment_date = sanitizeInput($_POST['payment_date']);
    $payment_type = sanitizeInput($_POST['payment_type']);
    
    // Validate required fields
    if (empty($depositor_name) || empty($student_name) || empty($amount) || empty($payment_date) || empty($payment_category)) {
        $error_message = "All fields are required.";
    } elseif ($payment_type === 'school' && (empty($student_class) || empty($registration_number))) {
        $error_message = "Student class and registration number are required for school payments.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error_message = "Please enter a valid amount.";
    } else {
        // Handle file upload
        if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
            $file_validation = validateImageFile($_FILES['receipt_image']);
            
            if ($file_validation === true) {
                createUploadDirectory();
                $filename = generateUniqueFilename($_FILES['receipt_image']['name']);
                $upload_path = UPLOAD_DIR . $filename;
                
                if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $upload_path)) {
                    // Save to database
                    try {
                        $pdo = getDBConnection();
                        
                        // Get account details based on payment type
                        if ($payment_type === 'school') {
                            $account_number = SCHOOL_ACCOUNT_NUMBER;
                            $account_name = SCHOOL_ACCOUNT_NAME;
                            $bank_name = SCHOOL_BANK;
                        } else {
                            $account_number = TUTORIAL_ACCOUNT_NUMBER;
                            $account_name = TUTORIAL_ACCOUNT_NAME;
                            $bank_name = TUTORIAL_BANK;
                        }
                        
                        $sql = "INSERT INTO payments (payment_type, payment_category, depositor_name, student_name, student_class, registration_number, amount, account_number, account_name, bank_name, receipt_image, payment_date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$payment_type, $payment_category, $depositor_name, $student_name, $student_class, $registration_number, $amount, $account_number, $account_name, $bank_name, $filename, $payment_date]);
                        
                        $payment_id = $pdo->lastInsertId();
                        
                        // Add to payment history
                        $sql = "INSERT INTO payment_history (payment_id, status, notes) VALUES (?, 'pending', 'Payment submitted for verification')";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$payment_id]);
                        
                        $success_message = "Payment verification submitted successfully! Your payment is now pending admin verification. You will be notified once it's processed.";
                        
                    } catch (PDOException $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                } else {
                    $error_message = "Failed to upload file. Please try again.";
                }
            } else {
                $error_message = $file_validation;
            }
        } else {
            $error_message = "Please select a receipt image to upload.";
        }
    }
}

// Validate payment type
if (!in_array($payment_type, ['school', 'tutorial'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payment - ACE Model College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .verification-card {
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
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-submit {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .file-upload {
            border: 2px dashed #667eea;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .file-upload:hover {
            border-color: #764ba2;
            background: #e9ecef;
        }
        .alert {
            border-radius: 15px;
            border: none;
        }
        .school-fields {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
            border-left: 5px solid #11998e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-lg-8 col-md-10">
                <div class="verification-card">
                    <div class="card-header">
                        <h1 class="mb-0">
                            <i class="fas fa-check-circle me-3"></i>
                            Verify Payment
                        </h1>
                        <p class="mb-0">
                            <?php echo $payment_type === 'school' ? 'School Payment' : 'ACE Tutorial Payment'; ?> - Upload Receipt
                        </p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success_message; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="payment_history.php" class="btn btn-primary">
                                    <i class="fas fa-history me-2"></i>
                                    View Payment History
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-home me-2"></i>
                                    Back to Home
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="payment_type" value="<?php echo $payment_type; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="depositor_name" class="form-label">
                                                <i class="fas fa-user me-2"></i>Depositor Name *
                                            </label>
                                            <input type="text" class="form-control" id="depositor_name" name="depositor_name" 
                                                   value="<?php echo isset($_POST['depositor_name']) ? htmlspecialchars($_POST['depositor_name']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="student_name" class="form-label">
                                                <i class="fas fa-graduation-cap me-2"></i>Student Name *
                                            </label>
                                            <input type="text" class="form-control" id="student_name" name="student_name" 
                                                   value="<?php echo isset($_POST['student_name']) ? htmlspecialchars($_POST['student_name']) : ''; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="payment_category" class="form-label">
                                                <i class="fas fa-tags me-2"></i>Payment Category *
                                            </label>
                                            <select class="form-select" id="payment_category" name="payment_category" required>
                                                <option value="">Select Payment Category</option>
                                                <?php if ($payment_type === 'school'): ?>
                                                    <option value="Tuition Fee" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Tuition Fee') ? 'selected' : ''; ?>>Tuition Fee</option>
                                                    <option value="Book Library" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Book Library') ? 'selected' : ''; ?>>Book Library</option>
                                                    <option value="Uniform" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Uniform') ? 'selected' : ''; ?>>Uniform</option>
                                                    <option value="Cardigan" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Cardigan') ? 'selected' : ''; ?>>Cardigan</option>
                                                    <option value="Sport Wares" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Sport Wares') ? 'selected' : ''; ?>>Sport Wares</option>
                                                    <option value="Examination Fee" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Examination Fee') ? 'selected' : ''; ?>>Examination Fee</option>
                                                    <option value="Development Levy" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Development Levy') ? 'selected' : ''; ?>>Development Levy</option>
                                                    <option value="Other" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                                <?php else: ?>
                                                    <option value="Morning Class" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Morning Class') ? 'selected' : ''; ?>>Morning Class (₦10,000)</option>
                                                    <option value="Evening Class" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Evening Class') ? 'selected' : ''; ?>>Evening Class (₦3,000)</option>
                                                    <option value="Other" <?php echo (isset($_POST['payment_category']) && $_POST['payment_category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">
                                                <i class="fas fa-money-bill me-2"></i>Amount Paid (₦) *
                                            </label>
                                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" 
                                                   value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($payment_type === 'school'): ?>
                                    <div class="school-fields">
                                        <h6 class="mb-3">
                                            <i class="fas fa-school me-2 text-success"></i>
                                            School Payment Details (Required)
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="student_class" class="form-label">
                                                        <i class="fas fa-users me-2"></i>Student Class *
                                                    </label>
                                                    <input type="text" class="form-control" id="student_class" name="student_class" 
                                                           value="<?php echo isset($_POST['student_class']) ? htmlspecialchars($_POST['student_class']) : ''; ?>" 
                                                           placeholder="e.g., JSS 1, SSS 2, etc." required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="registration_number" class="form-label">
                                                        <i class="fas fa-id-card me-2"></i>Registration Number *
                                                    </label>
                                                    <input type="text" class="form-control" id="registration_number" name="registration_number" 
                                                           value="<?php echo isset($_POST['registration_number']) ? htmlspecialchars($_POST['registration_number']) : ''; ?>" 
                                                           placeholder="e.g., 2425/COL/0011 or 2425/KID/0011" required>
                                                    <small class="text-muted">Format: YYYY/COL/XXXX or YYYY/KID/XXXX</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="payment_date" class="form-label">
                                                <i class="fas fa-calendar me-2"></i>Payment Date *
                                            </label>
                                            <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                                   value="<?php echo isset($_POST['payment_date']) ? htmlspecialchars($_POST['payment_date']) : date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-image me-2"></i>Payment Receipt *
                                    </label>
                                    <div class="file-upload">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                        <h5>Upload Receipt Image</h5>
                                        <p class="text-muted">Please upload a clear image of your payment receipt</p>
                                        <input type="file" class="form-control" name="receipt_image" accept="image/*" required>
                                        <small class="text-muted">Supported formats: JPG, PNG, GIF (Max: 5MB)</small>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-submit btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        Submit for Verification
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <a href="<?php echo $payment_type === 'school' ? 'school_payment.php' : 'tutorial_payment.php'; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill amount for tutorial payments
        document.addEventListener('DOMContentLoaded', function() {
            const paymentCategory = document.getElementById('payment_category');
            const amountField = document.getElementById('amount');
            
            paymentCategory.addEventListener('change', function() {
                const selectedCategory = this.value;
                const paymentType = '<?php echo $payment_type; ?>';
                
                if (paymentType === 'tutorial') {
                    if (selectedCategory === 'Morning Class') {
                        amountField.value = '10000';
                    } else if (selectedCategory === 'Evening Class') {
                        amountField.value = '3000';
                    } else if (selectedCategory === 'Other') {
                        amountField.value = '';
                    }
                }
            });
        });
    </script>
</body>
</html> 