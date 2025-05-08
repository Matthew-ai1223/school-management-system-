<?php
// Admin Dashboard
session_start();

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';
require_once '../auth.php';

// Require admin login
requireLogin('admin', '../../login.php');

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Get counts for statistics
try {
    // Get total students
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students");
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get total teachers
    $stmt = $conn->query("SELECT COUNT(*) as total FROM teachers");
    $totalTeachers = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get total classes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM classes");
    $totalClasses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get total subjects
    $stmt = $conn->query("SELECT COUNT(*) as total FROM subjects");
    $totalSubjects = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Page title
$pageTitle = "Admin Dashboard";
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
    
    <!-- Custom styles -->
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: #333;
            padding: 0.5rem 1rem;
        }
        
        .sidebar .nav-link.active {
            color: #2470dc;
        }
        
        .sidebar .nav-link:hover {
            color: #007bff;
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        
        .stat-card {
            border-left: 5px solid;
            border-radius: 5px;
        }
        
        .border-left-primary {
            border-left-color: #4e73df;
        }
        
        .border-left-success {
            border-left-color: #1cc88a;
        }
        
        .border-left-info {
            border-left-color: #36b9cc;
        }
        
        .border-left-warning {
            border-left-color: #f6c23e;
        }
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="#"><?php echo APP_NAME; ?></a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <input class="form-control form-control-dark w-100" type="text" placeholder="Search" aria-label="Search">
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="../../logout.php">Sign out</a>
            </div>
        </div>
    </header>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="students.php">
                                <i class="fas fa-user-graduate"></i> Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teachers.php">
                                <i class="fas fa-chalkboard-teacher"></i> Teachers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="classes.php">
                                <i class="fas fa-school"></i> Classes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="subjects.php">
                                <i class="fas fa-book"></i> Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="timetable.php">
                                <i class="fas fa-calendar-alt"></i> Timetable
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="attendance.php">
                                <i class="fas fa-clipboard-list"></i> Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exams.php">
                                <i class="fas fa-file-alt"></i> Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="fees.php">
                                <i class="fas fa-money-bill-wave"></i> Fees
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="announcements.php">
                                <i class="fas fa-bullhorn"></i> Announcements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="messages.php">
                                <i class="fas fa-envelope"></i> Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                    </ul>
                    
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>System</span>
                    </h6>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar"></i> This week
                        </button>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs text-uppercase mb-1 text-primary fw-bold">
                                            Total Students
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalStudents; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs text-uppercase mb-1 text-success fw-bold">
                                            Total Teachers
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalTeachers; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs text-uppercase mb-1 text-info fw-bold">
                                            Total Classes
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalClasses; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-school fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs text-uppercase mb-1 text-warning fw-bold">
                                            Total Subjects
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalSubjects; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-book fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 fw-bold text-primary">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="add_student.php" class="btn btn-primary w-100">
                                            <i class="fas fa-user-plus"></i> Add Student
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="add_teacher.php" class="btn btn-success w-100">
                                            <i class="fas fa-user-plus"></i> Add Teacher
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="add_class.php" class="btn btn-info w-100">
                                            <i class="fas fa-plus"></i> Add Class
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="add_subject.php" class="btn btn-warning w-100">
                                            <i class="fas fa-plus"></i> Add Subject
                                        </a>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="manage_timetable.php" class="btn btn-secondary w-100">
                                            <i class="fas fa-calendar-alt"></i> Manage Timetable
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="manage_fees.php" class="btn btn-danger w-100">
                                            <i class="fas fa-money-bill-wave"></i> Manage Fees
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="create_announcement.php" class="btn btn-dark w-100">
                                            <i class="fas fa-bullhorn"></i> Create Announcement
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="reports.php" class="btn btn-primary w-100">
                                            <i class="fas fa-chart-bar"></i> Generate Reports
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 fw-bold text-primary">System Information</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>System Version:</strong> <?php echo SYSTEM_VERSION; ?></p>
                                <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                                <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                                <p><strong>Current Date & Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom scripts -->
    <script>
        // Add any custom JavaScript here
    </script>
</body>
</html> 