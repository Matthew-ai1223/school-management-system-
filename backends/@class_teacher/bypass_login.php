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

// Get list of teachers and classes
$teachersQuery = "SELECT t.*, u.username, u.id as user_id FROM teachers t 
                 JOIN users u ON t.user_id = u.id 
                 WHERE u.role = 'class_teacher' AND u.status = 'active'
                 LIMIT 1";
$teachersResult = $conn->query($teachersQuery);

if ($teachersResult && $teachersResult->num_rows > 0) {
    $teacher = $teachersResult->fetch_assoc();
    
    // Get a class for this teacher
    $classQuery = "SELECT ct.class_name FROM class_teachers ct 
                  WHERE ct.teacher_id = '{$teacher['id']}' AND ct.is_active = 1
                  LIMIT 1";
    $classResult = $conn->query($classQuery);
    
    // If no assigned class, try to get any available class
    if (!$classResult || $classResult->num_rows == 0) {
        $classesQuery = "SELECT DISTINCT class FROM students LIMIT 1";
        $classesResult = $conn->query($classesQuery);
        $class = $classesResult->fetch_assoc();
        $className = $class['class'] ?? 'JSS 1A';
    } else {
        $classData = $classResult->fetch_assoc();
        $className = $classData['class_name'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $teacher['user_id'];
    $_SESSION['username'] = $teacher['username'];
    $_SESSION['role'] = 'class_teacher';
    $_SESSION['name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
    $_SESSION['teacher_id'] = $teacher['id'];
    $_SESSION['class_name'] = $className;
    
    // Try to update last login time safely
    // Check if last_login column exists
    $checkQuery = "SHOW COLUMNS FROM users LIKE 'last_login'";
    $columnExists = $conn->query($checkQuery);
    
    if ($columnExists && $columnExists->num_rows > 0) {
        $userId = $teacher['user_id'];
        $conn->query("UPDATE users SET last_login = NOW() WHERE id = '$userId'");
    }
    
    // Immediate redirect to dashboard
    header('Location: dashboard.php');
    exit();
} else {
    // If no teacher found, show error
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
                <p>No class teacher found in the system. Please create a class teacher user first.</p>
            </div>
            <a href='../admin/manage_teachers.php' class='btn btn-primary'>Manage Teachers</a>
        </div>
    </body>
    </html>";
}
?> 