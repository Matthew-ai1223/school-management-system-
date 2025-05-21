<?php
// Initialize required files
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize Auth instance
$auth = new Auth();

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Try to find an admin user
$adminQuery = "SELECT * FROM users WHERE role = 'admin' LIMIT 1";
$adminResult = $conn->query($adminQuery);
$admin = null;

if ($adminResult && $adminResult->num_rows > 0) {
    $admin = $adminResult->fetch_assoc();
    
    // Set session variables using the Auth class structure
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['role'] = 'admin';
    $_SESSION['name'] = ($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '');
    
    // Try to update last login time safely
    // Check if last_login column exists
    $checkQuery = "SHOW COLUMNS FROM users LIKE 'last_login'";
    $columnExists = $conn->query($checkQuery);
    
    if ($columnExists && $columnExists->num_rows > 0) {
        $userId = $admin['id'];
        $conn->query("UPDATE users SET last_login = NOW() WHERE id = '$userId'");
    }
    
    // Immediate redirect to dashboard
    header('Location: dashboard.php');
    exit();
} else {
    // If no admin found, show error
    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>Error</h4>
                <p>No admin user found in the system. Please create an admin user first.</p>
            </div>
            <a href='setup_admin.php' class='btn btn-primary'>Create Admin User</a>
        </div>
    </body>
    </html>";
}
?> 