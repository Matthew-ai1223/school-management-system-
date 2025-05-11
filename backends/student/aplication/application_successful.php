<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check - redirect if not submitted through proper channel
if (!isset($_SESSION['application_submitted']) || !$_SESSION['application_submitted']) {
    header("Location: application_form.php");
    exit();
}

// Get application details from session
$applicationType = isset($_SESSION['application_type']) ? $_SESSION['application_type'] : '';
$applicationRef = isset($_SESSION['application_reference']) ? $_SESSION['application_reference'] : '';
$paymentRef = isset($_SESSION['payment_reference']) ? $_SESSION['payment_reference'] : '';
$applicantName = isset($_SESSION['applicant_name']) ? $_SESSION['applicant_name'] : '';

// Split the full name into first and last name
$nameParts = explode(' ', $applicantName);
$firstName = isset($nameParts[0]) ? $nameParts[0] : '';
$lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : ''; // Combine remaining parts as last name

// Store these values before clearing session
$type = $applicationType;
$appRef = $applicationRef;
$payRef = $paymentRef;
$fName = $firstName;
$lName = $lastName;

// Clear the application submission session data
unset($_SESSION['application_submitted']);
unset($_SESSION['application_reference']);
unset($_SESSION['payment_reference']);
unset($_SESSION['application_type']);
unset($_SESSION['applicant_name']);

// If all session data is missing, redirect to form
if (empty($type) && empty($appRef) && empty($payRef)) {
    header("Location: application_form.php");
    exit();
}

// Reassign for use in the page
$applicationType = $type;
$applicationRef = $appRef;
$paymentRef = $payRef;
$firstName = $fName;
$lastName = $lName;
$applicantName = trim($firstName . ' ' . $lastName);

// Get applicant's first name for personalized message
$applicantFirstName = ucfirst(strtolower($firstName));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted Successfully - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --bg-light: #f8f9fa;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: #f5f7fa;
            color: #2c3e50;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .success-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .school-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            border-bottom: 2px solid var(--bg-light);
        }

        .school-logo {
            max-width: 150px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .school-logo:hover {
            transform: scale(1.05);
        }

        .success-icon {
            font-size: 5rem;
            color: var(--success-color);
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .reference-box {
            background: var(--bg-light);
            padding: 25px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .reference-box:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .reference-number {
            font-family: 'Consolas', monospace;
            font-size: 1.2em;
            color: var(--primary-color);
            background: rgba(13, 110, 253, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .next-steps {
            margin-top: 40px;
            padding: 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .next-steps h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }

        .next-steps li {
            margin-bottom: 20px;
            padding-left: 10px;
            position: relative;
            list-style-position: inside;
        }

        .next-steps li::marker {
            color: var(--primary-color);
            font-weight: bold;
        }

        .alert-info {
            background-color: rgba(13, 110, 253, 0.1);
            border: none;
            border-radius: var(--border-radius);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-info i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }

        .contact-info {
            margin-top: 40px;
            padding: 20px;
            border-top: 2px solid var(--bg-light);
            text-align: center;
        }

        .contact-info p {
            color: #6c757d;
            line-height: 1.8;
        }

        @media (max-width: 768px) {
            .success-container {
                margin: 20px;
                padding: 20px;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        @media print {
            .no-print {
                display: none;
            }
            
            body {
                background: white;
            }

            .success-container {
                box-shadow: none;
                margin: 0;
                padding: 20px;
            }

            .reference-box {
                border: 1px solid #dee2e6;
            }
        }
    </style>
</head>
<body>
    <div class="container success-container">
        <div class="school-header">
            <img src="../../../assets/images/logo.png" alt="School Logo" class="school-logo">
            <h1 class="mb-0"><?php echo SCHOOL_NAME; ?></h1>
        </div>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'pdf_generation'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Unable to generate PDF receipt. You can try again or use the print option instead.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="text-center">
            <i class="bi bi-check-circle-fill success-icon"></i>
            <h2 class="mb-4">Application Submitted Successfully!</h2>
            <?php if (!empty($applicantFirstName)): ?>
            <p class="lead">Congratulations <?php echo htmlspecialchars($applicantFirstName); ?>! Your application to <?php echo SCHOOL_NAME; ?> has been received and is being processed.</p>
            <?php else: ?>
            <p class="lead">Thank you for applying to <?php echo SCHOOL_NAME; ?>. Your application has been received and is being processed.</p>
            <?php endif; ?>
            <p class="text-muted">Application Type: <span class="badge bg-primary"><?php echo ucfirst(htmlspecialchars($applicationType)); ?></span></p>
        </div>

        <!-- <div class="reference-box">
            <h4 class="mb-4">Application Details</h4>
            <div class="row">
                <div class="col-md-4">
                    <p class="mb-3"><strong>Application Type:</strong><br>
                        <span class="text-capitalize"><?php echo htmlspecialchars($applicationType); ?></span>
                    </p>
                </div>
                <div class="col-md-4">
                    <p class="mb-3"><strong>Application Reference:</strong><br>
                        <span class="reference-number"><?php echo htmlspecialchars($applicationRef); ?></span>
                    </p>
                </div>
                <div class="col-md-4">
                    <p class="mb-3"><strong>Payment Reference:</strong><br>
                        <span class="reference-number"><?php echo htmlspecialchars($paymentRef); ?></span>
                    </p>
                </div>
            </div>
        </div> -->

        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                Please download and save the receipt. You will need it for future correspondence.
            </div>
        </div>

        <!-- <div class="next-steps">
            <h3><i class="bi bi-list-check me-2"></i>Next Steps</h3>
            <ol>
                <li>You will receive a confirmation email with your application details.</li>
                <li>Our admissions team will review your application within 5-7 working days.</li>
                <li>You will be notified via email about the status of your application.</li>
                <li>If additional documents are required, we will contact you through the provided email address.</li>
            </ol>
        </div> -->

        <div class="mt-4 text-center no-print">
            <!-- <button onclick="window.print()" class="btn btn-secondary me-2">
                <i class="bi bi-printer"></i> Print This Page
            </button> -->
            <a href="generate_receipt_pdf.php?type=<?php echo urlencode($applicationType); ?>&app_ref=<?php echo urlencode($applicationRef); ?>&pay_ref=<?php echo urlencode($paymentRef); ?>&first_name=<?php echo urlencode($firstName); ?>&last_name=<?php echo urlencode($lastName); ?>" class="btn btn-primary">
                <i class="bi bi-download"></i> Download Receipt
            </a>
            <a href="../../../index.php" class="btn btn-primary">
                <i class="bi bi-house"></i> Return to Homepage
            </a>
        </div>

        <div class="contact-info">
            <p>
                For any queries, please contact our admissions office:<br>
                <strong>Email:</strong> <?php echo SCHOOL_EMAIL; ?><br>
                <strong>Phone:</strong> <?php echo SCHOOL_PHONE; ?>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
