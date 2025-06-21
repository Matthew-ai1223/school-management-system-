<?php
require_once '../../config.php';
require_once '../../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get registration type from URL parameter (default to kiddies if not specified)
$registrationType = isset($_GET['type']) ? $_GET['type'] : 'kiddies';
if (!in_array($registrationType, ['kiddies', 'college'])) {
    $registrationType = 'kiddies';
}

// Define field categories with their display order
$fieldCategories = [
    'student_info' => 'Student Information',
    'parent_info' => 'Parent/Guardian Information',
    'guardian_info' => 'Guardian Info (Optional)',
    'medical_info' => 'Medical Background (Optional)'
];

// Base URL for assets
$base_url = "http://" . $_SERVER['HTTP_HOST'];

// Check if payment is verified and session is still valid (2 hour timeout)
$paymentVerified = false;
if (isset($_SESSION['verified_payment_reference']) && isset($_SESSION['payment_verified_time'])) {
    $sessionTimeout = 7200; // 2 hours in seconds
    if ((time() - $_SESSION['payment_verified_time']) < $sessionTimeout) {
        $paymentVerified = true;
    } else {
        // Clear expired verification session
        unset($_SESSION['verified_payment_reference']);
        unset($_SESSION['verified_payment_email']);
        unset($_SESSION['verified_payment_phone']);
        unset($_SESSION['payment_verified_time']);
        $_SESSION['error_message'] = 'Your payment verification has expired. Please verify again.';
    }
}

// Fetch form fields for the current registration type
$sql = "SELECT * FROM registration_form_fields WHERE is_active = 1 AND registration_type = ? ORDER BY field_category, field_order";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $registrationType);
$stmt->execute();
$result = $stmt->get_result();
$fields = $result->fetch_all(MYSQLI_ASSOC);

// Group fields by category
$categorizedFields = [];
foreach ($fieldCategories as $categoryKey => $categoryName) {
    $categorizedFields[$categoryKey] = [];
}

// Populate fields into categories
foreach ($fields as $field) {
    $category = $field['field_category'] ?? 'student_info'; // Default to student info if not specified
    $categorizedFields[$category][] = $field;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color:rgb(26, 43, 119);
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .form-control, .form-select {
            border-radius: 7px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 7px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 7px;
            padding: 10px 25px;
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
            font-weight: bold;
        }
        
        #paymentVerificationForm {
            display: <?php echo $paymentVerified ? 'none' : 'block'; ?>;
        }
        
        #registrationForm {
            display: <?php echo $paymentVerified ? 'block' : 'none'; ?>;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .file-preview {
            text-align: center;
            margin-top: 1rem;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .alert {
            border-radius: 7px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: rgba(76, 201, 240, 0.15);
            border-color: var(--success-color);
            color: #0a58ca;
        }
        
        .progress-container {
            margin-bottom: 2rem;
        }
        
        .progress-step {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 1rem;
        }
        
        .step {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            z-index: 2;
        }
        
        .step.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .step.completed {
            background-color: var(--success-color);
            color: white;
        }
        
        .step-connector {
            position: absolute;
            top: 17px;
            left: 35px;
            right: 35px;
            height: 2px;
            background-color: #e9ecef;
            z-index: 1;
        }
        
        .step-connector.active {
            background-color: var(--success-color);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-header h3 {
                font-size: 1.5rem;
            }
            
            .btn-group {
                width: 100%;
            }
            
            .btn-group .btn {
                width: 50%;
            }
        }
        
        .registration-type-selector {
            margin-bottom: 15px;
        }
        
        .btn-group-lg > .btn {
            padding: 15px 25px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-group-lg > .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
        }
        
        .btn-group-lg > .btn.btn-primary {
            background-image: linear-gradient(135deg, #4e54c8, #3f51b5);
            border: none;
            color: white;
        }
        
        .btn-group-lg > .btn.btn-outline-primary {
            border-width: 2px;
            font-weight: 600;
            color: #4e54c8;
            border-color: #4e54c8;
        }
        
        .btn-group-lg > .btn.btn-outline-primary:hover {
            background-color: #4e54c8;
            color: white;
        }
        
        .registration-confirmation {
            background-color: #e8f0fe;
            color: #3f51b5;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 1rem;
            margin-top: 15px;
            display: inline-block;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #4e54c8;
        }
        
        .registration-confirmation i {
            margin-right: 8px;
            color: #4e54c8;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center mb-3">Student Registration</h3>
                        <div class="text-center">
                            <div class="registration-type-selector">
                                <div class="guidance-text mb-3">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Please select your school type:</strong> 
                                        Choose "Ace Kiddies" for primary school students or "Ace College" for secondary school students.
                                    </div>
                                </div>
                                <div class="btn-group btn-group-lg">
                                    <a href="?type=kiddies" class="btn btn-<?php echo $registrationType === 'kiddies' ? 'primary' : 'outline-primary'; ?> fw-bold">
                                        <i class="fas fa-child me-2"></i>Ace Kiddies
                                    </a>
                                    <a href="?type=college" class="btn btn-<?php echo $registrationType === 'college' ? 'primary' : 'outline-primary'; ?> fw-bold">
                                        <i class="fas fa-graduation-cap me-2"></i>Ace College
                                    </a>
                                </div>
                                <div class="mt-3">
                                    <div class="registration-confirmation">
                                        <?php if($registrationType === 'kiddies'): ?>
                                            <i class="fas fa-info-circle"></i> You are registering for <strong>Ace Kiddies</strong> (Primary School)
                                        <?php else: ?>
                                            <i class="fas fa-info-circle"></i> You are registering for <strong>Ace College</strong> (Secondary School)
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-lg-4">
                        <!-- Registration Progress -->
                        <div class="progress-container">
                            <div class="progress-step">
                                <div class="step <?php echo !$paymentVerified ? 'active' : 'completed'; ?>">
                                    <i class="<?php echo $paymentVerified ? 'fas fa-check' : '1'; ?>"></i>
                                </div>
                                <div class="step-connector <?php echo $paymentVerified ? 'active' : ''; ?>"></div>
                                <div class="step <?php echo $paymentVerified ? 'active' : ''; ?>">
                                    2
                                </div>
                            </div>
                            <div class="d-flex justify-content-between text-center">
                                <div class="step-label"><small class="text-muted">Payment Verification</small></div>
                                <div class="step-label"><small class="text-muted">Registration Form</small></div>
                            </div>
                        </div>

                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php 
                                echo $_SESSION['error_message'];
                                unset($_SESSION['error_message']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Payment Verification Form -->
                        <div id="paymentVerificationForm" class="form-section">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Please enter your payment reference to proceed with registration. <br>
                                <strong>Note:</strong> Use your card number as the payment reference or the payment reference number on your application receipt.
                            </div>
                            <form id="verifyPaymentForm" onsubmit="return verifyPayment(event)">
                                <div class="mb-3">
                                    <label class="form-label required-field">Payment Reference</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-receipt"></i></span>
                                        <input type="text" name="reference" class="form-control" required placeholder="Enter your payment reference number">
                                    </div>
                                </div>
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check-circle me-2"></i>Verify Payment
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Registration Form -->
                        <div id="registrationForm">
                            <?php if ($paymentVerified): ?>
                                <div class="alert alert-success mb-4">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Payment verified successfully! Reference: <strong><?php echo htmlspecialchars($_SESSION['verified_payment_reference']); ?></strong>
                                </div>
                            <?php endif; ?>

                            <form action="save_registration.php" method="POST" enctype="multipart/form-data" id="studentRegistrationForm" onsubmit="return validateForm(this, event)">
                                <input type="hidden" name="registration_type" value="<?php echo $registrationType; ?>">
                                <input type="hidden" name="payment_reference" value="<?php echo $paymentVerified ? $_SESSION['verified_payment_reference'] : ''; ?>">
                                <!-- Add MAX_FILE_SIZE hidden field - must precede file input field -->
                                <input type="hidden" name="MAX_FILE_SIZE" value="2097152"> <!-- 2MB in bytes -->
                                
                                <?php if (empty($fields)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No form fields have been configured for this registration type.
                                    </div>
                                <?php else: ?>
                                    <?php 
                                    // Display fields by category
                                    foreach ($fieldCategories as $categoryKey => $categoryName): 
                                        if (empty($categorizedFields[$categoryKey])) continue;
                                    ?>
                                        <div class="form-section mt-4">
                                            <h5 class="mb-3"><?php echo $categoryName; ?></h5>
                                            <?php foreach ($categorizedFields[$categoryKey] as $field): ?>
                                                <div class="mb-3">
                                                    <label class="form-label <?php echo $field['required'] ? 'required-field' : ''; ?>">
                                                        <?php echo htmlspecialchars($field['field_label']); ?>
                                                    </label>
                                                    
                                                    <?php switch($field['field_type']): 
                                                        case 'text': 
                                                        case 'email':
                                                        case 'number':
                                                        case 'date': ?>
                                                            <div class="input-group">
                                                                <span class="input-group-text">
                                                                    <i class="fas fa-<?php 
                                                                        // Choose icon based on field_label
                                                                        $icon = 'file-alt'; // default
                                                                        $label = strtolower($field['field_label']);
                                                                        if (stripos($label, 'name') !== false) $icon = 'user';
                                                                        elseif (stripos($label, 'email') !== false) $icon = 'envelope';
                                                                        elseif (stripos($label, 'phone') !== false) $icon = 'phone';
                                                                        elseif (stripos($label, 'address') !== false) $icon = 'map-marker-alt';
                                                                        elseif (stripos($label, 'date') !== false || stripos($label, 'birth') !== false) $icon = 'calendar';
                                                                        elseif (stripos($label, 'gender') !== false) $icon = 'venus-mars';
                                                                        elseif (stripos($label, 'nationality') !== false || stripos($label, 'state') !== false) $icon = 'flag';
                                                                        elseif (stripos($label, 'blood') !== false || stripos($label, 'genotype') !== false) $icon = 'tint';
                                                                        elseif (stripos($label, 'occupation') !== false) $icon = 'briefcase';
                                                                        echo $icon;
                                                                    ?>"></i>
                                                                </span>
                                                                <input type="<?php echo $field['field_type']; ?>" 
                                                                       name="field_<?php echo $field['id']; ?>" 
                                                                       class="form-control"
                                                                       placeholder="Enter <?php echo strtolower($field['field_label']); ?>"
                                                                       <?php 
                                                                       // Auto-fill email and phone if they match field labels
                                                                       if (strtolower($field['field_label']) === 'email' && isset($_SESSION['verified_payment_email'])) {
                                                                           echo 'value="' . htmlspecialchars($_SESSION['verified_payment_email']) . '"';
                                                                       } elseif (strtolower($field['field_label']) === 'phone' && isset($_SESSION['verified_payment_phone'])) {
                                                                           echo 'value="' . htmlspecialchars($_SESSION['verified_payment_phone']) . '"';
                                                                       }
                                                                       ?>
                                                                       <?php echo $field['required'] ? 'required' : ''; ?>>
                                                            </div>
                                                            <?php break; ?>
                                                        
                                                        <?php case 'textarea': ?>
                                                            <textarea name="field_<?php echo $field['id']; ?>" 
                                                                      class="form-control" 
                                                                      rows="3"
                                                                      placeholder="Enter <?php echo strtolower($field['field_label']); ?>"
                                                                      <?php echo $field['required'] ? 'required' : ''; ?>></textarea>
                                                            <?php break; ?>
                                                        
                                                        <?php case 'select': ?>
                                                            <select name="field_<?php echo $field['id']; ?>" 
                                                                    class="form-select"
                                                                    <?php echo $field['required'] ? 'required' : ''; ?>>
                                                                <option value="">Select <?php echo $field['field_label']; ?></option>
                                                                <?php 
                                                                $options = explode(',', $field['options']);
                                                                foreach ($options as $option):
                                                                    $option = trim($option);
                                                                    if (!empty($option)):
                                                                ?>
                                                                    <option value="<?php echo htmlspecialchars($option); ?>">
                                                                        <?php echo htmlspecialchars($option); ?>
                                                                    </option>
                                                                <?php 
                                                                    endif;
                                                                endforeach; 
                                                                ?>
                                                            </select>
                                                            <?php break; ?>
                                                        
                                                        <?php case 'checkbox': ?>
                                                            <div class="checkbox-group">
                                                                <?php 
                                                                $options = explode(',', $field['options']);
                                                                foreach ($options as $option):
                                                                    $option = trim($option);
                                                                    if (!empty($option)):
                                                                        $optionId = 'checkbox_' . $field['id'] . '_' . md5($option);
                                                                ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" 
                                                                               name="field_<?php echo $field['id']; ?>[]" 
                                                                               id="<?php echo $optionId; ?>" 
                                                                               value="<?php echo htmlspecialchars($option); ?>">
                                                                        <label class="form-check-label" for="<?php echo $optionId; ?>">
                                                                            <?php echo htmlspecialchars($option); ?>
                                                                        </label>
                                                                    </div>
                                                                <?php 
                                                                    endif;
                                                                endforeach; 
                                                                ?>
                                                            </div>
                                                            <?php break; ?>
                                                        
                                                        <?php case 'file': ?>
                                                            <div class="file-upload-wrapper">
                                                                <div class="input-group">
                                                                    <span class="input-group-text">
                                                                        <i class="fas fa-<?php
                                                                            // Choose icon based on field_label
                                                                            $icon = 'file';
                                                                            if (stripos($field['field_label'], 'photo') !== false || 
                                                                                stripos($field['field_label'], 'image') !== false ||
                                                                                stripos($field['field_label'], 'passport') !== false) {
                                                                                $icon = 'camera';
                                                                            } elseif (stripos($field['field_label'], 'certificate') !== false) {
                                                                                $icon = 'certificate';
                                                                            } elseif (stripos($field['field_label'], 'document') !== false) {
                                                                                $icon = 'file-pdf';
                                                                            }
                                                                            echo $icon;
                                                                        ?>"></i>
                                                                    </span>
                                                                    <input type="file" 
                                                                           name="field_<?php echo $field['id']; ?>" 
                                                                           class="form-control"
                                                                           accept="image/*,application/pdf"
                                                                           <?php echo $field['required'] ? 'required' : ''; ?>>
                                                                </div>
                                                                <div class="form-text small text-muted mt-1">
                                                                    <i class="fas fa-info-circle me-1"></i>
                                                                    Accepted files: Images (JPG, PNG, GIF) and PDF. Maximum size: 2MB.
                                                                    <?php if (isset($field['field_label']) && (strtolower($field['field_label']) == 'image' || strtolower($field['field_label']) == 'photo' || strtolower($field['field_label']) == 'passport')): ?>
                                                                    <br><i class="fas fa-lightbulb me-1"></i>For best results, use a passport-sized photo with clear background.
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <?php break; ?>
                                                    <?php endswitch; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php 
                                    // Include the direct class field code
                                    include_once('direct_class_field.php');
                                    ?>

                                    <div class="text-center mt-4">
                                        <button type="submit" name="submit_registration" value="1" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Registration
                                        </button>
                                        
                                        <!-- Test button for direct form submission -->
                                        <button type="button" onclick="testFormSubmission()" class="btn btn-outline-secondary ms-3">
                                            <i class="fas fa-bug me-2"></i>Test Submit
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verifyPayment(event) {
            event.preventDefault();
            const form = document.getElementById('verifyPaymentForm');
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying...';
            
            // Create or clear error message container
            let errorDiv = document.getElementById('paymentErrorMessage');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'paymentErrorMessage';
                errorDiv.className = 'alert alert-danger mt-3';
                form.appendChild(errorDiv);
            }
            errorDiv.style.display = 'none';
            
            fetch('verify_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success mt-3';
                    successDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i><strong>Success!</strong> ' + data.message + '<br>Please wait while we load the registration form...';
                    form.appendChild(successDiv);
                    
                    // Update progress steps
                    document.querySelector('.step:first-child').classList.remove('active');
                    document.querySelector('.step:first-child').classList.add('completed');
                    document.querySelector('.step:first-child').innerHTML = '<i class="fas fa-check"></i>';
                    document.querySelector('.step-connector').classList.add('active');
                    document.querySelector('.step:last-child').classList.add('active');
                    
                    // Hide payment form and redirect after a short delay
                    setTimeout(() => {
                        document.getElementById('paymentVerificationForm').style.display = 'none';
                        document.getElementById('registrationForm').style.display = 'block';
                        // Reload the page to show the registration form with verified payment
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + (data.message || 'An error occurred while verifying the payment.');
                    errorDiv.style.display = 'block';
                    
                    // Reset button
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>A network error occurred while verifying the payment. Please try again.';
                errorDiv.style.display = 'block';
                
                // Reset button
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
            
            return false;
        }

        // File upload validation
        document.addEventListener('DOMContentLoaded', function() {
            // Get all file inputs
            const fileInputs = document.querySelectorAll('input[type="file"]');
            const form = document.getElementById('studentRegistrationForm');
            
            // Log form details for debugging
            if (form) {
                console.log('Form found:', form);
                console.log('Form action:', form.getAttribute('action'));
                console.log('Form method:', form.getAttribute('method'));
                console.log('Form enctype:', form.getAttribute('enctype'));
            } else {
                console.log('Form not found!');
            }
            
            fileInputs.forEach(function(input) {
                input.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Check file size (max 2MB)
                        const maxSize = 2 * 1024 * 1024; // 2MB in bytes
                        if (file.size > maxSize) {
                            alert('File is too large. Maximum size is 2MB.');
                            e.target.value = ''; // Clear the file input
                            return false;
                        }
                        
                        // Check file type
                        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                        if (!validTypes.includes(file.type)) {
                            alert('Invalid file type. Please upload an image (JPG, PNG, GIF) or PDF file.');
                            e.target.value = ''; // Clear the file input
                            return false;
                        }
                        
                        // Show preview for image files
                        if (file.type.startsWith('image/')) {
                            // Create or get preview container
                            let previewContainer = input.closest('.file-upload-wrapper').querySelector('.file-preview');
                            if (!previewContainer) {
                                previewContainer = document.createElement('div');
                                previewContainer.className = 'file-preview mt-3';
                                input.closest('.file-upload-wrapper').appendChild(previewContainer);
                            }
                            
                            // Create preview image
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewContainer.innerHTML = `
                                    <div class="card mx-auto" style="max-width: 200px;">
                                        <img src="${e.target.result}" class="card-img-top" alt="Preview" style="max-height: 150px; object-fit: contain;">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <p class="card-text small text-muted mb-0 text-truncate" style="max-width: 140px;">${file.name}</p>
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-preview">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                
                                // Add event listener to remove button
                                previewContainer.querySelector('.remove-preview').addEventListener('click', function() {
                                    input.value = ''; // Clear the file input
                                    previewContainer.innerHTML = ''; // Clear the preview
                                });
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                });
            });
            
            // Main form validation function
            function validateForm(form, event) {
                console.log('Form validation started');
                
                // Check if any required fields are empty
                const requiredFields = form.querySelectorAll('[required]');
                let hasErrors = false;
                
                requiredFields.forEach(function(field) {
                    if (field.type === 'file') {
                        if (!field.files || field.files.length === 0) {
                            field.classList.add('is-invalid');
                            hasErrors = true;
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    } else if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        hasErrors = true;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (hasErrors) {
                    console.log('Form has validation errors');
                    event.preventDefault();
                    
                    // Scroll to first error
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    return false;
                }
                
                // Log form details before submission
                console.log('Form action:', form.getAttribute('action'));
                console.log('Form method:', form.getAttribute('method'));
                console.log('Form enctype:', form.getAttribute('enctype'));
                console.log('Form values:', new FormData(form));
                
                // Show loading state on submit
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
                
                console.log('Form submission proceeding');
                return true;
            }
        });

        // Function to test direct form submission
        function testFormSubmission() {
            console.log('Testing direct form submission');
            
            // Create a simple form submission with the essential fields
            const formData = new FormData();
            formData.append('registration_type', document.querySelector('input[name="registration_type"]').value);
            formData.append('payment_reference', document.querySelector('input[name="payment_reference"]').value);
            formData.append('submit_registration', '1');
            
            // Add a test field
            formData.append('test_field', 'Testing direct submission');
            
            // Show loading state
            const statusDiv = document.createElement('div');
            statusDiv.className = 'alert alert-info mt-3';
            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing direct submission...';
            document.querySelector('.text-center.mt-4').appendChild(statusDiv);
            
            // Submit directly via fetch
            fetch('save_registration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(data => {
                console.log('Response data:', data);
                statusDiv.className = 'alert alert-success mt-3';
                statusDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>Test completed. Check console for details.';
            })
            .catch(error => {
                console.error('Error:', error);
                statusDiv.className = 'alert alert-danger mt-3';
                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Error: ' + error.message;
            });
        }
    </script>
</body>
</html> 