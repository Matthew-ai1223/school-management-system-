<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../payment_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';
$success_message = '';

if (isset($_POST['submit'])) {
    $payment_reference = trim($_POST['payment_reference']);
    
    if (empty($payment_reference)) {
        $error_message = "Please enter your payment reference number.";
    } else {
        // Connect to database
        $db = Database::getInstance();
        $mysqli = $db->getConnection();
        
        // Step 1: Find payment information
        $stmt = $mysqli->prepare("SELECT * FROM application_payments WHERE reference = ?");
        $stmt->bind_param("s", $payment_reference);
        $stmt->execute();
        $payment_result = $stmt->get_result();
        $payment_data = $payment_result->fetch_assoc();
        
        if (!$payment_data) {
            $error_message = "Invalid payment reference. Please check and try again.";
        } else {
            // Step 2: Find associated application
            $stmt = $mysqli->prepare("SELECT * FROM applications WHERE applicant_data LIKE ?");
            $search_param = '%"payment_reference":"' . $payment_reference . '"%';
            $stmt->bind_param("s", $search_param);
            $stmt->execute();
            $app_result = $stmt->get_result();
            $application = $app_result->fetch_assoc();
            
            if (!$application) {
                $error_message = "Application record not found for this payment reference.";
            } else {
                // Extract needed data from application
                $applicant_data = json_decode($application['applicant_data'], true);
                $application_type = $application['application_type'];
                
                // Get applicant's name
                $full_name = $applicant_data['field_full_name'] ?? '';
                // Split the name for first and last name parameters
                $name_parts = explode(' ', $full_name, 2);
                $first_name = $name_parts[0] ?? '';
                $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                
                // Get class seeking admission
                $class_admission = $applicant_data['field_class_admission'] ?? '';
                
                // Get passport photo path if available
                $passport_photo = $applicant_data['field_passport_photo'] ?? '';
                
                // Generate unique application reference
                $application_ref = 'APP_' . strtoupper(substr($payment_reference, -8));
                
                // Redirect to generate_receipt_pdf.php with all needed parameters
                $url = "generate_receipt_pdf.php";
                $url .= "?type=" . urlencode($application_type);
                $url .= "&app_ref=" . urlencode($application_ref);
                $url .= "&pay_ref=" . urlencode($payment_reference);
                $url .= "&first_name=" . urlencode($first_name);
                $url .= "&last_name=" . urlencode($last_name);
                $url .= "&class_admission=" . urlencode($class_admission);
                
                if (!empty($passport_photo)) {
                    $url .= "&photo=" . urlencode($passport_photo);
                }
                
                // Redirect to generate PDF
                header("Location: " . $url);
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regenerate Receipt - ACE MODEL COLLEGE</title>
    
    <!-- Include CSS files -->
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
            max-width: 600px;
            margin: 40px auto;
            padding: 0;
        }

        .school-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            text-align: center;
            margin-bottom: 0;
        }

        .school-header h1 {
            font-size: 2.2em;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .school-header h2 {
            font-size: 1.4em;
            opacity: 0.9;
        }

        .school-logo {
            width: 100px;
            height: 100px;
            margin-bottom: 15px;
            border-radius: 50px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-md);
        }

        .receipt-form {
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

        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(41, 98, 255, 0.15);
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
        
        .receipt-instructions {
            background-color: var(--light-gray);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .receipt-instructions h3 {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .receipt-instructions p {
            color: #555;
            margin-bottom: 8px;
        }
        
        .receipt-instructions ul {
            padding-left: 20px;
        }
        
        .receipt-instructions ul li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container form-container">
        <div class="school-header">
            <img src="../../../images/logo.png" alt="ACE Model College Logo" class="school-logo">
            <h1>ACE MODEL COLLEGE</h1>
            <h2>Application Receipt Recovery</h2>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" class="receipt-form">
            <div class="receipt-instructions">
                <h3><i class="fas fa-info-circle"></i> Instructions</h3>
                <p>To regenerate your application receipt, please enter your payment reference number below.</p>
                <ul>
                    <li>The payment reference was sent to you via email when you made the payment.</li>
                    <li>It typically starts with "KID_" or "COL_" followed by numbers and letters.</li>
                    <li>You can also find it in your payment confirmation email or SMS.</li>
                </ul>
            </div>
            
            <div class="mb-4">
                <label for="payment_reference" class="form-label">Payment Reference <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="payment_reference" name="payment_reference" required
                       placeholder="Enter your payment reference (e.g., KID_123456 or COL_123456)">
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" name="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-file-invoice"></i> Generate Receipt
                </button>
            </div>
            
            <div class="mt-4 text-center">
                <a href="../../../index.html" class="btn btn-outline-secondary">
                    <i class="fas fa-home"></i> Back to Home
                </a>
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
                        <a href="#" target="_blank" style="margin:0 8px; color:#3b5998; font-size:22px;"><i class="icon-facebook2"></i></a>
                        <a href="#" target="_blank" style="margin:0 8px; color:#E1306C; font-size:22px;"><i class="icon-instagram"></i></a>
                        <a href="#" target="_blank" style="margin:0 8px; color:#FF0000; font-size:22px;"><i class="icon-youtube"></i></a>
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
    </script>
</body>
</html> 