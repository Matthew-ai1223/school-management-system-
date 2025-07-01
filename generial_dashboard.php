<?php
session_start();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Define credentials
$default_password = 'acecollege001';
define('VALID_USERNAME', 'bursar001');

// Read password from file if it exists
$password_file = __DIR__ . '/dashboard_password.txt';
if (file_exists($password_file)) {
    $password_from_file = trim(file_get_contents($password_file));
    define('VALID_PASSWORD', $password_from_file);
} else {
    define('VALID_PASSWORD', $default_password);
}

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
        <title>Login</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .login-container {
                background-color: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-sizing: border-box;
            }
            button {
                background-color: #4CAF50;
                color: white;
                padding: 10px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                width: 100%;
            }
            button:hover {
                background-color: #45a049;
            }
            .error {
                color: red;
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Login</h2>
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
    <title>Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logout-btn {
            background-color: #f44336;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .logout-btn:hover {
            background-color: #da190b;
        }
        .content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .button-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .dashboard-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            color: white;
            font-size: 1.1em;
            min-height: 120px;
            text-decoration: none;
        }
        .dashboard-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .dashboard-btn i {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .btn-student {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }
        .btn-teacher {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }
        .btn-admin {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
        }
        .btn-payment-admin {
            background: linear-gradient(135deg, #FF5722, #E64A19);
        }
        .user-guide {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .guide-section {
            margin-bottom: 20px;
            padding: 15px;
            border-left: 4px solid #667eea;
            background-color: #f8f9fa;
            border-radius: 0 8px 8px 0;
            display: none; /* Hide by default */
        }
        .guide-section h4 {
            color: #667eea;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .guide-section h4 i {
            margin-right: 10px;
        }
        .guide-section ul {
            margin: 0;
            padding-left: 20px;
        }
        .guide-section li {
            margin-bottom: 5px;
            color: #555;
        }
        .guide-toggle {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .guide-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .guide-content {
            display: none;
        }
        .guide-content.show {
            display: block;
        }
    </style>
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <a href="?logout=1" class="logout-btn">Logout</a>
    </div>

    <div class="content">
        <h2>Dashboard</h2>
        <div class="button-container">
            <a href="https://acecollege.ng/backends/lesson/admin/dashboard.php" class="dashboard-btn btn-student">
                <i class="fas fa-user-graduate"></i>
                Tutorial Student Portal
            </a>
           <a href="backends/school_paymente/buxer_dashbord.php" class="dashboard-btn btn-teacher">
                <i class="fas fa-school"></i>
                School Bursar Portal
            </a>
            <a href="backends\lesson\admin\payment\payment_report.php" class="dashboard-btn btn-admin">
                <i class="fas fa-book"></i>
                Lesson Bursar Portal
            </a> 
            <a href="backends/g_p/admin.php" class="dashboard-btn btn-payment-admin">
                <i class="fas fa-credit-card"></i>
                School and Tutorial Online Payment
            </a>
        </div>
        <div class="user-guide">
            <h3>User Guide</h3>
            <div class="guide-section">
                <h4><i class="fas fa-user-graduate"></i> Tutorial Student Portal</h4>
                <ul>
                    <li>Access the portal to manage your academic records and progress.</li>
                    <li>View your course schedule and assignments.</li>
                    <li>Submit assignments and participate in discussions.</li>
                </ul>
            </div>
            <div class="guide-section">
                <h4><i class="fas fa-school"></i> School Bursar Portal</h4>
                <ul>
                    <li>Access the portal to manage school fees and payments.</li>
                    <li>View and manage student accounts.</li>
                    <li>Generate and manage invoices.</li>
                </ul>
            </div>
            <div class="guide-section">
                <h4><i class="fas fa-book"></i> Lesson Bursar Portal</h4>
                <ul>
                    <li>Access the portal to manage lesson fees and payments.</li>
                    <li>View and manage lesson schedules.</li>
                    <li>Generate and manage invoices.</li>
                </ul>
            </div>
            <div class="guide-section">
                <h4><i class="fas fa-credit-card"></i> School and Tutorial Online Payment</h4>
                <ul>
                    <li>Access the portal to make payments for school fees and tutorials.</li>
                    <li>View payment history and manage payment methods.</li>
                    <li>Receive and manage payment notifications.</li>
                </ul>
            </div>
            <button class="guide-toggle">Show Guide</button>
        </div>
        <div class="guide-content">
            <!-- Guide content will be dynamically added here -->
        </div>
    </div>

    <?php
    ?>
    
    <script>
        // Toggle user guide visibility
        document.addEventListener('DOMContentLoaded', function() {
            const guideToggle = document.querySelector('.guide-toggle');
            const guideSections = document.querySelectorAll('.guide-section');
            
            guideToggle.addEventListener('click', function() {
                const isVisible = guideSections[0].style.display !== 'none';
                
                guideSections.forEach(function(section) {
                    if (isVisible) {
                        section.style.display = 'none';
                    } else {
                        section.style.display = 'block';
                    }
                });
                
                if (isVisible) {
                    guideToggle.textContent = 'Show Guide';
                } else {
                    guideToggle.textContent = 'Hide Guide';
                }
            });
        });
    </script>
</body>
</html>
