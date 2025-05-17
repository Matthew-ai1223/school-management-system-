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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --success-color: #4ade80;
            --light-bg: #f8fafc;
            --dark-bg: #1e293b;
            --border-radius: 16px;
            --box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--light-bg);
            color: #334155;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            transition: all 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }

        .success-container {
            width: 100%;
            max-width: 850px;
            margin: 20px auto;
            padding: 40px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            animation: fadeInUp 0.8s ease-out;
            position: relative;
            overflow: hidden;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .bg-pattern {
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25px 25px, var(--accent-color) 2%, transparent 0%),
                radial-gradient(circle at 75px 75px, var(--accent-color) 2%, transparent 0%);
            background-size: 100px 100px;
            opacity: 0.05;
            z-index: 0;
        }

        .content-wrapper {
            position: relative;
            z-index: 1;
        }

        .school-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
        }

        .school-logo {
            max-width: 140px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .school-logo:hover {
            transform: scale(1.08);
        }

        .success-icon-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
        }

        .success-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            background-color: rgba(74, 222, 128, 0.1);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 0.6;
            }
            50% {
                transform: scale(1.15);
                opacity: 0.3;
            }
            100% {
                transform: scale(1);
                opacity: 0.6;
            }
        }

        .success-icon {
            position: relative;
            font-size: 3.5rem;
            color: var(--success-color);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            animation: bounceIn 0.6s ease-out 0.2s both;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                transform: scale(1);
            }
        }

        .application-details {
            background: linear-gradient(to right bottom, #f8fafc, #f1f5f9);
            padding: 30px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            border-left: 5px solid var(--primary-color);
            transition: all 0.3s ease;
            position: relative;
        }

        .application-details:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow);
        }

        .reference-number {
            font-family: 'Roboto Mono', 'Consolas', monospace;
            font-size: 1.1em;
            font-weight: 600;
            color: var(--primary-color);
            background: rgba(67, 97, 238, 0.1);
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-block;
            letter-spacing: 0.5px;
        }

        .timeline {
            position: relative;
            margin: 40px 0;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #cbd5e1;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -30px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.3);
        }

        .timeline-content {
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        .timeline-content:hover {
            transform: translateX(5px);
        }

        .timeline-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-alert {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(76, 201, 240, 0.1));
            border: none;
            border-radius: var(--border-radius);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 30px 0;
            border-left: 5px solid var(--accent-color);
            animation: slideIn 0.5s ease-out 0.4s both;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .info-alert i {
            font-size: 1.8rem;
            color: var(--accent-color);
        }

        .info-alert-content {
            flex: 1;
        }

        .btn-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 40px;
            justify-content: center;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(90deg, rgba(255,255,255,0.2), rgba(255,255,255,0));
            transition: all 0.4s ease;
        }

        .btn:hover::after {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .btn i {
            font-size: 1.1em;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
        }

        .btn-secondary {
            background-color: #64748b;
            border: none;
        }

        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 2px solid rgba(67, 97, 238, 0.2);
        }

        .btn-outline:hover {
            background: rgba(67, 97, 238, 0.05);
            border-color: var(--primary-color);
        }

        .contact-info {
            margin-top: 40px;
            padding: 30px 0 10px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            animation: fadeIn 0.5s ease-out 0.6s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .contact-info-title {
            font-weight: 600;
            color: #64748b;
            margin-bottom: 15px;
        }

        .contact-methods {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }

        .contact-method {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            border-radius: 12px;
            min-width: 160px;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .contact-method:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .contact-method i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .contact-value {
            font-weight: 500;
        }

        .dark-mode-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            color: #334155;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 100;
            border: none;
            font-size: 1.2rem;
        }

        .dark-mode-toggle:hover {
            transform: scale(1.1);
        }

        /* Dark Mode Styles */
        body.dark-mode {
            background-color: var(--dark-bg);
            color: #e2e8f0;
        }

        body.dark-mode .success-container {
            background: #0f172a;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .school-header {
            border-bottom: 1px solid #1e293b;
        }

        body.dark-mode .application-details {
            background: linear-gradient(to right bottom, #1e293b, #1a1e2b);
        }

        body.dark-mode .timeline::before {
            background-color: #334155;
        }

        body.dark-mode .timeline-content {
            background-color: #1e293b;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .timeline-dot {
            border: 3px solid #0f172a;
        }

        body.dark-mode .info-alert {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.15), rgba(76, 201, 240, 0.15));
        }

        body.dark-mode .contact-info {
            border-top: 1px solid #1e293b;
        }

        body.dark-mode .contact-method {
            background: #1e293b;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .dark-mode-toggle {
            background: #334155;
            color: #f1f5f9;
        }

        body.dark-mode .btn-outline {
            background: #1e293b;
            color: #94a3b8;
            border: 2px solid rgba(148, 163, 184, 0.2);
        }

        body.dark-mode .btn-outline:hover {
            background: rgba(148, 163, 184, 0.1);
            border-color: #94a3b8;
        }

        /* Confetti Animation Container */
        #confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .success-container {
                padding: 25px 20px;
                margin: 10px;
                border-radius: 12px;
            }

            .btn-container {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }

            .timeline {
                padding-left: 25px;
            }

            .contact-methods {
                flex-direction: column;
                align-items: center;
            }

            .contact-method {
                width: 100%;
                max-width: 300px;
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

            .application-details {
                border: 1px solid #dee2e6;
            }
            
            .bg-pattern,
            .dark-mode-toggle,
            #confetti {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div id="confetti"></div>
    <button id="darkModeToggle" class="dark-mode-toggle no-print" aria-label="Toggle Dark Mode">
        <i class="bi bi-moon"></i>
    </button>

    <div class="success-container">
        <div class="bg-pattern"></div>
        <div class="content-wrapper">
            <div class="school-header">
                <img src="../../../images/logo.png" alt="School Logo" class="school-logo">
                <h1 class="mb-0"><?php echo SCHOOL_NAME; ?></h1>
            </div>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'pdf_generation'): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Unable to generate PDF receipt. You can try again or use the print option instead.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="text-center">
                <div class="success-icon-container">
                    <div class="success-bg"></div>
                    <div class="success-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
                <h2 class="mb-4 fw-bold">Application Submitted Successfully!</h2>
                <?php if (!empty($applicantFirstName)): ?>
                <p class="lead">Congratulations <?php echo htmlspecialchars($applicantFirstName); ?>! Your application to ACE <span><?php echo ucfirst(htmlspecialchars($applicationType)); ?></span> has been received and is being processed.</p>
                <?php else: ?>
                <p class="lead">Thank you for applying to ACE <span><?php echo ucfirst(htmlspecialchars($applicationType)); ?></span>. Your application has been received and is being processed.</p>
                <?php endif; ?>
                <p class="text-muted">Application Type: <span class="badge text-bg-primary rounded-pill"><?php echo ucfirst(htmlspecialchars($applicationType)); ?></span></p>
            </div>

            <div class="info-alert">
                <i class="bi bi-info-circle-fill"></i>
                <div class="info-alert-content">
                    <h5 class="m-0 fw-semibold">Important Notice</h5>
                    <p class="m-0">Please download your receipt and save it for future reference. You'll need it during the application process. </p>
                </div>
            </div>

            <div class="application-details">
                <h4 class="mb-4 fw-bold"><i class="bi bi-file-earmark-check me-2"></i>Application Details</h4>
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
            </div>

            <!-- <div class="timeline-section">
                <h4 class="fw-bold mb-4"><i class="bi bi-arrow-down-circle me-2"></i>What happens next?</h4>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">
                                <i class="bi bi-envelope-check"></i> Confirmation Email
                            </div>
                            <p>You will receive a confirmation email with your application details within 24 hours.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">
                                <i class="bi bi-clipboard-check"></i> Application Review
                            </div>
                            <p>Our admissions team will review your application within 5-7 working days.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">
                                <i class="bi bi-bell"></i> Status Update
                            </div>
                            <p>You will be notified via email about the status of your application.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">
                                <i class="bi bi-file-earmark-text"></i> Additional Documents
                            </div>
                            <p>If additional documents are required, we'll contact you through your provided email address.</p>
                        </div>
                    </div>
                </div>
            </div> -->

            <div class="btn-container no-print">
                <button onclick="window.print()" class="btn btn-outline">
                    <i class="bi bi-printer"></i> Print This Page
                </button>
                <?php
                // Retrieve class admission and passport photo data from database
                $db = Database::getInstance();
                $conn = $db->getConnection();
                
                // Default values
                $classAdmission = '';
                $passportPhoto = '';
                
                // Query the database using payment reference to get the application data
                if (!empty($paymentRef)) {
                    $sql = "SELECT applicant_data FROM applications WHERE JSON_EXTRACT(applicant_data, '$.payment_reference') = ? LIMIT 1";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("s", $paymentRef);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result && $result->num_rows > 0) {
                            $application = $result->fetch_assoc();
                            $applicant_data = json_decode($application['applicant_data'], true);
                            
                            // Look for class admission and passport photo fields
                            foreach ($applicant_data as $key => $value) {
                                if (strpos($key, 'field_class_admission') !== false) {
                                    $classAdmission = $value;
                                }
                                if (strpos($key, 'field_passport_photo') !== false) {
                                    $passportPhoto = $value;
                                }
                            }
                        }
                    }
                }
                ?>
                <a href="generate_receipt_pdf.php?type=<?php echo urlencode($applicationType); ?>&app_ref=<?php echo urlencode($applicationRef); ?>&pay_ref=<?php echo urlencode($paymentRef); ?>&first_name=<?php echo urlencode($firstName); ?>&last_name=<?php echo urlencode($lastName); ?>&class_admission=<?php echo urlencode($classAdmission); ?>&photo=<?php echo urlencode($passportPhoto); ?>" class="btn btn-primary">
                    <i class="bi bi-download"></i> Download Receipt
                </a>
                <!-- <a href="#" onclick="addToCalendar()" class="btn btn-outline">
                    <i class="bi bi-calendar-plus"></i> Add to Calendar
                </a> -->
                <a href="../../../index.php" class="btn btn-secondary">
                    <i class="bi bi-house"></i> Return to Homepage
                </a>
            </div>

            <div class="contact-info">
                <h5 class="contact-info-title">Need Help?</h5>
                <div class="contact-methods">
                    <div class="contact-method">
                        <i class="bi bi-envelope"></i>
                        <span>Email</span>
                        <a href="mailto:<?php echo SCHOOL_EMAIL; ?>" class="contact-value"><?php echo SCHOOL_EMAIL; ?></a>
                    </div>
                    <div class="contact-method">
                        <i class="bi bi-telephone"></i>
                        <span>Phone</span>
                        <a href="tel:<?php echo SCHOOL_PHONE; ?>" class="contact-value"><?php echo SCHOOL_PHONE; ?></a>
                    </div>
                    <div class="contact-method">
                        <i class="bi bi-geo-alt"></i>
                        <span>Address</span>
                        <span class="contact-value">Campus Office</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <script>
        // Run confetti animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            const duration = 3000;
            const animationEnd = Date.now() + duration;
            
            function randomInRange(min, max) {
                return Math.random() * (max - min) + min;
            }
            
            function frame() {
                const timeLeft = animationEnd - Date.now();
                
                if (timeLeft <= 0) return;
                
                const particleCount = 50 * (timeLeft / duration);
                
                confetti({
                    particleCount,
                    spread: 80,
                    origin: { y: 0.6 },
                    colors: ['#4361ee', '#3f37c9', '#4cc9f0', '#4ade80'],
                    disableForReducedMotion: true
                });
                
                requestAnimationFrame(frame);
            }
            
            frame();
        });
        
        // Dark mode toggle functionality
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;
        const icon = darkModeToggle.querySelector('i');
        
        // Check for saved preference
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
            icon.classList.remove('bi-moon');
            icon.classList.add('bi-sun');
        }
        
        darkModeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                icon.classList.remove('bi-moon');
                icon.classList.add('bi-sun');
                localStorage.setItem('darkMode', 'enabled');
            } else {
                icon.classList.remove('bi-sun');
                icon.classList.add('bi-moon');
                localStorage.setItem('darkMode', null);
            }
        });
        
        // Add to calendar functionality
        function addToCalendar() {
            const date = new Date();
            date.setDate(date.getDate() + 7); // Add 7 days for expected response
            
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            
            const formattedDate = `${year}${month}${day}`;
            const title = 'Expected Application Response - <?php echo SCHOOL_NAME; ?>';
            const details = 'Follow up on application reference: <?php echo htmlspecialchars($applicationRef); ?>';
            
            // Create Google Calendar link
            const googleCalUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&dates=${formattedDate}/${formattedDate}&details=${encodeURIComponent(details)}`;
            
            // Open in new window
            window.open(googleCalUrl, '_blank');
        }
    </script>
</body>
</html>
