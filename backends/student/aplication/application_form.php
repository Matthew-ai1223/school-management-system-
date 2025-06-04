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

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize authentication
$auth = new Auth();

// Get application type from URL parameter
$applicationType = isset($_GET['type']) ? $_GET['type'] : 'kiddies';
if (!in_array($applicationType, ['kiddies', 'college'])) {
    $applicationType = 'kiddies';
}

// Verify payment before allowing access
$payment_verified = false;
$payment_error = '';

try {
    // Check if payment reference is in session or URL
    $reference = isset($_SESSION['payment_reference']) ? $_SESSION['payment_reference'] : 
                (isset($_GET['reference']) ? $_GET['reference'] : '');

    error_log("Payment Verification Debug - Reference: " . $reference);
    error_log("Payment Verification Debug - Session Data: " . print_r($_SESSION, true));

    if (empty($reference)) {
        throw new Exception("No payment reference found. Please complete payment first.");
    }

    // Verify payment status with more detailed error logging
    $sql = "SELECT * FROM application_payments WHERE reference = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Database prepare failed: " . $conn->error);
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $reference);
    
    if (!$stmt->execute()) {
        error_log("Database execute failed: " . $stmt->error);
        throw new Exception("Database query failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    error_log("Payment Verification Debug - Payment Data: " . print_r($payment, true));

    if (!$payment) {
        throw new Exception("Payment record not found. Please complete payment first.");
    }

    // Check payment status
    if ($payment['status'] !== PAYMENT_STATUS_COMPLETED) {
        error_log("Payment status not completed: " . $payment['status']);
        throw new Exception("Payment not completed. Current status: " . $payment['status']);
    }

    // Verify application type matches if specified
    if (!empty($applicationType) && $payment['application_type'] !== $applicationType) {
        error_log("Application type mismatch: Expected {$applicationType}, got {$payment['application_type']}");
        throw new Exception("Invalid application type for this payment.");
    }

    $payment_verified = true;
    $_SESSION['payment_verified'] = true;
    $_SESSION['payment_reference'] = $reference;
    $_SESSION['application_type'] = $payment['application_type'];

    error_log("Payment verification successful - Session updated");

} catch (Exception $e) {
    $payment_error = $e->getMessage();
    error_log("Payment verification error: " . $payment_error);
    error_log("Debug backtrace: " . print_r(debug_backtrace(), true));
}

// If payment is not verified, redirect to payment page with detailed error
if (!$payment_verified) {
    error_log("Redirecting to payment page - Error: " . $payment_error);
    $_SESSION['error_message'] = $payment_error;
    
    // Add more detailed error information to the URL
    $redirect_url = "payment.php?type=" . urlencode($applicationType) . 
                   "&error=" . urlencode($payment_error) . 
                   "&debug=1" . 
                   (isset($_SESSION['payment_reference']) ? "&session_ref=" . urlencode($_SESSION['payment_reference']) : "") .
                   (isset($_GET['reference']) ? "&url_ref=" . urlencode($_GET['reference']) : "");
                   
    header("Location: " . $redirect_url);
    exit();
}

// Define the application form fields
function getApplicationFields($applicationType) {
    $fields = [
        // Student Information
        [
            'id' => 'full_name',
            'field_label' => 'Full Name',
            'field_type' => 'text',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'dob',
            'field_label' => 'Date of Birth',
            'field_type' => 'date',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'gender',
            'field_label' => 'Gender',
            'field_type' => 'select',
            'options' => 'Male,Female',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'previous_school',
            'field_label' => 'Previous School Attended',
            'field_type' => 'text',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'class_admission',
            'field_label' => 'Class Seeking Admission Into',
            'field_type' => 'select',
            'options' => $applicationType === 'kiddies' ? 
                'Pre-Nursery,Nursery 1,Nursery 2,Primary 1,Primary 2,Primary 3,Primary 4,Primary 5,Primary 6' : 
                'JSS 1,JSS 2,JSS 3,SS 1,SS 2,SS 3',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'passport_photo',
            'field_label' => 'Passport Photograph',
            'field_type' => 'file',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        
        // Parent Information
        [
            'id' => 'parent_name',
            'field_label' => 'Parent/Guardian Name',
            'field_type' => 'text',
            'required' => true,
            'field_group' => 'Parent Information'
        ],
        [
            'id' => 'parent_phone',
            'field_label' => 'Parent/Guardian Phone Number',
            'field_type' => 'text',
            'required' => true,
            'field_group' => 'Parent Information'
        ],
        [
            'id' => 'parent_email',
            'field_label' => 'Parent/Guardian Email',
            'field_type' => 'email',
            'required' => true,
            'field_group' => 'Parent Information'
        ],
        [
            'id' => 'home_address',
            'field_label' => 'Home Address',
            'field_type' => 'textarea',
            'required' => true,
            'field_group' => 'Parent Information'
        ]
    ];
    
    return $fields;
}

// Handle application submission
if (isset($_POST['submit_application'])) {
    $fields = getApplicationFields($applicationType);
    $application_data = [
        'application_type' => $applicationType
    ];
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../../../uploads/' . $applicationType . '/' . date('Y/m/d');
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $has_error = false;
    $error_message = '';
    
    foreach ($fields as $field) {
        $field_name = "field_" . $field['id'];
        
        if ($field['field_type'] === 'file' && isset($_FILES[$field_name])) {
            $file = $_FILES[$field_name];
            
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                if ($field['required']) {
                    $has_error = true;
                    $error_message = "Please upload file for " . $field['field_label'];
                    break;
                }
                continue;
            }
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $has_error = true;
                $error_message = "Error uploading file for " . $field['field_label'];
                break;
            }
            
            // Generate unique filename
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            
            // Special handling for passport photos - store in consistent location
            if ($field['id'] === 'passport_photo') {
                $upload_dir = '../../../uploads/passports/' . date('Y/m');
            } else {
                $upload_dir = '../../../uploads/' . $applicationType . '/' . date('Y/m/d');
            }
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . '/' . $new_filename;
            $relative_path = str_replace('../../../', '', $upload_path);
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Store relative path in database and log for debugging
                $application_data[$field_name] = $relative_path;
                error_log("File uploaded for field {$field['id']}: " . $relative_path);
            } else {
                $has_error = true;
                $error_message = "Failed to save uploaded file for " . $field['field_label'];
                error_log("Failed to move uploaded file to: " . $upload_path);
                break;
            }
        } else if (isset($_POST[$field_name])) {
            $application_data[$field_name] = $_POST[$field_name];
        } else if ($field['required']) {
            $has_error = true;
            $error_message = "Please fill in " . $field['field_label'];
            break;
        }
    }
    
    if (!$has_error) {
        // Add payment reference to application data
        $application_data['payment_reference'] = $_SESSION['payment_reference'];
        
        // Save application data to database
        $sql = "INSERT INTO applications (applicant_data, application_type, submission_date) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $json_data = json_encode($application_data);
        $stmt->bind_param("ss", $json_data, $applicationType);
        
        error_log("Attempting to save application - Data: " . print_r($application_data, true));
        
        if ($stmt->execute()) {
            // Get the applicant's name from the form data
            $applicantName = $_POST['field_full_name'] ?? '';

            // Store necessary data in session for success page
            $_SESSION['application_submitted'] = true;
            $_SESSION['application_reference'] = uniqid('APP_', true);
            $_SESSION['applicant_name'] = $applicantName;
            $_SESSION['application_type'] = $applicationType;
            
            error_log("Application saved successfully - Session data: " . print_r($_SESSION, true));
            
            // Clear payment session data after successful submission
            unset($_SESSION['payment_verified']);
            unset($_SESSION['payment_amount']);
            
            // Ensure headers haven't been sent yet
            if (!headers_sent()) {
                // Redirect to success page with parameters
                $redirect_url = "application_successful.php?" . http_build_query([
                    'type' => $applicationType,
                    'ref' => $_SESSION['application_reference'],
                    'timestamp' => time()
                ]);
                
                error_log("Redirecting to: " . $redirect_url);
                header("Location: " . $redirect_url);
                exit();
            } else {
                error_log("Headers already sent - Cannot redirect");
                echo "<script>window.location.href = 'application_successful.php';</script>";
                exit();
            }
        } else {
            error_log("Failed to save application - Error: " . $stmt->error);
            $error_message = "Failed to submit application. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACE <?php echo ucfirst($applicationType); ?> - Application Form</title>
    
    <!-- Include the same CSS as payment page -->
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:300,400,600,700" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:300,400" rel="stylesheet">
    <link rel="stylesheet" href="../../../css/bootstrap.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1a237e;
            --secondary-color: #2962ff;
            --success-color: #00c853;
            --danger-color: #f50057;
            --light-gray: #f8f9fa;
            --border-color: #e0e0e0;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 15px;
        }

        .form-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0;
        }

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
            opacity: 0.9;
        }

        .school-logo {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            border-radius: 60px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-md);
        }

        .application-type-switch {
            background: white;
            padding: 20px;
            border-radius: 0;
            margin-bottom: 0;
            box-shadow: var(--shadow-sm);
        }

        .application-type-switch .btn-group {
            width: 100%;
            gap: 10px;
            margin-bottom: 0;
        }

        .application-type-switch .btn {
            flex: 1;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
        }

        .application-type-switch .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .application-type-switch .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .application-type-switch .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .application-form {
            background: white;
            padding: 30px;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .form-label {
            font-weight: 600;
            color: #444;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(41, 98, 255, 0.15);
        }

        textarea.form-control {
            min-height: 120px;
        }

        .file-preview {
            margin-top: 15px;
            padding: 15px;
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            text-align: center;
        }

        .file-preview img {
            max-width: 300px;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: var(--light-gray);
            border-radius: 8px;
            margin-top: 10px;
        }

        .file-info i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(41, 98, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(41, 98, 255, 0.4);
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 20px;
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

        /* Field groups */
        .field-group {
            background: var(--light-gray);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        .field-group-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-container {
                margin: 20px;
            }

            .school-header {
                padding: 30px 20px;
            }

            .school-header h1 {
                font-size: 2em;
            }

            .application-form {
                padding: 20px;
            }

            .file-preview img {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container form-container">
        <div class="school-header">
            <img src="../../../images/logo.png" alt="ACE Kiddies and College Logo" class="school-logo">
            <h1>ACE MODEL COLLEGE</h1>
            <h2><?php echo ucfirst($applicationType); ?> Application Form</h2>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="application-type-switch">
            <div class="btn-group">
                <a href="?type=kiddies" class="btn btn-<?php echo $applicationType === 'kiddies' ? 'primary' : 'outline-primary'; ?>">
                    <i class="fas fa-child"></i> Kiddies Application
                </a>
                <a href="?type=college" class="btn btn-<?php echo $applicationType === 'college' ? 'primary' : 'outline-primary'; ?>">
                    <i class="fas fa-graduation-cap"></i> College Application
                </a>
            </div>
        </div>

        <form method="POST" class="application-form" enctype="multipart/form-data">
            <?php
            $fields = getApplicationFields($applicationType);
            $fieldGroups = [];
            
            // First pass: organize fields by group
            foreach ($fields as $field) {
                $groupName = !empty($field['field_group']) ? $field['field_group'] : 'Other Information';
                if (!isset($fieldGroups[$groupName])) {
                    $fieldGroups[$groupName] = [];
                }
                $fieldGroups[$groupName][] = $field;
            }
            
            // Second pass: display fields by group
            foreach ($fieldGroups as $groupName => $groupFields):
            ?>
                <div class="field-group">
                    <h3 class="field-group-title"><?php echo htmlspecialchars($groupName); ?></h3>
                    
                    <?php foreach ($groupFields as $field):
                        $field_id = "field_" . $field['id'];
                        $required = $field['required'] ? 'required' : '';
                    ?>
                    <div class="mb-4">
                        <label for="<?php echo $field_id; ?>" class="form-label">
                            <?php echo htmlspecialchars($field['field_label']); ?>
                            <?php if ($field['required']): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($field['field_type'] === 'textarea'): ?>
                            <textarea class="form-control" id="<?php echo $field_id; ?>" name="<?php echo $field_id; ?>" <?php echo $required; ?>
                                      placeholder="Enter <?php echo strtolower($field['field_label']); ?>"></textarea>
                        <?php elseif ($field['field_type'] === 'select'): ?>
                            <select class="form-select" id="<?php echo $field_id; ?>" name="<?php echo $field_id; ?>" <?php echo $required; ?>>
                                <option value="">Select <?php echo strtolower($field['field_label']); ?></option>
                                <?php
                                $options = explode(',', $field['options']);
                                foreach ($options as $option):
                                    $option = trim($option);
                                    if (!empty($option)):
                                ?>
                                <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                        <?php elseif ($field['field_type'] === 'file'): ?>
                            <input type="file" class="form-control" id="<?php echo $field_id; ?>" name="<?php echo $field_id; ?>" <?php echo $required; ?> 
                                   accept="<?php echo $field['file_types'] ?? 'image/*'; ?>"
                                   onchange="previewFile(this)">
                            <div id="<?php echo $field_id; ?>_preview" class="file-preview"></div>
                        <?php else: ?>
                            <input type="<?php echo $field['field_type']; ?>" 
                                   class="form-control" 
                                   id="<?php echo $field_id; ?>" 
                                   name="<?php echo $field_id; ?>"
                                   placeholder="Enter <?php echo strtolower($field['field_label']); ?>"
                                   <?php echo $required; ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="text-center mt-4">
                <button type="submit" name="submit_application" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
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

        function previewFile(input) {
            const preview = document.getElementById(input.id + '_preview');
            const file = input.files[0];
            
            if (!file) {
                preview.innerHTML = '';
                return;
            }
            
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="File preview">
                        <div class="file-info">
                            <i class="fas fa-image"></i>
                            <span>${file.name} (${formatFileSize(file.size)})</span>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = `
                    <div class="file-info">
                        <i class="fas fa-file"></i>
                        <span>${file.name} (${formatFileSize(file.size)})</span>
                    </div>
                `;
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html> 