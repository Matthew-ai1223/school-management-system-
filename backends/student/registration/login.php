<?php
require_once '../../config.php';
require_once '../../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

$error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $registration_number = $_POST['registration_number'] ?? '';
    
    // Validate input
    if (empty($registration_number)) {
        $error = 'Registration number is required';
    } else {
        // Connect to database
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Prepare and execute query
        $stmt = $conn->prepare("SELECT id, first_name, last_name, registration_number, status FROM students WHERE registration_number = ?");
        $stmt->bind_param("s", $registration_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $student = $result->fetch_assoc();
            
            // Check if student status is registered
            if ($student['status'] !== 'registered') {
                $error = 'Your registration is still pending approval';
            } else {
                // Set session variables
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['registration_number'] = $student['registration_number'];
                
                // Redirect to dashboard
                header('Location: student_dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid registration number';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:300,400" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a237e;    /* Deep Blue */
            --secondary-color: #0d47a1;  /* Medium Blue */
            --accent-color: #2962ff;     /* Bright Blue */
            --light-bg: #e3f2fd;         /* Light Blue Background */
            --text-color: #333;
            --white: #fff;
            --border-radius: 8px;
        }

        body {
            background: linear-gradient(135deg, #f5f8ff 0%, #e8eeff 100%);
            font-family: 'Source Sans Pro', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            padding: 20px;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 40px;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .school-logo {
            width: 100px;
            height: 70px;
            margin-bottom: 15px;
        }

        .school-name {
            color: var(--primary-color);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .login-title {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text-color);
        }

        .form-control {
            border-radius: var(--border-radius);
            border: 1px solid #e0e0e0;
            padding: 12px 15px;
            height: auto;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(41, 98, 255, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            border-radius: 50px;
            padding: 12px 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(41, 98, 255, 0.3);
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(41, 98, 255, 0.4);
        }

        .homepage-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .homepage-link:hover {
            color: var(--accent-color);
            text-decoration: none;
        }

        .homepage-link i {
            margin-right: 5px;
        }

        .registration-link {
            display: inline-block;
            color: var(--accent-color);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .registration-link:hover {
            color: var(--primary-color);
            text-decoration: none;
            transform: translateY(-2px);
        }

        .alert-danger {
            background-color: #fff0f0;
            color: #e53935;
            border-color: #ffcdd2;
            border-radius: var(--border-radius);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <img src="../../../images/logo.png" alt="<?php echo SCHOOL_NAME; ?> Logo" class="school-logo">
                <h2 class="school-name"><?php echo SCHOOL_NAME; ?></h2>
                <p class="login-title">Student Portal Login</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="registration_number">Registration Number</label>
                    <input type="text" class="form-control" id="registration_number" name="registration_number" placeholder="Enter your registration number" required>
                    <small class="form-text text-muted">Example: 2023KID0001 or 2023COL0001</small>
                </div>
                
                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </button>
            </form>
            
            <div class="text-center">
                <div class="mt-4 mb-3">
                    <span class="text-muted">New student?</span>
                    <a href="reg_form.php" class="registration-link">
                        <i class="fas fa-user-plus mr-1"></i> Register Now
                    </a>
                </div>
                
                <a href="../../../index.php" class="homepage-link">
                    <i class="fas fa-home"></i> Back to Homepage
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
