<?php
// Start session
// session_start();

// Include required files
require_once 'backends/database.php';
require_once 'backends/utils.php';
require_once 'backends/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    // Redirect based on role
    switch (getUserRole()) {
        case 'admin':
            redirect('backends/admin/dashboard.php');
            break;
        case 'teacher':
            redirect('backends/teacher/dashboard.php');
            break;
        case 'student':
            redirect('backends/students/dashboard.php');
            break;
        case 'parent':
            redirect('backends/parent/dashboard.php');
            break;
        default:
            // Fallback to home
            redirect('index.php');
    }
}

// Process login form
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $result = login($username, $password, $conn);
        
        if ($result['status']) {
            // Redirect based on role
            switch ($result['data']['role']) {
                case 'admin':
                    redirect('backends/admin/dashboard.php');
                    break;
                case 'teacher':
                    redirect('backends/teacher/dashboard.php');
                    break;
                case 'student':
                    redirect('backends/students/dashboard.php');
                    break;
                case 'parent':
                    redirect('backends/parent/dashboard.php');
                    break;
                default:
                    // Fallback to home
                    redirect('index.php');
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo img {
            max-width: 150px;
        }
        .btn-login {
            width: 100%;
            padding: 10px 0;
            font-weight: bold;
        }
        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <a href="index.php" class="back-home btn btn-outline-secondary">
        <i class="fas fa-home"></i> Back to Home
    </a>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-card">
                    <div class="logo">
                        <img src="images/logo.png" alt="<?php echo APP_NAME; ?>" class="img-fluid">
                        <h4 class="mt-3"><?php echo APP_NAME; ?></h4>
                        <p class="text-muted">Login to your account</p>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fa fa-eye-slash" id="eye-icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                    </form>
                    
                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot your password?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
    </script>
</body>
</html> 