<?php
session_start();

// Define credentials
define('VALID_USERNAME', 'ace');
define('VALID_PASSWORD', 'ace');

// Check if user is already logged in
if (!isset($_SESSION['logged_in'])) {
    // Handle login form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if ($username === VALID_USERNAME && $password === VALID_PASSWORD) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error_message = 'Invalid username or password';
        }
    }

    // Show login form if not logged in
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Management Login</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .login-container {
                background-color: white;
                padding: 30px;
                border-radius: 15px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
            }
            .form-group {
                margin-bottom: 20px;
            }
            label {
                display: block;
                margin-bottom: 8px;
                font-weight: bold;
                color: #333;
            }
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                box-sizing: border-box;
                font-size: 16px;
                transition: border-color 0.3s ease;
            }
            input[type="text"]:focus,
            input[type="password"]:focus {
                border-color: #667eea;
                outline: none;
            }
            button {
                background: linear-gradient(45deg, #667eea, #764ba2);
                color: white;
                padding: 12px 20px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                width: 100%;
                font-size: 16px;
                font-weight: 600;
                transition: transform 0.3s ease;
            }
            button:hover {
                transform: translateY(-2px);
            }
            .error {
                color: #dc3545;
                margin-bottom: 20px;
                padding: 10px;
                background-color: #f8d7da;
                border-radius: 5px;
                border: 1px solid #f5c6cb;
            }
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .login-header h2 {
                color: #333;
                margin-bottom: 10px;
            }
            .login-header p {
                color: #666;
                margin: 0;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h2>Payment Management</h2>
                <p>Access all payment systems</p>
            </div>
            <?php if (isset($error_message)): ?>
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// If we get here, user is logged in
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Payment Management - ACE College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .logout-btn {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
            color: white;
        }
        .payment-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .payment-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 2rem;
            text-align: center;
        }
        .card-body {
            padding: 2.5rem;
        }
        .btn-payment {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 50px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
            margin: 1rem 0;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-payment:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-online-payment {
            background: linear-gradient(45deg, #11998e, #38ef7d);
        }
        .btn-online-payment:hover {
            box-shadow: 0 10px 25px rgba(17, 153, 142, 0.4);
        }
        .btn-lesson-payment {
            background: linear-gradient(45deg, #fc466b, #3f5efb);
        }
        .btn-lesson-payment:hover {
            box-shadow: 0 10px 25px rgba(252, 70, 107, 0.4);
        }
        .btn-school-payment {
            background: linear-gradient(45deg, #f093fb, #f5576c);
        }
        .btn-school-payment:hover {
            box-shadow: 0 10px 25px rgba(240, 147, 251, 0.4);
        }
        .payment-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
        .stats-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 15px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            margin-bottom: 1rem;
        }
        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        .back-btn {
            background: linear-gradient(45deg, #6c757d, #495057);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-credit-card me-3"></i>
                        All Payment Management
                    </h1>
                    <p class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! Manage all payment systems from one place.</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="https://acecollege.ng/backends/admin/login.php" class="back-btn">
            <i class="fas fa-arrow-left me-2"></i>
            Back to Main Dashboard
        </a>

        <!-- Statistics Section -->
        <div class="stats-section">
            <h3 class="text-center mb-4">
                <i class="fas fa-chart-bar me-2"></i>
                Payment System Overview
            </h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3>3</h3>
                        <p>Payment Systems</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3>24/7</h3>
                        <p>Access Available</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3>100%</h3>
                        <p>Secure Access</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Systems -->
        <div class="row">
            <!-- Online Payment System -->
            <div class="col-lg-4 col-md-6">
                <div class="payment-card">
                    <div class="card-header">
                        <div class="payment-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <h2 class="mb-0">Online Payment System</h2>
                        <p class="mb-0">School & Tutorial Online Payments</p>
                    </div>
                    <div class="card-body text-center">
                        <p class="text-muted mb-4">Manage online payment verifications, view payment history, and approve pending payments for both school fees and tutorial payments.</p>
                        
                        <a href="backends/g_p/admin.php" class="btn btn-payment btn-online-payment">
                            <i class="fas fa-arrow-right me-2"></i>
                            Access Online Payment Admin
                        </a>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Handles both school and tutorial online payments
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lesson Payment System -->
            <div class="col-lg-4 col-md-6">
                <div class="payment-card">
                    <div class="card-header">
                        <div class="payment-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h2 class="mb-0">Lesson Payment System</h2>
                        <p class="mb-0">Tutorial & Lesson Payment Management</p>
                    </div>
                    <div class="card-body text-center">
                        <p class="text-muted mb-4">Manage lesson payments, view tutorial payment reports, approve cash payments, and generate payment receipts for tutorial services.</p>
                        
                        <a href="backends/lesson/admin/payment/admin_payment_report.php" class="btn btn-payment btn-lesson-payment">
                            <i class="fas fa-arrow-right me-2"></i>
                            Access Lesson Payment Admin
                        </a>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Manages tutorial and lesson payment approvals
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- School Payment System -->
            <div class="col-lg-4 col-md-6">
                <div class="payment-card">
                    <div class="card-header">
                        <div class="payment-icon">
                            <i class="fas fa-school"></i>
                        </div>
                        <h2 class="mb-0">School Payment System</h2>
                        <p class="mb-0">School Fees & Registration Management</p>
                    </div>
                    <div class="card-body text-center">
                        <p class="text-muted mb-4">Manage school fees, view payment history, approve cash payments, and generate comprehensive payment reports for school operations.</p>
                        
                        <a href="backends/school_paymente/admin_payment_history.php" class="btn btn-payment btn-school-payment">
                            <i class="fas fa-arrow-right me-2"></i>
                            Access School Payment Admin
                        </a>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Handles school fees and registration payments
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Access Section -->
        <div class="payment-card">
            <div class="card-header">
                <h2 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Quick Access
                </h2>
                <p class="mb-0">Direct links to frequently used features</p>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <a href="backends/g_p/index.php" class="btn btn-outline-primary btn-lg w-100 mb-3">
                            <i class="fas fa-credit-card me-2"></i>
                            Make Online Payment
                        </a>
                        <p class="text-muted small">For students and parents to make payments</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <a href="https://acecollege.ng/backends/lesson/admin/dashboard.php"  class="btn btn-outline-success btn-lg w-100 mb-3">
                            <i class="fas fa-user-graduate me-2"></i>
                            Tutorial Student Admin              
                        </a>
                        <p class="text-muted small">Access student dashboard and services</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <a href="generial_dashboard.php" class="btn btn-outline-info btn-lg w-100 mb-3">
                            <i class="fas fa-home me-2"></i>
                            Bursar Dashboard
                        </a>
                        <p class="text-muted small">Return to the Bursar Dashboard</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php
    // Handle logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    ?>
</body>
</html>
