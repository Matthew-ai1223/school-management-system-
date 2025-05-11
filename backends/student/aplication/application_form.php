<?php
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

    error_log("Checking payment verification for reference: " . $reference);
    error_log("Session payment_verified: " . (isset($_SESSION['payment_verified']) ? 'true' : 'false'));
    error_log("Application type: " . $applicationType);

    if (empty($reference)) {
        throw new Exception("No payment reference found. Please complete payment first.");
    }

    // Verify payment status
    $sql = "SELECT * FROM application_payments WHERE reference = ? AND status = ? AND application_type = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $completed_status = PAYMENT_STATUS_COMPLETED;
    $stmt->bind_param("sss", $reference, $completed_status, $applicationType);
    
    if (!$stmt->execute()) {
        throw new Exception("Database query failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    error_log("Payment verification query result: " . ($payment ? "Payment found" : "Payment not found"));

    if (!$payment) {
        throw new Exception("Invalid payment reference or payment not completed. Please complete payment first.");
    }

    $payment_verified = true;
    $_SESSION['payment_verified'] = true;
    $_SESSION['payment_reference'] = $reference;
    $_SESSION['application_type'] = $applicationType;

    error_log("Payment verification successful. Session variables set.");

} catch (Exception $e) {
    $payment_error = $e->getMessage();
    error_log("Payment verification error: " . $payment_error);
}

// If payment is not verified, redirect to payment page
if (!$payment_verified) {
    error_log("Redirecting to payment page due to unverified payment. Error: " . $payment_error);
    $_SESSION['error_message'] = $payment_error;
    header("Location: payment.php?type=" . urlencode($applicationType) . "&error=" . urlencode($payment_error));
    exit();
}

// Function to get all form fields from database
function getFormFields($conn, $applicationType) {
    $sql = "SELECT * FROM form_fields WHERE is_active = 1 AND application_type = ? ORDER BY field_order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $applicationType);
    $stmt->execute();
    $result = $stmt->get_result();
    $fields = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $fields[] = $row;
        }
    }
    return $fields;
}

// Handle application submission
if (isset($_POST['submit_application'])) {
    $fields = getFormFields($conn, $applicationType);
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
            $upload_path = $upload_dir . '/' . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Store relative path in database
                $relative_path = str_replace('../../../', '', $upload_path);
                $application_data[$field_name] = $relative_path;
            } else {
                $has_error = true;
                $error_message = "Failed to save uploaded file for " . $field['field_label'];
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
        if ($stmt->execute()) {
            // Get the applicant's name from the form data
            $applicantName = '';
            foreach ($fields as $field) {
                if (strpos(strtolower($field['field_label']), 'name') !== false) {
                    $field_name = "field_" . $field['id'];
                    $applicantName = $_POST[$field_name];
                    break;
                }
            }

            // Store necessary data in session for success page
            $_SESSION['application_submitted'] = true;
            $_SESSION['application_reference'] = uniqid('APP_', true);
            $_SESSION['applicant_name'] = $applicantName;
            
            // Clear payment session data after successful submission
            unset($_SESSION['payment_verified']);
            unset($_SESSION['payment_amount']);
            
            // Redirect to success page
            header("Location: application_successful.php");
            exit();
        } else {
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
    <!-- Navigation Bar -->
    <!-- <nav class="fh5co-nav" role="navigation">
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
    </nav> -->

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
            $fields = getFormFields($conn, $applicationType);
            $currentGroup = '';
            foreach ($fields as $field):
                $field_id = "field_" . $field['id'];
                $required = $field['required'] ? 'required' : '';
                
                // Check if this field belongs to a new group
                if (!empty($field['field_group']) && $field['field_group'] !== $currentGroup):
                    if ($currentGroup !== '') echo '</div>'; // Close previous group
                    $currentGroup = $field['field_group'];
            ?>
                <div class="field-group">
                    <h3 class="field-group-title"><?php echo htmlspecialchars($currentGroup); ?></h3>
            <?php
                endif;
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
                           accept="<?php echo $field['file_types'] ?? '*/*'; ?>"
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
            <?php endforeach; 
            if ($currentGroup !== '') echo '</div>'; // Close last group if exists
            ?>

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