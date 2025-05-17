<?php
require_once '../../config.php';
require_once '../../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

// Get student information
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$registration_number = $_SESSION['registration_number'];

// Connect to database
$db = Database::getInstance();
$conn = $db->getConnection();

// Get student details
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Initialize student photo from database
$student_photo = '';
$photo_columns = ['photo', 'image', 'profile_picture', 'passport', 'student_photo'];
foreach ($photo_columns as $column) {
    if (isset($student[$column]) && !empty($student[$column])) {
        $student_photo = $student[$column];
        break;
    }
}

// Check various possible photo locations
$photo_found = false;
$photo_path = '';
$possible_paths = [];

// Path 1: Student files directory with stored filename
if (!empty($student_photo)) {
    $possible_paths[] = '../uploads/student_files/' . $student_photo;
}

// Path 2: Using registration number
$safe_registration = str_replace(['/', ' '], '_', $registration_number);
$possible_paths[] = '../uploads/student_files/' . $safe_registration . '.jpg';
$possible_paths[] = '../uploads/student_files/' . $safe_registration . '.png';

// Path 3: Check profile path 
$possible_paths[] = '../../../uploads/student_passports/' . $safe_registration . '.jpg';
$possible_paths[] = '../../../uploads/student_passports/' . $safe_registration . '.png';

// Path 4: Absolute paths if needed
$base_dir = realpath(dirname(__FILE__) . '/../../..');
$possible_paths[] = $base_dir . '/uploads/student_files/' . $safe_registration . '.jpg';
$possible_paths[] = $base_dir . '/uploads/student_passports/' . $safe_registration . '.jpg';

// Check each path
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $photo_found = true;
        $photo_path = $path;
        break;
    }
}

// Set final photo URL for use in HTML
$photo_url = '';
if ($photo_found) {
    // Convert to a web-accessible URL based on which path was found
    if (strpos($photo_path, 'uploads/student_files/') !== false) {
        $photo_url = '../uploads/student_files/' . basename($photo_path);
    } elseif (strpos($photo_path, 'uploads/student_passports/') !== false) {
        $photo_url = '../../../uploads/student_passports/' . basename($photo_path);
    } else {
        // Default - use the path as is
        $photo_url = $photo_path;
    }
}

// Debug image paths
$debug_image_info = [];
$debug_image_info[] = "Photo found: " . ($photo_found ? 'Yes' : 'No');
if ($photo_found) {
    $debug_image_info[] = "Found at: " . $photo_path;
    $debug_image_info[] = "Using URL: " . $photo_url;
}
$debug_image_info[] = "Checked paths:";
foreach ($possible_paths as $i => $path) {
    $debug_image_info[] = ($i+1) . ". " . $path . " - " . (file_exists($path) ? 'Exists' : 'Not found');
}

// Store debug info
$_SESSION['image_debug'] = implode('<br>', $debug_image_info);

// Get student class/level information
$student_class = '';
$class_columns = ['class', 'level', 'grade', 'student_class'];
foreach ($class_columns as $column) {
    if (isset($student[$column]) && !empty($student[$column])) {
        $student_class = $student[$column];
        break;
    }
}

// Determine application type from registration number
$application_type = '';
if (strpos($registration_number, 'KID') !== false) {
    $application_type = 'kiddies';
} elseif (strpos($registration_number, 'COL') !== false) {
    $application_type = 'college';
}

// Get exam results if any
$exam_results = [];
$exams_stmt = $conn->prepare("SELECT * FROM exam_results WHERE student_id = ? ORDER BY exam_date DESC");
$exams_stmt->bind_param("i", $student_id);
$exams_stmt->execute();
$exams_result = $exams_stmt->get_result();
if ($exams_result && $exams_result->num_rows > 0) {
    while ($row = $exams_result->fetch_assoc()) {
        $exam_results[] = $row;
    }
}

// Get payment history
$payments = [];
$payments_stmt = $conn->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
$payments_stmt->bind_param("i", $student_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
if ($payments_result && $payments_result->num_rows > 0) {
    while ($row = $payments_result->fetch_assoc()) {
        $payments[] = $row;
    }
}

// Function to format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo SCHOOL_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:300,400,700" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.css">
    
    <style>
        :root {
            --primary-color: #1a237e;    /* Deep Blue */
            --primary-light: #534bae;    /* Lighter Primary */
            --primary-dark: #000051;     /* Darker Primary */
            --secondary-color: #0d47a1;  /* Medium Blue */
            --accent-color: #2962ff;     /* Bright Blue */
            --accent-light: #768fff;     /* Lighter Accent */
            --light-bg: #e3f2fd;         /* Light Blue Background */
            --text-color: #333;          /* Main Text */
            --text-muted: #6c757d;       /* Muted Text */
            --text-light: #f8f9fa;       /* Light Text */
            --success: #43a047;          /* Success Color */
            --warning: #ffb300;          /* Warning Color */
            --danger: #e53935;           /* Danger Color */
            --white: #fff;               /* White */
            --border-radius: 8px;        /* Border Radius */
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* Card Shadow */
            --sidebar-width: 250px;      /* Sidebar Width */
        }

        html, body {
            height: 100%;
            background-color: #f8f9fa;
            font-family: 'Source Sans Pro', Arial, sans-serif;
            color: var(--text-color);
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(to bottom, var(--primary-color), var(--primary-dark));
            color: var(--white);
            height: 100vh;
            width: var(--sidebar-width);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            padding: 0;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .school-logo {
            width: 70px;
            height: 50px;
            margin-bottom: 15px;
        }

        .sidebar-header h4 {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            color: var(--white);
            font-family: 'Roboto Slab', serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.3s;
            position: relative;
            margin: 5px 0;
        }

        .sidebar .nav-link:hover {
            color: var(--white);
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: var(--accent-light);
        }

        .sidebar .nav-link.active {
            color: var(--white);
            background: linear-gradient(to right, rgba(41, 98, 255, 0.5), rgba(0, 0, 0, 0));
            border-left-color: var(--accent-color);
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
        }

        .logout-container {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 0 20px;
        }

        .logout-btn {
            background: rgba(229, 57, 53, 0.8);
            border: none;
            width: 100%;
            padding: 12px 0;
            border-radius: var(--border-radius);
            transition: all 0.3s;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .logout-btn:hover {
            background: var(--danger);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(229, 57, 53, 0.4);
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px 30px;
            min-height: 100vh;
            transition: all 0.3s;
        }

        /* Student Info Card */
        .student-info {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .student-info::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }

        .student-info h3 {
            font-family: 'Roboto Slab', serif;
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        .student-info p {
            margin-bottom: 8px;
            font-size: 1.05rem;
            font-weight: 500;
        }

        .student-info strong {
            font-weight: 700;
            display: inline-block;
            min-width: 140px;
        }

        .last-login {
            text-align: right;
            font-style: italic;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        /* Dashboard Cards */
        .dashboard-card {
            background-color: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            height: 100%;
            border-top: 4px solid transparent;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .dashboard-card.card-primary {
            border-top-color: var(--primary-color);
        }

        .dashboard-card.card-accent {
            border-top-color: var(--accent-color);
        }

        .dashboard-card.card-success {
            border-top-color: var(--success);
        }

        .dashboard-card h4 {
            color: var(--text-color);
            font-weight: 700;
            margin-bottom: 20px;
            font-family: 'Roboto Slab', serif;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
        }

        .dashboard-card h4 i {
            margin-right: 10px;
            color: var(--accent-color);
        }

        .card-icon {
            font-size: 50px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .dashboard-card .h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
            color: var(--primary-color);
        }

        .dashboard-card p {
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 0;
        }

        /* Alert Styles */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 15px 20px;
        }

        .alert-info {
            background-color: rgba(227, 242, 253, 0.7);
            color: var(--primary-dark);
            border-left: 4px solid var(--accent-color);
        }

        .alert h5 {
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        /* Table Styles */
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 25px;
            margin-bottom: 30px;
        }

        .table-responsive {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: rgba(227, 242, 253, 0.4);
            border-bottom: 2px solid var(--primary-light);
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
        }

        .table td {
            vertical-align: middle;
            padding: 15px;
            font-size: 1rem;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .badge {
            padding: 6px 12px;
            font-weight: 600;
            font-size: 0.8rem;
            border-radius: 30px;
        }

        .badge-success {
            background-color: var(--success);
        }

        .badge-warning {
            background-color: var(--warning);
            color: #212529;
        }

        .badge-danger {
            background-color: var(--danger);
        }

        /* Tab Content Transitions */
        .tab-content > .tab-pane {
            transition: all 0.3s ease;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Adjustments */
        @media (max-width: 991.98px) {
            .sidebar {
                width: 0;
                padding: 0;
                overflow: hidden;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .show-sidebar .sidebar {
                width: var(--sidebar-width);
                padding: 0;
            }
            
            .show-sidebar .main-content {
                margin-left: var(--sidebar-width);
            }
            
            .nav-toggle {
                display: block !important;
            }
            
            .student-info {
                padding: 20px;
            }
            
            .dashboard-card {
                padding: 20px;
            }
        }

        @media (max-width: 767.98px) {
            .student-info .col-md-6:last-child {
                text-align: left;
                margin-top: 10px;
            }
            
            .last-login {
                text-align: left;
            }
            
            .card-icon {
                font-size: 40px;
                margin-bottom: 15px;
            }
            
            .dashboard-card .h3 {
                font-size: 2rem;
            }
        }

        /* Navbar Toggle Button */
        .nav-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 200;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: none;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: all 0.3s;
        }

        .nav-toggle:hover {
            background-color: var(--accent-color);
        }

        /* Profile Section Styles */
        .profile-stats {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .stat-card {
            flex: 1;
            min-width: 200px;
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 10px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 30px;
            margin-bottom: 10px;
            color: var(--accent-color);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Calendar Styles */
        .calendar-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--card-shadow);
        }

        .fc-header-toolbar {
            margin-bottom: 1.5em !important;
        }

        .fc-toolbar h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .fc-button-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .fc-button-primary:hover {
            background-color: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
        }

        .fc-day-today {
            background-color: var(--light-bg) !important;
        }

        .fc-event {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .passport-container {
            position: relative;
            display: inline-block;
        }
        
        .change-photo-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .change-photo-btn:hover {
            background-color: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }
        
        .profile-stats {
            flex-direction: row;
            justify-content: center;
        }
        
        #imagePreview img {
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <!-- Toggle Button for Responsive Menu -->
    <button class="nav-toggle" id="navToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="container-fluid" id="app">
        <div class="row">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <img src="../../../images/logo.png" alt="<?php echo SCHOOL_NAME; ?> Logo" class="school-logo">
                    <h4><?php echo SCHOOL_NAME; ?></h4>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#dashboard" data-toggle="tab">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#profile" data-toggle="tab">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#exams" data-toggle="tab">
                            <i class="fas fa-file-alt"></i> Exams & Results
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#payments" data-toggle="tab">
                            <i class="fas fa-money-bill-wave"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#calendar" data-toggle="tab">
                            <i class="fas fa-calendar-alt"></i> Calendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#announcements" data-toggle="tab">
                            <i class="fas fa-bullhorn"></i> Announcements
                        </a>
                    </li>
                </ul>
                <div class="logout-container">
                    <a href="logout.php" class="btn btn-danger logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                 <div class="student-info">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center mb-3 mb-md-0">
                            <?php if ($photo_found && !empty($photo_url)): ?>
                                <img src="<?php echo htmlspecialchars($photo_url); ?>"
                                     alt="Student Photo" class="img-fluid rounded-circle student-photo"
                                     style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #4361ee;">
                                <!-- Hidden debug info -->
                                <small class="d-none">Image: <?php echo basename($photo_path); ?></small>
                            <?php else: ?>
                                <div class="default-avatar rounded-circle d-flex align-items-center justify-content-center"
                                     style="width: 120px; height: 120px; background-color: #e9ecef; margin: 0 auto; font-size: 2.5rem; color: #6c757d;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <!-- Hidden debug info -->
                                <small class="d-none">No image found. Checked columns: <?php echo implode(', ', $photo_columns); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h3>Welcome, <?php echo htmlspecialchars($student_name); ?></h3>
                            <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($registration_number); ?></p>
                            <p><strong>Category:</strong> <?php echo ucfirst($application_type); ?> Student</p>
                            <p><strong>Class/Level:</strong> <?php echo !empty($student_class) ? htmlspecialchars($student_class) : 'Not assigned'; ?></p>
                        </div>
                        <div class="col-md-4 text-md-right">
                            <div class="card bg-light" style="color: #4361ee;">
                                <div class="card-body p-3">
                                    <p class="mb-2"><i class="far fa-clock mr-2" ></i> Last Login:</p>
                                    <p class="font-weight-bold mb-0" ><?php echo date('M d, Y H:i:s'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['image_debug'])): ?>
                <!-- <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                    <h5><i class="fas fa-info-circle"></i> Image Debug Information</h5>
                    <div><?php echo $_SESSION['image_debug']; ?></div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div> -->
                <?php endif; ?>
                
                <div class="tab-content">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade show active" id="dashboard">
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="dashboard-card card-primary">
                                    <div class="text-center">
                                        <i class="fas fa-file-alt card-icon"></i>
                                        <h4>Exams</h4>
                                        <p class="h3"><?php echo count($exam_results); ?></p>
                                        <p>Total exams taken</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="dashboard-card card-accent">
                                    <div class="text-center">
                                        <i class="fas fa-money-bill-wave card-icon"></i>
                                        <h4>Payments</h4>
                                        <p class="h3"><?php echo count($payments); ?></p>
                                        <p>Total payments made</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="dashboard-card card-success">
                                    <div class="text-center">
                                        <i class="fas fa-calendar-alt card-icon"></i>
                                        <h4>School Calendar</h4>
                                        <p>View upcoming events and academic calendar</p>
                                        <a href="#calendar" data-toggle="tab" class="btn btn-outline-primary mt-3">View Calendar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-4">
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-chart-bar"></i> Academic Progress</h4>
                                    <div>
                                        <canvas id="academicChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-bell"></i> Recent Announcements</h4>
                                    <div class="announcement-list">
                                        <div class="alert alert-info">
                                            <h5>Welcome to the New Session</h5>
                                            <p>The new academic session has begun. Please ensure all fees are paid before the deadline.</p>
                                            <small class="text-muted"><i class="far fa-clock mr-1"></i> Today</small>
                                        </div>
                                        <a href="#announcements" data-toggle="tab" class="btn btn-sm btn-link">View All Announcements</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-tasks"></i> Quick Actions</h4>
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <a href="#profile" data-toggle="tab" class="btn btn-light btn-block py-3">
                                                <i class="fas fa-user-edit mb-2 d-block" style="font-size: 24px;"></i>
                                                View Profile
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <a href="#exams" data-toggle="tab" class="btn btn-light btn-block py-3">
                                                <i class="fas fa-file-alt mb-2 d-block" style="font-size: 24px;"></i>
                                                Check Results
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <a href="#payments" data-toggle="tab" class="btn btn-light btn-block py-3">
                                                <i class="fas fa-credit-card mb-2 d-block" style="font-size: 24px;"></i>
                                                View Payments
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <a href="#" class="btn btn-light btn-block py-3">
                                                <i class="fas fa-download mb-2 d-block" style="font-size: 24px;"></i>
                                                Download Timetable
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Tab -->
                    <div class="tab-pane fade" id="profile">
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['debug_info'])): ?>
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <strong>Debug Info:</strong> <?php echo $_SESSION['debug_info']; unset($_SESSION['debug_info']); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['debug_path'])): ?>
                            <!-- <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <strong>Path Info:</strong> <?php echo $_SESSION['debug_path']; unset($_SESSION['debug_path']); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div> -->
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="dashboard-card text-center">
                                    <div class="passport-container mb-4">
                                        <?php 
                                        // Sanitize registration number for file path
                                        $safe_registration = str_replace('/', '_', $registration_number);
                                        $passport_path = '../../../uploads/student_passports/' . $safe_registration . '.jpg';
                                        $default_avatar = '../../../images/avatar-placeholder.png';
                                        $display_image = file_exists($passport_path) ? $passport_path : $default_avatar;
                                        ?>
                                        <div class="position-relative d-inline-block">
                                            <img src="<?php echo $display_image; ?>" alt="Student Passport" class="img-fluid rounded-circle" style="width: 180px; height: 180px; object-fit: cover; border: 5px solid #e3f2fd;">
                                            <a href="#" class="change-photo-btn" data-toggle="modal" data-target="#changePassportModal">
                                                <i class="fas fa-camera"></i>
                                            </a>
                                        </div>
                                        <h4 class="mt-3"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                        <p class="text-muted"><?php echo htmlspecialchars($registration_number); ?></p>
                                    </div>
                                    
                                    <div class="profile-stats mb-4">
                                        <div class="stat-card">
                                            <i class="fas fa-file-alt stat-icon"></i>
                                            <div class="stat-value"><?php echo count($exam_results); ?></div>
                                            <div class="stat-label">Exams</div>
                                        </div>
                                        <div class="stat-card">
                                            <i class="fas fa-trophy stat-icon"></i>
                                            <div class="stat-value">
                                                <?php 
                                                $passed = 0;
                                                foreach($exam_results as $exam) {
                                                    if($exam['status'] === 'passed') $passed++;
                                                }
                                                echo $passed;
                                                ?>
                                            </div>
                                            <div class="stat-label">Passed</div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editProfileModal">
                                        <i class="fas fa-user-edit mr-2"></i> Edit Profile
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-8 mb-4">
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-user-circle"></i> Student Information</h4>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong>Full Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Registration Number:</strong> <?php echo htmlspecialchars($student['registration_number']); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Date of Birth:</strong> <?php echo isset($student['date_of_birth']) ? formatDate($student['date_of_birth']) : 'N/A'; ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Gender:</strong> <?php echo ucfirst(htmlspecialchars($student['gender'] ?? 'N/A')); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Nationality:</strong> <?php echo htmlspecialchars($student['nationality'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>State:</strong> <?php echo htmlspecialchars($student['state'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <strong>Contact Address:</strong> <?php echo htmlspecialchars($student['contact_address'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-user-friends"></i> Father's Information</h4>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong>Father's Name:</strong> <?php echo htmlspecialchars($student['father_s_name'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Father's Occupation:</strong> <?php echo htmlspecialchars($student['father_s_occupation'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <strong>Office Address:</strong> <?php echo htmlspecialchars($student['father_s_office_address'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Contact Number:</strong> <?php echo htmlspecialchars($student['father_s_contact_phone_number_s_'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-female"></i> Mother's Information</h4>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong>Mother's Name:</strong> <?php echo htmlspecialchars($student['mother_s_name'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Mother's Occupation:</strong> <?php echo htmlspecialchars($student['mother_s_occupation'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <strong>Office Address:</strong> <?php echo htmlspecialchars($student['mother_s_office_address'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Contact Number:</strong> <?php echo htmlspecialchars($student['mother_s_contact_phone_number_s_'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($student['guardian_name']) || !empty($student['child_lives_with'])): ?>
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-user-shield"></i> Guardian Information</h4>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong>Guardian Name:</strong> <?php echo htmlspecialchars($student['guardian_name'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Guardian Occupation:</strong> <?php echo htmlspecialchars($student['guardian_occupation'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <strong>Office Address:</strong> <?php echo htmlspecialchars($student['guardian_office_address'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Contact Number:</strong> <?php echo htmlspecialchars($student['guardian_contact_phone_number'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Child Lives With:</strong> <?php echo htmlspecialchars($student['child_lives_with'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($student['blood_group']) || !empty($student['genotype']) || !empty($student['allergies'])): ?>
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-heartbeat"></i> Medical Information</h4>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong>Blood Group:</strong> <?php echo htmlspecialchars($student['blood_group'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Genotype:</strong> <?php echo htmlspecialchars($student['genotype'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <strong>Allergies:</strong> <?php echo htmlspecialchars($student['allergies'] ?? 'None'); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exams Tab -->
                    <div class="tab-pane fade" id="exams">
                        <div class="dashboard-card mb-4">
                            <h4><i class="fas fa-chart-line"></i> Exam Performance Summary</h4>
                            <div class="row">
                                <div class="col-lg-8">
                                    <canvas id="examChart" height="250"></canvas>
                                </div>
                                <div class="col-lg-4">
                                    <div class="text-center mt-4">
                                        <div style="width: 120px; height: 120px; margin: 0 auto; position: relative;">
                                            <canvas id="examDoughnutChart"></canvas>
                                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 24px; font-weight: bold;">
                                                <?php 
                                                    $passRate = 0;
                                                    if(count($exam_results) > 0) {
                                                        $passed = 0;
                                                        foreach($exam_results as $exam) {
                                                            if($exam['status'] === 'passed') $passed++;
                                                        }
                                                        $passRate = round(($passed / count($exam_results)) * 100);
                                                    }
                                                    echo $passRate . '%';
                                                ?>
                                            </div>
                                        </div>
                                        <p class="mt-3">Overall Pass Rate</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <h4><i class="fas fa-file-alt"></i> Exam Results</h4>
                            <?php if (empty($exam_results)): ?>
                                <div class="alert alert-info">No exam records found.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Exam Type</th>
                                                <th>Score</th>
                                                <th>Total Score</th>
                                                <th>Percentage</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($exam_results as $exam): ?>
                                                <tr>
                                                    <td><?php echo ucfirst(htmlspecialchars($exam['exam_type'])); ?></td>
                                                    <td><?php echo htmlspecialchars($exam['score']); ?></td>
                                                    <td><?php echo htmlspecialchars($exam['total_score']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $percentage = ($exam['score'] / $exam['total_score']) * 100; 
                                                        echo number_format($percentage, 2) . '%';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($exam['status'] == 'passed'): ?>
                                                            <span class="badge badge-success">Passed</span>
                                                        <?php elseif ($exam['status'] == 'failed'): ?>
                                                            <span class="badge badge-danger">Failed</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatDate($exam['exam_date']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Payments Tab -->
                    <div class="tab-pane fade" id="payments">
                        <div class="dashboard-card mb-4">
                            <h4><i class="fas fa-money-bill-wave"></i> Payment Summary</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="paymentChart" height="250"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="row mt-4">
                                        <div class="col-md-6 mb-4">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <h5 class="card-title text-muted mb-0">Total Payments</h5>
                                                    <div class="display-4 font-weight-bold my-3" style="color: var(--primary-color);">
                                                        <?php echo count($payments); ?>
                                                    </div>
                                                    <p class="card-text text-muted">Transactions</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <h5 class="card-title text-muted mb-0">Total Amount</h5>
                                                    <div class="display-4 font-weight-bold my-3" style="color: var(--success);">₦
                                                        <?php
                                                            $total = 0;
                                                            foreach($payments as $payment) {
                                                                $total += $payment['amount'];
                                                            }
                                                            echo number_format($total, 0);
                                                        ?>
                                                    </div>
                                                    <p class="card-text text-muted">Paid</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <h4><i class="fas fa-list"></i> Payment History</h4>
                            <?php if (empty($payments)): ?>
                                <div class="alert alert-info">No payment records found.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Payment Type</th>
                                                <th>Amount</th>
                                                <th>Payment Method</th>
                                                <th>Reference Number</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($payment['payment_type']))); ?></td>
                                                    <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                                    <td><?php echo ucfirst(htmlspecialchars($payment['payment_method'])); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                                                    <td>
                                                        <?php if ($payment['status'] == 'completed'): ?>
                                                            <span class="badge badge-success">Completed</span>
                                                        <?php elseif ($payment['status'] == 'failed'): ?>
                                                            <span class="badge badge-danger">Failed</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Calendar Tab -->
                    <div class="tab-pane fade" id="calendar">
                        <div class="dashboard-card">
                            <h4><i class="fas fa-calendar"></i> School Calendar</h4>
                            <div class="calendar-container">
                                <div id="schoolCalendar"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Announcements Tab -->
                    <div class="tab-pane fade" id="announcements">
                        <div class="dashboard-card">
                            <h4><i class="fas fa-bullhorn"></i> School Announcements</h4>
                            
                            <div class="announcement-item mb-4 p-3 border-left border-primary">
                                <h5>Welcome to the New Academic Session</h5>
                                <p>The new academic session has begun. We extend a warm welcome to all our students and wish them a productive and successful term ahead.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small"><i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y'); ?></span>
                                    <span class="badge badge-primary">New</span>
                                </div>
                            </div>
                            
                            <div class="announcement-item mb-4 p-3 border-left border-success">
                                <h5>Fee Payment Deadline</h5>
                                <p>All students are reminded that the deadline for school fee payment is the end of this month. Late payments will attract additional charges.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small"><i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y', strtotime('-2 days')); ?></span>
                                    <span class="badge badge-success">Important</span>
                                </div>
                            </div>
                            
                            <div class="announcement-item mb-4 p-3 border-left border-info">
                                <h5>Upcoming Parent-Teacher Meeting</h5>
                                <p>There will be a parent-teacher meeting on the last Friday of this month. All parents are encouraged to attend.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small"><i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y', strtotime('-5 days')); ?></span>
                                    <span class="badge badge-info">Meeting</span>
                                </div>
                            </div>
                            
                            <div class="announcement-item mb-4 p-3 border-left border-warning">
                                <h5>Mid-Term Examination Schedule</h5>
                                <p>The mid-term examination will commence in the second week of next month. The detailed timetable will be shared soon.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small"><i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y', strtotime('-10 days')); ?></span>
                                    <span class="badge badge-warning">Examination</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editProfileForm" action="update_profile.php" method="POST">
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        
                        <h5 class="mb-3">Student Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="first_name">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo isset($student['date_of_birth']) ? date('Y-m-d', strtotime($student['date_of_birth'])) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="gender">Gender</label>
                                    <select class="form-control" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo (isset($student['gender']) && strtolower($student['gender']) == 'male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo (isset($student['gender']) && strtolower($student['gender']) == 'female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="nationality">Nationality</label>
                                    <input type="text" class="form-control" id="nationality" name="nationality" value="<?php echo htmlspecialchars($student['nationality'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="state">State</label>
                                    <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($student['state'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="email">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="contact_address">Contact Address</label>
                            <textarea class="form-control" id="contact_address" name="contact_address" rows="3"><?php echo htmlspecialchars($student['contact_address'] ?? ''); ?></textarea>
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Father's Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="father_s_name">Father's Name</label>
                                    <input type="text" class="form-control" id="father_s_name" name="father_s_name" value="<?php echo htmlspecialchars($student['father_s_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="father_s_occupation">Father's Occupation</label>
                                    <input type="text" class="form-control" id="father_s_occupation" name="father_s_occupation" value="<?php echo htmlspecialchars($student['father_s_occupation'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="father_s_office_address">Father's Office Address</label>
                            <textarea class="form-control" id="father_s_office_address" name="father_s_office_address" rows="2"><?php echo htmlspecialchars($student['father_s_office_address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="father_s_contact_phone_number_s_">Father's Contact Number(s)</label>
                            <input type="text" class="form-control" id="father_s_contact_phone_number_s_" name="father_s_contact_phone_number_s_" value="<?php echo htmlspecialchars($student['father_s_contact_phone_number_s_'] ?? ''); ?>">
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Mother's Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="mother_s_name">Mother's Name</label>
                                    <input type="text" class="form-control" id="mother_s_name" name="mother_s_name" value="<?php echo htmlspecialchars($student['mother_s_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="mother_s_occupation">Mother's Occupation</label>
                                    <input type="text" class="form-control" id="mother_s_occupation" name="mother_s_occupation" value="<?php echo htmlspecialchars($student['mother_s_occupation'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="mother_s_office_address">Mother's Office Address</label>
                            <textarea class="form-control" id="mother_s_office_address" name="mother_s_office_address" rows="2"><?php echo htmlspecialchars($student['mother_s_office_address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="mother_s_contact_phone_number_s_">Mother's Contact Number(s)</label>
                            <input type="text" class="form-control" id="mother_s_contact_phone_number_s_" name="mother_s_contact_phone_number_s_" value="<?php echo htmlspecialchars($student['mother_s_contact_phone_number_s_'] ?? ''); ?>">
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Guardian Information (Optional)</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="guardian_name">Guardian Name</label>
                                    <input type="text" class="form-control" id="guardian_name" name="guardian_name" value="<?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="guardian_occupation">Guardian Occupation</label>
                                    <input type="text" class="form-control" id="guardian_occupation" name="guardian_occupation" value="<?php echo htmlspecialchars($student['guardian_occupation'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="guardian_office_address">Guardian Office Address</label>
                            <textarea class="form-control" id="guardian_office_address" name="guardian_office_address" rows="2"><?php echo htmlspecialchars($student['guardian_office_address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="guardian_contact_phone_number">Guardian Contact Number</label>
                            <input type="text" class="form-control" id="guardian_contact_phone_number" name="guardian_contact_phone_number" value="<?php echo htmlspecialchars($student['guardian_contact_phone_number'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label>Child Lives With</label>
                            <div class="d-flex flex-wrap">
                                <?php
                                $livingWith = $student['child_lives_with'] ?? '';
                                $livingWithOptions = ['Both Parents', 'Mother', 'Father', 'Guardian'];
                                $livingWithArray = explode(',', $livingWith);
                                
                                foreach ($livingWithOptions as $option):
                                    $checked = in_array($option, $livingWithArray) ? 'checked' : '';
                                ?>
                                <div class="form-check me-4">
                                    <input class="form-check-input" type="checkbox" name="child_lives_with[]" id="lives_<?php echo str_replace(' ', '_', strtolower($option)); ?>" value="<?php echo $option; ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label" for="lives_<?php echo str_replace(' ', '_', strtolower($option)); ?>">
                                        <?php echo $option; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Medical Information (Optional)</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="blood_group">Blood Group</label>
                                    <select class="form-control" id="blood_group" name="blood_group">
                                        <option value="">Select Blood Group</option>
                                        <?php
                                        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                        foreach ($bloodGroups as $group):
                                            $selected = ($student['blood_group'] ?? '') === $group ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $group; ?>" <?php echo $selected; ?>><?php echo $group; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="genotype">Genotype</label>
                                    <select class="form-control" id="genotype" name="genotype">
                                        <option value="">Select Genotype</option>
                                        <?php
                                        $genotypes = ['AA', 'AS', 'SS', 'AC', 'SC'];
                                        foreach ($genotypes as $genotype):
                                            $selected = ($student['genotype'] ?? '') === $genotype ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $genotype; ?>" <?php echo $selected; ?>><?php echo $genotype; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="allergies">Allergies</label>
                            <textarea class="form-control" id="allergies" name="allergies" rows="2" placeholder="List any allergies here"><?php echo htmlspecialchars($student['allergies'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="update_profile">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Change Passport Modal -->
    <div class="modal fade" id="changePassportModal" tabindex="-1" role="dialog" aria-labelledby="changePassportModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="changePassportModalLabel">Change Passport Photo</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="passportForm" action="update_passport.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        <input type="hidden" name="registration_number" value="<?php echo $registration_number; ?>">
                        
                        <div class="form-group">
                            <label>Current Passport Photo</label>
                            <div class="text-center mb-3">
                                <img src="<?php echo $display_image; ?>" alt="Current Passport" class="img-fluid rounded" style="max-height: 200px;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="passport_photo">Upload New Passport Photo</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="passport_photo" name="passport_photo" accept="image/jpeg,image/png,image/jpg" required>
                                <label class="custom-file-label" for="passport_photo">Choose file...</label>
                            </div>
                            <small class="form-text text-muted">Accepted formats: JPG, JPEG, PNG. Maximum file size: 2MB. Recommended size: 300x300 pixels.</small>
                        </div>
                        
                        <div id="imagePreview" class="text-center mt-3" style="display: none;">
                            <p>Preview:</p>
                            <img src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="update_passport">Upload Passport</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css" rel="stylesheet">
    
    <script>
        $(document).ready(function() {
            // File input preview for passport photo
            $('#passport_photo').change(function() {
                const file = this.files[0];
                if (file) {
                    let reader = new FileReader();
                    reader.onload = function(e) {
                        $('#imagePreview').show();
                        $('#imagePreview img').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(file);
                    
                    // Update the file input label with the file name
                    let fileName = file.name;
                    $(this).next('.custom-file-label').html(fileName);
                }
            });
            
            // Rest of your JavaScript code
            // Sidebar Navigation Toggle
            $('#navToggle').click(function() {
                $('#app').toggleClass('show-sidebar');
            });
            
            // Tab Navigation
            var hash = window.location.hash;
            if (hash) {
                $('.nav-link[href="' + hash + '"]').tab('show');
            }
            
            $('.nav-link').on('click', function() {
                window.location.hash = $(this).attr('href');
            });
            
            // Initialize Academic Progress Chart
            var academicCtx = document.getElementById('academicChart');
            if (academicCtx) {
                var academicChart = new Chart(academicCtx, {
                    type: 'line',
                    data: {
                        labels: ['Term 1', 'Term 2', 'Term 3'],
                        datasets: [{
                            label: 'Your Score',
                            data: [85, 75, 90],
                            backgroundColor: 'rgba(41, 98, 255, 0.2)',
                            borderColor: '#2962ff',
                            borderWidth: 2,
                            pointBackgroundColor: '#2962ff',
                            pointBorderColor: '#fff',
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            tension: 0.4
                        }, {
                            label: 'Class Average',
                            data: [75, 70, 80],
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderColor: '#9966ff',
                            borderWidth: 2,
                            pointBackgroundColor: '#9966ff',
                            pointBorderColor: '#fff',
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    max: 100
                                },
                                gridLines: {
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    zeroLineColor: 'rgba(0, 0, 0, 0.1)'
                                }
                            }],
                            xAxes: [{
                                gridLines: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            }]
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                });
            }
            
            // Initialize Exam Chart
            var examCtx = document.getElementById('examChart');
            if (examCtx) {
                var examData = <?php 
                    $labels = [];
                    $scores = [];
                    
                    if (!empty($exam_results)) {
                        foreach(array_slice($exam_results, 0, 5) as $exam) {
                            $labels[] = ucfirst($exam['exam_type']);
                            $scores[] = ($exam['score'] / $exam['total_score']) * 100;
                        }
                    } else {
                        $labels = ['No Exam Data'];
                        $scores = [0];
                    }
                    
                    echo json_encode([
                        'labels' => $labels,
                        'scores' => $scores
                    ]);
                ?>;
                
                var examChart = new Chart(examCtx, {
                    type: 'bar',
                    data: {
                        labels: examData.labels,
                        datasets: [{
                            label: 'Performance (%)',
                            data: examData.scores,
                            backgroundColor: [
                                'rgba(41, 98, 255, 0.7)',
                                'rgba(76, 175, 80, 0.7)',
                                'rgba(255, 179, 0, 0.7)',
                                'rgba(233, 30, 99, 0.7)',
                                'rgba(156, 39, 176, 0.7)'
                            ],
                            borderColor: [
                                'rgba(41, 98, 255, 1)',
                                'rgba(76, 175, 80, 1)',
                                'rgba(255, 179, 0, 1)',
                                'rgba(233, 30, 99, 1)',
                                'rgba(156, 39, 176, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    max: 100
                                },
                                gridLines: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            }],
                            xAxes: [{
                                gridLines: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            }]
                        },
                        legend: {
                            display: false
                        }
                    }
                });
            }
            
            // Initialize Exam Doughnut Chart
            var doughnutCtx = document.getElementById('examDoughnutChart');
            if (doughnutCtx) {
                var passRate = <?php 
                    $passRate = 0;
                    if(count($exam_results) > 0) {
                        $passed = 0;
                        foreach($exam_results as $exam) {
                            if($exam['status'] === 'passed') $passed++;
                        }
                        $passRate = round(($passed / count($exam_results)) * 100);
                    }
                    echo $passRate;
                ?>;
                
                var doughnutChart = new Chart(doughnutCtx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [passRate, 100 - passRate],
                            backgroundColor: [
                                '#4caf50',
                                '#f5f5f5'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutoutPercentage: 80,
                        legend: {
                            display: false
                        },
                        tooltips: {
                            enabled: false
                        },
                        animation: {
                            animateRotate: true,
                            animateScale: true
                        }
                    }
                });
            }
            
            // Initialize Payment Chart
            var paymentCtx = document.getElementById('paymentChart');
            if (paymentCtx) {
                var paymentData = <?php 
                    $paymentLabels = [];
                    $paymentAmounts = [];
                    
                    if (!empty($payments)) {
                        $uniqueTypes = [];
                        foreach($payments as $payment) {
                            $type = ucfirst(str_replace('_', ' ', $payment['payment_type']));
                            if (!in_array($type, $uniqueTypes)) {
                                $uniqueTypes[] = $type;
                                $paymentLabels[] = $type;
                                $paymentAmounts[] = 0;
                            }
                            
                            $index = array_search($type, $uniqueTypes);
                            $paymentAmounts[$index] += $payment['amount'];
                        }
                    } else {
                        $paymentLabels = ['No Payment Data'];
                        $paymentAmounts = [0];
                    }
                    
                    echo json_encode([
                        'labels' => $paymentLabels,
                        'amounts' => $paymentAmounts
                    ]);
                ?>;
                
                var paymentChart = new Chart(paymentCtx, {
                    type: 'pie',
                    data: {
                        labels: paymentData.labels,
                        datasets: [{
                            data: paymentData.amounts,
                            backgroundColor: [
                                'rgba(76, 175, 80, 0.7)',
                                'rgba(41, 98, 255, 0.7)',
                                'rgba(255, 179, 0, 0.7)',
                                'rgba(233, 30, 99, 0.7)',
                                'rgba(156, 39, 176, 0.7)'
                            ],
                            borderColor: [
                                'rgba(76, 175, 80, 1)',
                                'rgba(41, 98, 255, 1)',
                                'rgba(255, 179, 0, 1)',
                                'rgba(233, 30, 99, 1)',
                                'rgba(156, 39, 176, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: {
                            position: 'right'
                        }
                    }
                });
            }
            
            // Initialize Calendar
            var calendarEl = document.getElementById('schoolCalendar');
            if (calendarEl) {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,listWeek'
                    },
                    events: [
                        {
                            title: 'First Day of Term',
                            start: '2023-09-04',
                            color: '#1a237e'
                        },
                        {
                            title: 'Mid-Term Break',
                            start: '2023-10-23',
                            end: '2023-10-30',
                            color: '#0d47a1'
                        },
                        {
                            title: 'Parent-Teacher Meeting',
                            start: '2023-09-29',
                            color: '#2962ff'
                        },
                        {
                            title: 'End of Term Exams',
                            start: '2023-12-04',
                            end: '2023-12-15',
                            color: '#e53935'
                        },
                        {
                            title: 'Christmas Holiday',
                            start: '2023-12-18',
                            end: '2024-01-08',
                            color: '#43a047'
                        }
                    ],
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        meridiem: 'short'
                    }
                });
                
                calendar.render();
            }
            
            // Responsive adjustments for mobile
            function checkScreenSize() {
                if (window.innerWidth < 992) {
                    $('#app').removeClass('show-sidebar');
                }
            }
            
            // Run on page load
            checkScreenSize();
            
            // Run on window resize
            $(window).resize(function() {
                checkScreenSize();
            });
        });
    </script>
</body>
</html>
