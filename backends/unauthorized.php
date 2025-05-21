<?php
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$currentPath = $_SERVER['REQUEST_URI'];
$isAdmin = strpos($currentPath, '/admin/') !== false;
$isClassTeacher = strpos($currentPath, '/@class_teacher/') !== false;

// Determine return path based on current location
$returnPath = '/';
if ($isAdmin) {
    $returnPath = '/backends/admin/login.php';
} elseif ($isClassTeacher) {
    $returnPath = '/backends/@class_teacher/login.php';
}

// Get user role if available
$userRole = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
        }
        .error-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-icon">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <h1 class="text-danger">Access Denied</h1>
            <p class="lead">You do not have permission to access this resource.</p>
            
            <?php if ($userRole !== 'guest'): ?>
                <div class="alert alert-warning">
                    Your current role is <strong><?php echo htmlspecialchars(ucfirst($userRole)); ?></strong>, 
                    but this page requires different access privileges.
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="<?php echo $returnPath; ?>" class="btn btn-primary">Return to Login</a>
                
                <?php if ($isAdmin && $userRole == 'class_teacher'): ?>
                    <a href="/backends/@class_teacher/dashboard.php" class="btn btn-secondary ms-2">Go to Teacher Dashboard</a>
                <?php elseif ($isClassTeacher && $userRole == 'admin'): ?>
                    <a href="/backends/admin/dashboard.php" class="btn btn-secondary ms-2">Go to Admin Dashboard</a>
                <?php endif; ?>
                
                <a href="/" class="btn btn-outline-secondary ms-2">Home Page</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 