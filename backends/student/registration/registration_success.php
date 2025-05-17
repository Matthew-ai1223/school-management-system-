<?php
require_once '../../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if success message exists
$successMessage = $_SESSION['success_message'] ?? 'Registration completed';
unset($_SESSION['success_message']); // Clear message after displaying

// Get registration number from URL
$registrationNumber = isset($_GET['reg']) ? htmlspecialchars($_GET['reg']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Success - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --success-bg: #d1fae5;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background-color: #10b981;
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .success-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 1.5rem;
            background-color: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 0.5rem 1rem rgba(16, 185, 129, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
        
        .registration-number {
            background-color: #f0fdfa;
            border: 2px dashed #10b981;
            border-radius: 10px;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: center;
            position: relative;
        }
        
        .reg-label {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background-color: white;
            padding: 0 10px;
            font-size: 0.85rem;
            color: #10b981;
            font-weight: 600;
        }
        
        .copy-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #10b981;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.2s;
            padding: 5px;
        }
        
        .copy-btn:hover {
            color: var(--primary-color);
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
        
        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
            border-radius: 7px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
            transform: translateY(-2px);
        }
        
        .alert-success {
            background-color: var(--success-bg);
            border: none;
            color: #047857;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .next-steps {
            margin-top: 2rem;
            padding: 1.5rem;
            background-color: #f9fafb;
            border-radius: 10px;
        }
        
        .step-item {
            display: flex;
            margin-bottom: 1rem;
            align-items: flex-start;
        }
        
        .step-number {
            width: 28px;
            height: 28px;
            background-color: #10b981;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background-color: #f0f;
            opacity: 0;
            animation: confetti 5s ease-in-out infinite;
            z-index: -1;
        }
        
        @keyframes confetti {
            0% {
                transform: translateY(0) rotate(0);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
            }
            .success-icon {
                animation: none !important;
                box-shadow: none !important;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            .card-header h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Confetti Animation -->
    <div id="confetti-container"></div>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card">
                    <div class="card-header text-center">
                        <h3 class="mb-0">Registration Successful</h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        
                        <div class="text-center mb-4">
                            <h4 class="mb-3">Thank you for your registration!</h4>
                            <p class="lead"><?php echo htmlspecialchars($successMessage); ?></p>
                        </div>
                        
                        <?php if (!empty($registrationNumber)): ?>
                        <div class="registration-number">
                            <span class="reg-label">Registration Number</span>
                            <h5 class="mb-0" id="regNumber"><?php echo $registrationNumber; ?></h5>
                            <button class="copy-btn" onclick="copyRegistrationNumber()" title="Copy to clipboard">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                        <p class="text-center text-muted small">Please save this number for future reference</p>
                        <?php endif; ?>
                        
                        <div class="alert alert-success mt-4">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-info-circle fa-2x"></i>
                                </div>
                                <div>
                                    <h5 class="alert-heading">Application Under Review</h5>
                                    <p>Your registration has been submitted successfully. Our admissions team will review your application within 24 hours.</p>
                                    <p>Copy your registration number and use it to check your application status on the portal/login page.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4 no-print">
                            <a href="../../../../index.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Back to Home
                            </a>
                            <?php if (!empty($registrationNumber)): ?>
                            <button class="btn btn-outline-secondary ms-2" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print This Page
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Copy registration number function
        function copyRegistrationNumber() {
            const regNumber = document.getElementById('regNumber');
            const textArea = document.createElement('textarea');
            textArea.value = regNumber.textContent;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            // Show tooltip feedback
            const copyBtn = document.querySelector('.copy-btn');
            const originalHTML = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i>';
            copyBtn.style.color = '#047857';
            
            setTimeout(() => {
                copyBtn.innerHTML = originalHTML;
                copyBtn.style.color = '';
            }, 2000);
        }
        
        // Create confetti animation
        function createConfetti() {
            const colors = ['#4cc9f0', '#4361ee', '#3f37c9', '#f72585', '#10b981'];
            const container = document.getElementById('confetti-container');
            
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = Math.random() * 10 + 5 + 'px';
                confetti.style.height = Math.random() * 10 + 5 + 'px';
                confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                container.appendChild(confetti);
            }
        }
        
        // Initialize confetti on load
        window.addEventListener('load', createConfetti);
    </script>
</body>
</html> 