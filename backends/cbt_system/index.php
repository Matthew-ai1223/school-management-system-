<?php
// CBT System Index
session_start();

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';
require_once '../auth.php';

// Require login
requireLogin(['admin', 'teacher'], '../../login.php');

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Page title
$pageTitle = "CBT System";

// Redirect based on role
if (getUserRole() === 'admin') {
    redirect('admin.php');
} elseif (getUserRole() === 'teacher') {
    redirect('teacher.php');
} elseif (getUserRole() === 'student') {
    redirect('../students/cbt.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Computer-Based Testing System</h4>
                    </div>
                    <div class="card-body">
                        <p class="lead">Welcome to the CBT System of <?php echo APP_NAME; ?>.</p>
                        <p>This system allows teachers to create online exams and students to take them.</p>
                        <div class="d-grid gap-2">
                            <a href="admin.php" class="btn btn-primary">
                                <i class="fas fa-user-shield"></i> Admin CBT Panel
                            </a>
                            <a href="teacher.php" class="btn btn-success">
                                <i class="fas fa-chalkboard-teacher"></i> Teacher CBT Panel
                            </a>
                            <a href="../students/cbt.php" class="btn btn-info">
                                <i class="fas fa-user-graduate"></i> Student CBT Panel
                            </a>
                            <a href="../../index.php" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 