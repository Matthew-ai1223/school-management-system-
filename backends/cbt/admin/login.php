<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['teacher_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$auth = new Auth();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = trim($_POST['teacher_id'] ?? '');

    if (empty($teacher_id)) {
        $error = 'Please enter your Teacher ID.';
    } else {
        if ($auth->loginWithTeacherId($teacher_id)) {
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid Teacher ID. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 90%;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .school-logo {
            width: 100px;
            height: auto;
            margin-bottom: 1rem;
        }
        .login-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #2c3e50;
        }
        .input-group-text {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <h1 class="h3 text-dark">ACE COLLEGE</h1>
            <p class="text-muted">Computer-Based Test (CBT) System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label for="teacher_id" class="form-label">
                    <i class="fas fa-id-card me-2"></i>Teacher ID
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="password" 
                           class="form-control" 
                           id="teacher_id" 
                           name="teacher_id" 
                           placeholder="Enter your Teacher ID"
                           required 
                           autofocus>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
                <a href="../../index.php" class="btn btn-light">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
            </div>
        </form>

        <div class="text-center mt-4">
            <p class="text-muted mb-0">
                <small>&copy; <?php echo date('Y'); ?> ACE COLLEGE. All rights reserved.</small>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 