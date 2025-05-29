<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../utils.php';  // Add this line to include the utils file

// Debug mode disabled - uncomment if needed
// define('DEBUG_MODE', true);

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

// Check if connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure both admission_number and registration_number columns exist
ensureStudentNumberColumns($conn);

// Get student details - using a more robust approach
$stmt = $conn->prepare("SELECT *, COALESCE(admission_number, registration_number) AS display_number FROM students WHERE id = ?");
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $student_id);
$result = $stmt->execute();
if (!$result) {
    die("Error executing query: " . $stmt->error);
}

$studentResult = $stmt->get_result();
if ($studentResult->num_rows === 0) {
    // No student found with this ID
    $student = null;
    $_SESSION['error'] = "Student record not found. Please contact administration.";
} else {
    $student = $studentResult->fetch_assoc();
    // Store for debug
    $_SESSION['debug_raw_student'] = $student;
}

// Get teacher activities for this student
$activitiesQuery = "SELECT cta.*, ct.user_id as teacher_user_id
                   FROM class_teacher_activities cta
                   JOIN class_teachers ct ON cta.class_teacher_id = ct.id
                   WHERE cta.student_id = ?
                   ORDER BY cta.activity_date DESC, cta.created_at DESC
                   LIMIT 10";

$stmt = $conn->prepare($activitiesQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$activitiesResult = $stmt->get_result();
$teacherActivities = [];

while ($row = $activitiesResult->fetch_assoc()) {
    $teacherActivities[] = $row;
}

// Get teacher comments for this student
$commentsQuery = "SELECT ctc.*, ct.user_id as teacher_user_id
                  FROM class_teacher_comments ctc
                  JOIN class_teachers ct ON ctc.class_teacher_id = ct.id
                  WHERE ctc.student_id = ?
                  ORDER BY ctc.created_at DESC
                  LIMIT 10";

$stmt = $conn->prepare($commentsQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$commentsResult = $stmt->get_result();
$teacherComments = [];

while ($row = $commentsResult->fetch_assoc()) {
    $teacherComments[] = $row;
}

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
    $possible_paths[] = '../../uploads/student_files/' . $student_photo;
    $possible_paths[] = '../../../uploads/student_files/' . $student_photo;
}

// Path 2: Using registration number
$safe_registration = str_replace(['/', ' '], '_', $registration_number);
$possible_paths[] = '../uploads/student_files/' . $safe_registration . '.jpg';
$possible_paths[] = '../uploads/student_files/' . $safe_registration . '.png';
$possible_paths[] = '../../uploads/student_files/' . $safe_registration . '.jpg';
$possible_paths[] = '../../uploads/student_files/' . $safe_registration . '.png';

// Path 3: Check profile path 
$possible_paths[] = '../uploads/student_passports/' . $safe_registration . '.jpg';
$possible_paths[] = '../uploads/student_passports/' . $safe_registration . '.png';
$possible_paths[] = '../../uploads/student_passports/' . $safe_registration . '.jpg';
$possible_paths[] = '../../uploads/student_passports/' . $safe_registration . '.png';
$possible_paths[] = '../../../uploads/student_passports/' . $safe_registration . '.jpg';
$possible_paths[] = '../../../uploads/student_passports/' . $safe_registration . '.png';

// Path 4: Absolute paths if needed
$base_dir = realpath(dirname(__FILE__) . '/../../..');
$possible_paths[] = $base_dir . '/uploads/student_files/' . $safe_registration . '.jpg';
$possible_paths[] = $base_dir . '/uploads/student_files/' . $safe_registration . '.png';
$possible_paths[] = $base_dir . '/uploads/student_passports/' . $safe_registration . '.jpg';
$possible_paths[] = $base_dir . '/uploads/student_passports/' . $safe_registration . '.png';

// Path 5: General school images directory
$possible_paths[] = '../../../images/student_photos/' . $safe_registration . '.jpg';
$possible_paths[] = '../../../images/student_photos/' . $safe_registration . '.png';
$possible_paths[] = '../../../images/students/' . $safe_registration . '.jpg';
$possible_paths[] = '../../../images/students/' . $safe_registration . '.png';

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

// Get class teacher's name for the student's class
$class_teacher_name = '';
if (!empty($student_class)) {
    $teacherQuery = "SELECT t.first_name, t.last_name
                     FROM class_teachers ct
                     JOIN teachers t ON ct.teacher_id = t.id
                     WHERE ct.class_name = ? AND ct.is_active = 1
                     LIMIT 1";
    $stmt = $conn->prepare($teacherQuery);
    $stmt->bind_param("s", $student_class);
    $stmt->execute();
    $teacherResult = $stmt->get_result();
    if ($teacher = $teacherResult->fetch_assoc()) {
        $class_teacher_name = $teacher['first_name'] . ' ' . $teacher['last_name'];
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

// Debug student data
if (isset($student)) {
    $_SESSION['student_debug'] = [
        'id' => $student['id'] ?? 'N/A',
        'first_name' => $student['first_name'] ?? 'N/A',
        'last_name' => $student['last_name'] ?? 'N/A',
        'registration_number' => $student['display_number'] ?? $student['registration_number'] ?? $student['admission_number'] ?? 'N/A',
        'gender' => $student['gender'] ?? 'N/A',
        'date_of_birth' => $student['date_of_birth'] ?? 'N/A',
        'fetch_success' => 'Yes'
    ];
} else {
    $_SESSION['student_debug'] = ['fetch_success' => 'No', 'message' => 'Student record not found'];
}

// Get student information if student ID is provided
$studentName = '';
if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $nameResult = $stmt->get_result();
    if ($nameRow = $nameResult->fetch_assoc()) {
        $studentName = $nameRow['first_name'] . ' ' . $nameRow['last_name'];
    }
}

// Get announcements for this student
$announcementsQuery = "
    SELECT a.*, ct.user_id as teacher_user_id
    FROM announcements a
    JOIN class_teachers ct ON a.class_teacher_id = ct.id
    WHERE a.student_id = ? OR a.student_id IS NULL
    ORDER BY a.created_at DESC
    LIMIT 10
";

$stmt = $conn->prepare($announcementsQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$announcementsResult = $stmt->get_result();
$studentAnnouncements = [];

while ($row = $announcementsResult->fetch_assoc()) {
    $studentAnnouncements[] = $row;
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
    
    <!-- JavaScript Libraries - Load early -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Mobile Navigation Script -->
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            var sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('mobile-active');
            }
            return false;
        }
        
        // Initialize when page is fully loaded
        $(document).ready(function() {
            // Show dashboard tab by default
            $('#mainTabContent .tab-pane:first').addClass('show active');
            $('.sidebar .nav-link:first').addClass('active');
            
            // Handle sidebar navigation clicks
            $('.sidebar .nav-link').on('click', function(e) {
                e.preventDefault();
                var targetTab = $(this).attr('href');
                
                // Update active states
                $('.sidebar .nav-link').removeClass('active');
                $(this).addClass('active');
                
                // Show the target tab
                $('#mainTabContent .tab-pane').removeClass('show active');
                $(targetTab).addClass('show active');
                
                // Close mobile sidebar if needed
                if (window.innerWidth < 992) {
                    $('.sidebar').removeClass('mobile-active');
                }
            });
            
            // Handle profile tab navigation
            $('#profileTabs .nav-link').on('click', function(e) {
                e.preventDefault();
                var targetTab = $(this).attr('href');
                
                // Update active states
                $('#profileTabs .nav-link').removeClass('active');
                $(this).addClass('active');
                
                // Show the target tab
                $('#profileTabsContent .tab-pane').removeClass('show active');
                $(targetTab).addClass('show active');
            });
            
            // Add mobile styles
            var style = document.createElement('style');
            style.textContent = `
                @media (max-width: 991.98px) {
                    .sidebar {
                        position: fixed;
                        left: -250px;
                        top: 0;
                        height: 100%;
                        z-index: 1050;
                        transition: left 0.3s ease;
                    }
                    .sidebar.mobile-active {
                        left: 0;
                    }
                    .main-content {
                        margin-left: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
    
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

        /* Profile Section Styling */
        .profile-card {
            border-radius: 10px;
            overflow: hidden;
            border: none;
            transition: all 0.3s ease;
        }

        .dashboard-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .profile-image-container {
            position: relative;
            margin-bottom: 10px;
        }

        .profile-image {
            transition: all 0.3s ease;
        }

        .profile-image:hover {
            transform: scale(1.02);
        }

        .change-photo-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 123, 255, 0.8);
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .change-photo-btn:hover {
            background: rgba(0, 123, 255, 1);
            transform: scale(1.1);
        }

        .student-badge {
            background: #e6f7ff;
            color: #0056b3;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .class-badge {
            background: #e3f2fd;
            color: #1a237e;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .stats-container {
            display: flex;
            justify-content: space-between;
            margin: 0 -5px;
        }

        .stat-card {
            flex: 1;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px 10px;
            margin: 0 5px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }

        .stat-icon-container {
            width: 40px;
            height: 40px;
            background: #e3f2fd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }

        .stat-icon {
            color: #1a237e;
            font-size: 18px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #0d47a1;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }

        .profile-tabs-container {
            margin-bottom: 20px;
        }

        .profile-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }

        .profile-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 600;
            padding: 10px 15px;
        }

        .profile-tabs .nav-link.active {
            color: #1a237e;
            border-bottom-color: #1a237e;
            background: transparent;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3f2fd;
            color: #333;
        }

        .profile-details {
            padding: 0 5px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .info-value {
            font-size: 15px;
            color: #343a40;
            font-weight: 500;
        }

        .text-warning {
            color: #ffc107 !important;
        }

        @media (max-width: 767px) {
            .stats-container {
                flex-direction: column;
            }
            
            .stat-card {
                margin: 5px 0;
            }
            
            .profile-actions {
                flex-direction: column;
            }
            
            .profile-actions .btn {
                margin-right: 0 !important;
                margin-bottom: 10px;
            }
        }

        /* Improved Tab Transitions */
        .tab-pane {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        
        .tab-pane.fade {
            opacity: 0;
            transform: translateY(10px);
        }
        
        .tab-pane.fade.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Active Nav Indicator */
        .sidebar .nav-link {
            position: relative;
        }
        
        .sidebar .nav-link.active:after {
            content: "";
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            border-style: solid;
            border-width: 8px 8px 8px 0;
            border-color: transparent #fff transparent transparent;
        }
        
        /* Mobile Navigation Enhancement */
        @media (max-width: 991.98px) {
            .sidebar {
                z-index: 1050;
                box-shadow: 0 0 15px rgba(0,0,0,0.2);
            }
            
            .sidebar.mobile-active {
                width: var(--sidebar-width) !important;
                left: 0;
                padding: 0;
            }
            
            .nav-toggle {
                display: flex !important;
                z-index: 1060;
            }
        }

        /* Add these styles to your existing CSS */
        .bg-gradient-primary {
            background: linear-gradient(45deg, #4e73df, #224abe);
        }

        .table > :not(caption) > * > * {
            padding: 1rem;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
            transition: all 0.2s ease;
        }

        .badge {
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .btn-sm {
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
        }

        .btn-sm:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .card {
            border-radius: 10px;
            overflow: hidden;
        }

        .card-header {
            border-bottom: none;
        }

        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            color: #5a5c69;
        }

        .alert {
            border-radius: 10px;
        }

        .alert-info {
            background-color: #e8f4fd;
            border-left: 4px solid #4e73df;
        }

        .text-primary {
            color: #4e73df !important;
        }

        .text-info {
            color: #36b9cc !important;
        }

        .text-secondary {
            color: #858796 !important;
        }
    </style>
</head>
<body>
    <!-- Update the navigation toggle button -->
    <button class="nav-toggle d-lg-none" id="navToggle" onclick="toggleSidebar(); return false;">
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
                        <a class="nav-link active" href="#dashboard" data-toggle="tab" role="tab" aria-controls="dashboard" aria-selected="true">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#profile" data-toggle="tab" role="tab" aria-controls="profile" aria-selected="false">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#exams" data-toggle="tab" role="tab" aria-controls="exams" aria-selected="false">
                            <i class="fas fa-file-alt"></i> Exams & Results
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#cbt-exams" data-toggle="tab" role="tab" aria-controls="cbt-exams" aria-selected="false">
                            <i class="fas fa-laptop"></i> CBT Exams
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#payments" data-toggle="tab" role="tab" aria-controls="payments" aria-selected="false">
                            <i class="fas fa-money-bill-wave"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#calendar" data-toggle="tab" role="tab" aria-controls="calendar" aria-selected="false">
                            <i class="fas fa-calendar-alt"></i> Calendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#announcements" data-toggle="tab" role="tab" aria-controls="announcements" aria-selected="false">
                            <i class="fas fa-bullhorn"></i> Announcements
                            <?php if (count($studentAnnouncements) > 0): ?>
                            <span class="badge badge-pill badge-light float-right"><?php echo count($studentAnnouncements); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#teacher-feedback" data-toggle="tab" role="tab" aria-controls="teacher-feedback" aria-selected="false">
                            <i class="fas fa-clipboard-check"></i> Teacher Feedback
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
                            <?php if (!empty($class_teacher_name)): ?>
                                <p><strong>Class Teacher:</strong> <?php echo htmlspecialchars($class_teacher_name); ?></p>
                            <?php endif; ?>
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
                
                <?php if(isset($_SESSION['debug_raw_student']) && ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1')): ?>
                <!-- <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <h5><i class="fas fa-bug"></i> Raw Student Data</h5>
                    <div>
                        <pre><?php print_r($_SESSION['debug_raw_student']); ?></pre>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div> -->
                <?php endif; ?>
                
                <?php if(isset($_SESSION['student_debug']) && ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1')): ?>
                <!-- <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                    <h5><i class="fas fa-info-circle"></i> Student Data Debug</h5>
                    <div>
                        <pre><?php print_r($_SESSION['student_debug']); ?></pre>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div> -->
                <?php endif; ?>
                
                <!-- Tab Content -->
                <div class="tab-content" id="mainTabContent" style="min-height: 500px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="dashboard-card card-primary">
                                    <div class="text-center">
                                        <i class="fas fa-file-alt card-icon"></i>
                                        <h4>Exams</h4>
                                        <!-- <p class="h3"><?php echo $total_exams; ?></p> -->
                                        <p>Total exams completed</p>
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
                                        <?php if (count($studentAnnouncements) > 0): ?>
                                            <div class="alert alert-<?php 
                                                $alertClass = 'info'; 
                                                switch($studentAnnouncements[0]['type']) {
                                                    case 'academic': $alertClass = 'primary'; break;
                                                    case 'exam': $alertClass = 'warning'; break;
                                                    case 'event': $alertClass = 'success'; break;
                                                    case 'payment': $alertClass = 'danger'; break;
                                                    case 'important': $alertClass = 'dark'; break;
                                                }
                                                echo $alertClass;
                                            ?>">
                                                <h5><?php echo htmlspecialchars($studentAnnouncements[0]['title']); ?></h5>
                                                <p><?php echo htmlspecialchars(substr($studentAnnouncements[0]['content'], 0, 100) . (strlen($studentAnnouncements[0]['content']) > 100 ? '...' : '')); ?></p>
                                                <small class="text-muted"><i class="far fa-clock mr-1"></i> <?php echo date('M d, Y', strtotime($studentAnnouncements[0]['created_at'])); ?></small>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <h5>Welcome to the New Session</h5>
                                                <p>No announcements available yet.</p>
                                            </div>
                                        <?php endif; ?>
                                        <a href="#announcements" data-toggle="tab" class="btn btn-sm btn-link">View All Announcements</a>
                                    </div>
                                </div>
                                
                                <div class="dashboard-card mt-4">
                                    <h4><i class="fas fa-clipboard-check"></i> Teacher Feedback</h4>
                                    <?php if (count($teacherActivities) > 0 || count($teacherComments) > 0): ?>
                                        <?php if (count($teacherActivities) > 0): ?>
                                            <div class="mb-3">
                                                <div class="alert alert-warning mb-2">
                                                    <strong><?php echo ucfirst($teacherActivities[0]['activity_type']); ?>:</strong> 
                                                    <?php echo substr($teacherActivities[0]['description'], 0, 70) . (strlen($teacherActivities[0]['description']) > 70 ? '...' : ''); ?>
                                                    <small class="d-block text-muted mt-1">
                                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y', strtotime($teacherActivities[0]['activity_date'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (count($teacherComments) > 0): ?>
                                            <div class="mb-3">
                                                <div class="alert alert-secondary mb-2">
                                                    <strong><?php echo ucfirst($teacherComments[0]['comment_type']); ?>:</strong> 
                                                    <?php echo substr($teacherComments[0]['comment'], 0, 70) . (strlen($teacherComments[0]['comment']) > 70 ? '...' : ''); ?>
                                                    <small class="d-block text-muted mt-1">
                                                        <i class="far fa-clock mr-1"></i> <?php echo date('M d, Y', strtotime($teacherComments[0]['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <a href="#teacher-feedback" data-toggle="tab" class="btn btn-sm btn-link">View All Teacher Feedback</a>
                                    <?php else: ?>
                                        <div class="alert alert-light">
                                            <p class="mb-0">No recent feedback available from your teacher.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="dashboard-card">
                                    <h4><i class="fas fa-tasks"></i> Quick Actions</h4>
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <a href="#" data-toggle="tab" data-target="#profile" class="btn btn-light btn-block py-3">
                                                <i class="fas fa-user-edit mb-2 d-block" style="font-size: 24px;"></i>
                                                View Profile
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <a href="../../../lib/user_dashboard.php" class="btn btn-light btn-block py-3">
                                                <i class="fas fa-file-alt mb-2 d-block" style="font-size: 24px;"></i>
                                                Digital Library
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <a href="#" data-toggle="tab" data-target="#payments" class="btn btn-light btn-block py-3">
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
                        
                        <!-- Teacher Activities Section -->
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="dashboard-card card-warning">
                                    <h4><i class="fas fa-clipboard-list"></i> Recent Activities</h4>
                                    <?php if (count($teacherActivities) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th style="width: 15%">Date</th>
                                                    <th style="width: 15%">Type</th>
                                                    <th>Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($teacherActivities as $activity): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        $typeClass = '';
                                                        switch ($activity['activity_type']) {
                                                            case 'attendance': $typeClass = 'badge-info'; break;
                                                            case 'behavioral': $typeClass = 'badge-warning'; break;
                                                            case 'academic': $typeClass = 'badge-success'; break;
                                                            case 'health': $typeClass = 'badge-danger'; break;
                                                            default: $typeClass = 'badge-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $typeClass; ?>">
                                                            <?php echo ucfirst($activity['activity_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $activity['description']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No activities have been recorded for you by your class teacher.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Teacher Comments Section -->
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="dashboard-card card-secondary">
                                    <h4><i class="fas fa-comments"></i> Teacher Comments</h4>
                                    <?php if (count($teacherComments) > 0): ?>
                                    <div class="direct-chat-messages" style="height: auto;">
                                        <?php foreach ($teacherComments as $comment): ?>
                                        <div class="direct-chat-msg">
                                            <div class="direct-chat-infos clearfix">
                                                <span class="direct-chat-name float-left">Class Teacher</span>
                                                <span class="direct-chat-timestamp float-right">
                                                    <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                                </span>
                                            </div>
                                            <div class="direct-chat-img">
                                                <i class="fas fa-user-circle fa-2x"></i>
                                            </div>
                                            <div class="direct-chat-text">
                                                <strong><?php echo ucfirst($comment['comment_type']); ?>:</strong>
                                                <?php echo nl2br($comment['comment']); ?>
                                                <?php if (!empty($comment['term'])): ?>
                                                <small class="text-muted d-block mt-1">
                                                    Term: <?php echo $comment['term']; ?>, 
                                                    Session: <?php echo $comment['session']; ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No comments have been added by your class teacher.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Tab -->
                    <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
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
                            <div class="col-lg-4 mb-4">
                                <div class="dashboard-card profile-card shadow">
                                    <div class="passport-container text-center mb-4">
                                        <?php 
                                        // Sanitize registration number for file path
                                        $safe_registration = str_replace('/', '_', $registration_number);
                                        $passport_path = '../../../uploads/student_passports/' . $safe_registration . '.jpg';
                                        $default_avatar = '../../../images/avatar-placeholder.png';
                                        $display_image = file_exists($passport_path) ? $passport_path : $default_avatar;
                                        ?>
                                        <div class="position-relative d-inline-block profile-image-container">
                                            <img src="<?php echo $display_image; ?>" alt="Student Passport" class="img-fluid rounded-circle profile-image" style="width: 180px; height: 180px; object-fit: cover; border: 5px solid #e3f2fd; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                                            <a href="#" class="change-photo-btn" data-toggle="modal" data-target="#changePassportModal">
                                                <i class="fas fa-camera"></i>
                                            </a>
                                        </div>
                                        <h4 class="mt-3 mb-1 font-weight-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                        <div class="student-badge mb-2"><?php echo htmlspecialchars($registration_number); ?></div>
                                        
                                        <?php if (isset($student['class'])): ?>
                                        <div class="class-badge mb-3">
                                            <i class="fas fa-graduation-cap mr-1"></i> <?php echo htmlspecialchars($student['class'] ?? 'Not assigned'); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="profile-stats mb-4">
                                        <div class="stats-container">
                                            <div class="stat-card">
                                                <div class="stat-icon-container">
                                                    <i class="fas fa-file-alt stat-icon"></i>
                                                </div>
                                                <div>
                                                    <div class="stat-value"><?php echo count($exam_results); ?></div>
                                                    <div class="stat-label">Exams</div>
                                                </div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-icon-container">
                                                    <i class="fas fa-trophy stat-icon"></i>
                                                </div>
                                                <div>
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
                                            <div class="stat-card">
                                                <div class="stat-icon-container">
                                                    <i class="fas fa-chart-line stat-icon"></i>
                                                </div>
                                                <div>
                                                    <div class="stat-value">
                                                        <?php 
                                                        echo (count($exam_results) > 0) 
                                                            ? round(($passed / count($exam_results)) * 100) . '%' 
                                                            : '0%';
                                                        ?>
                                                    </div>
                                                    <div class="stat-label">Success</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="profile-actions d-flex justify-content-between">
                                        <button type="button" class="btn btn-primary flex-grow-1 mr-2" data-toggle="modal" data-target="#editProfileModal">
                                            <i class="fas fa-user-edit mr-2"></i> Edit Profile
                                        </button>
                                        <button type="button" class="btn btn-outline-primary flex-grow-1" onclick="window.print()">
                                            <i class="fas fa-print mr-2"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-8 mb-4">
                                <div class="profile-tabs-container">
                                    <ul class="nav nav-tabs profile-tabs" id="profileTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="personal-tab" data-toggle="tab" href="#personal-info" role="tab" aria-controls="personal-info" aria-selected="true">
                                                <i class="fas fa-user mr-2"></i> Personal
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="family-tab" data-toggle="tab" href="#family-info" role="tab" aria-controls="family-info" aria-selected="false">
                                                <i class="fas fa-users mr-2"></i> Family
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="medical-tab" data-toggle="tab" href="#medical-info" role="tab" aria-controls="medical-info" aria-selected="false">
                                                <i class="fas fa-heartbeat mr-2"></i> Medical
                                            </a>
                                        </li>
                                    </ul>
                                    
                                    <div class="tab-content profile-tab-content" id="profileTabsContent">
                                        <!-- Personal Information Tab -->
                                        <div class="tab-pane fade show active" id="personal-info" role="tabpanel" aria-labelledby="personal-tab">
                                            <div class="dashboard-card shadow-sm">
                                                <h4 class="section-title"><i class="fas fa-user-circle text-primary mr-2"></i> Student Information</h4>
                                                <div class="profile-details">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Full Name</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Registration Number</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['display_number'] ?? $student['registration_number'] ?? $student['admission_number'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Date of Birth</div>
                                                                <div class="info-value"><?php echo isset($student['date_of_birth']) ? formatDate($student['date_of_birth']) : 'N/A'; ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Gender</div>
                                                                <div class="info-value"><?php echo ucfirst(htmlspecialchars($student['gender'] ?? 'N/A')); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Nationality</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['nationality'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">State</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['state'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Email</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="info-item">
                                                                <div class="info-label">Contact Address</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['contact_address'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Family Information Tab -->
                                        <div class="tab-pane fade" id="family-info" role="tabpanel" aria-labelledby="family-tab">
                                            <div class="dashboard-card shadow-sm mb-4">
                                                <h4 class="section-title"><i class="fas fa-male text-primary mr-2"></i> Father's Information</h4>
                                                <div class="profile-details">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Father's Name</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['father_s_name'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Father's Occupation</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['father_s_occupation'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Contact Number</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['father_s_contact_phone_number_s_'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="info-item">
                                                                <div class="info-label">Office Address</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['father_s_office_address'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="dashboard-card shadow-sm mb-4">
                                                <h4 class="section-title"><i class="fas fa-female text-primary mr-2"></i> Mother's Information</h4>
                                                <div class="profile-details">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Mother's Name</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['mother_s_name'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Mother's Occupation</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['mother_s_occupation'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Contact Number</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['mother_s_contact_phone_number_s_'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="info-item">
                                                                <div class="info-label">Office Address</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['mother_s_office_address'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($student['guardian_name']) || !empty($student['child_lives_with'])): ?>
                                            <div class="dashboard-card shadow-sm">
                                                <h4 class="section-title"><i class="fas fa-user-shield text-primary mr-2"></i> Guardian Information</h4>
                                                <div class="profile-details">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Guardian Name</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['guardian_name'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Guardian Occupation</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['guardian_occupation'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Contact Number</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['guardian_contact_phone_number'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Child Lives With</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['child_lives_with'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="info-item">
                                                                <div class="info-label">Office Address</div>
                                                                <div class="info-value"><?php echo htmlspecialchars($student['guardian_office_address'] ?? 'N/A'); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Medical Information Tab -->
                                        <div class="tab-pane fade" id="medical-info" role="tabpanel" aria-labelledby="medical-tab">
                                            <div class="dashboard-card shadow-sm">
                                                <h4 class="section-title"><i class="fas fa-heartbeat text-primary mr-2"></i> Medical Information</h4>
                                                <div class="profile-details">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Blood Group</div>
                                                                <div class="info-value<?php echo empty($student['blood_group']) ? ' text-warning' : ''; ?>">
                                                                    <?php echo !empty($student['blood_group']) ? htmlspecialchars($student['blood_group']) : 'Not Provided'; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="info-item">
                                                                <div class="info-label">Genotype</div>
                                                                <div class="info-value<?php echo empty($student['genotype']) ? ' text-warning' : ''; ?>">
                                                                    <?php echo !empty($student['genotype']) ? htmlspecialchars($student['genotype']) : 'Not Provided'; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="info-item">
                                                                <div class="info-label">Allergies</div>
                                                                <div class="info-value">
                                                                    <?php echo !empty($student['allergies']) ? htmlspecialchars($student['allergies']) : 'None reported'; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exams Tab -->
                    <div class="tab-pane fade" id="exams" role="tabpanel" aria-labelledby="exams-tab">
                        <div class="dashboard-card mb-4">
                            <h4><i class="fas fa-file-alt"></i> Exams & Results</h4>
                            
                            <!-- Add Exam Results Section -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card shadow-sm border-0">
                                        <div class="card-header bg-gradient-primary text-white py-3">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-chart-bar fa-2x mr-3"></i>
                                                <h5 class="mb-0 font-weight-bold">Exam Results</h5>
                                            </div>
                                        </div>
                                        <div class="card-body p-4">
                                           
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- <?php if (count($availableExams) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Title</th>
                                                <th>Duration</th>
                                                <th>Questions</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($availableExams as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['time_limit']); ?> minutes</td>
                                                <td><?php echo htmlspecialchars($exam['total_questions'] ?? 'Not specified'); ?></td>
                                                <td>
                                                    <span class="badge badge-success">Available</span>
                                                </td>
                                                <td>
                                                    <form method="POST" action="../../../backends/cbt/take_exam.php" class="d-inline">
                                                        <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                                        <input type="hidden" name="start_exam" value="1">
                                                        <button type="submit" 
                                                           class="btn btn-primary btn-sm start-exam-btn"
                                                           data-exam-id="<?php echo $exam['id']; ?>"
                                                           data-exam-title="<?php echo htmlspecialchars($exam['title']); ?>"
                                                           data-exam-subject="<?php echo htmlspecialchars($exam['subject']); ?>"
                                                           data-exam-duration="<?php echo htmlspecialchars($exam['time_limit']); ?>"
                                                           data-exam-questions="<?php echo htmlspecialchars($exam['total_questions'] ?? 'N/A'); ?>">
                                                            Start Exam
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <!-- <p><i class="fas fa-info-circle mr-2"></i> There are no CBT exams currently available for your class.</p> -->
                                    <p>Current class: <strong><?php echo htmlspecialchars($studentClass ?? 'Not set'); ?></strong></p>
                                    
                                    <!-- Alternative access option -->
                                    <hr>
                                    <p><i class="fas fa-sign-in-alt mr-2"></i> You can also access exams directly using your credentials:</p>
                                    <a href="../../../backends/cbt/take_exam.php" class="btn btn-primary btn-sm mt-2">
                                        Access Exam Portal <i class="fas fa-external-link-alt ml-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?> -->
                            
                            <!-- <h4 class="mt-5"><i class="fas fa-history"></i> My Past Exams</h4> -->
                            
                            <!-- <?php if (count($pastExams) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Title</th>
                                                <th>Date Taken</th>
                                                <th>Status</th>
                                                <th>Score</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pastExams as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td>
                                                    <?php echo isset($exam['completed_at']) 
                                                          ? date('M d, Y g:i A', strtotime($exam['completed_at'])) 
                                                          : (isset($exam['started_at']) 
                                                             ? date('M d, Y g:i A', strtotime($exam['started_at'])) . ' (Not submitted)' 
                                                             : 'N/A'); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $status = $exam['status'] ?? '';
                                                    switch ($status) {
                                                        case 'Completed':
                                                        case 'passed':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'In Progress':
                                                            $statusClass = 'warning';
                                                            break;
                                                        case 'failed':
                                                            $statusClass = 'danger';
                                                            break;
                                                        default:
                                                            $statusClass = 'secondary';
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (isset($exam['score']) && ($exam['show_results'] == 1)): ?>
                                                        <span class="badge badge-<?php echo ($exam['score'] >= $exam['passing_score']) ? 'success' : 'danger'; ?>">
                                                            <?php echo $exam['score']; ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Not available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="../../cbt/view_result.php?exam_id=<?php echo $exam['exam_id']; ?>&student_id=<?php echo $_SESSION['student_id']; ?>" 
                                                       class="btn btn-info btn-sm <?php echo (!$exam['show_results'] && $exam['status'] !== 'In Progress') ? 'disabled' : ''; ?>"
                                                       <?php echo (!$exam['show_results'] && $exam['status'] !== 'In Progress') ? 'title="Results not available yet"' : ''; ?>>
                                                        <i class="fas fa-eye"></i> View Result
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-secondary">
                                    <p>You haven't taken any CBT exams yet.</p>
                                </div>
                            <?php endif; ?> -->
                        </div>
                    </div>
                    
                    <!-- Announcements Tab -->
                    <div class="tab-pane fade" id="announcements" role="tabpanel" aria-labelledby="announcements-tab">
                        <div class="dashboard-card">
                            <h4><i class="fas fa-bullhorn"></i> School Announcements</h4>
                            
                            <?php if (count($studentAnnouncements) > 0): ?>
                                <?php foreach($studentAnnouncements as $announcement): ?>
                                    <div class="announcement-item mb-4 p-3 border-left border-<?php 
                                        $borderClass = 'primary'; 
                                        switch($announcement['type']) {
                                            case 'general': $borderClass = 'primary'; break;
                                            case 'academic': $borderClass = 'info'; break;
                                            case 'exam': $borderClass = 'warning'; break;
                                            case 'event': $borderClass = 'success'; break;
                                            case 'payment': $borderClass = 'danger'; break;
                                            case 'important': $borderClass = 'dark'; break;
                                        }
                                        echo $borderClass;
                                    ?>">
                                        <h5><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                        <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small"><i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></span>
                                            <span class="badge badge-<?php echo $borderClass; ?>"><?php echo ucfirst($announcement['type']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if (!$photo_found): ?>
                                <div class="alert alert-warning mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 mr-3">
                                            <i class="fas fa-exclamation-circle fa-2x"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="alert-heading">Important: Update Your Profile Photo</h5>
                                            <p class="mb-0">Welcome to <?php echo SCHOOL_NAME; ?>! We noticed that you haven't uploaded your passport photograph yet. Please update your profile photo to complete your student profile.</p>
                                            <hr>
                                            <p class="mb-0">
                                                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#changePassportModal">
                                                    <i class="fas fa-camera mr-1"></i> Upload Photo Now
                                                </button>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="alert alert-info">
                                    <p class="mb-0">No other announcements are currently available.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- CBT Exams Tab -->
                    <div class="tab-pane fade" id="cbt-exams" role="tabpanel" aria-labelledby="cbt-exams-tab">
                        <div class="dashboard-card mb-4">
                            <h4><i class="fas fa-laptop"></i> Available CBT Exams</h4>
                            
                            <?php
                            // Get student's class
                            $studentClass = isset($student['class']) ? $student['class'] : '';
                            
                            // Initialize arrays for exams
                            $availableExams = [];
                            $pastExams = [];
                            
                            try {
                                // DIRECT QUERY APPROACH - Skip table existence checks
                                $availableExamsQuery = "SELECT id, title, class, subject, 
                                   duration as time_limit, total_questions, 
                                   passing_score, show_results, is_active
                             FROM cbt_exams 
                             WHERE is_active = 1
                             AND TRIM(class) LIKE ?
                             ORDER BY created_at DESC";
                                
                                // Use pattern matching to find exams for this class
                                $searchPattern = '%' . trim($studentClass) . '%';
                                $stmt = $conn->prepare($availableExamsQuery);
                                
                                if ($stmt) {
                                    $stmt->bind_param("s", $searchPattern);
                                    $stmt->execute();
                                    $availableExamsResult = $stmt->get_result();
                                    
                                    while ($row = $availableExamsResult->fetch_assoc()) {
                                        $availableExams[] = $row;
                                    }
                                    
                                    // If specific query failed, try a second approach with exact class names
                                    if (count($availableExams) == 0) {
                                        $exactQuery = "SELECT id, title, class, subject, 
                                 duration as time_limit, total_questions, 
                                 passing_score, show_results, is_active
                           FROM cbt_exams 
                           WHERE is_active = 1
                           ORDER BY created_at DESC";
                                        
                                        $result = $conn->query($exactQuery);
                                        if ($result) {
                                            while ($row = $result->fetch_assoc()) {
                                                // String comparison including trimming
                                                if (strcasecmp(trim($row['class']), trim($studentClass)) == 0) {
                                                    $availableExams[] = $row;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Try to get past exams if student_exams table exists
                                    try {
                                        $pastExamsQuery = "SELECT se.*, e.title, e.subject, 
                                    e.duration as time_limit, e.passing_score, 
                                    e.show_results
                              FROM cbt_student_attempts se
                              JOIN cbt_exams e ON se.exam_id = e.id
                              WHERE se.student_id = ?
                              ORDER BY se.completed_at DESC";
                                        
                                        $stmt = $conn->prepare($pastExamsQuery);
                                        if ($stmt) {
                                            $stmt->bind_param("i", $student_id);
                                            $stmt->execute();
                                            $pastExamsResult = $stmt->get_result();
                                            
                                            while ($row = $pastExamsResult->fetch_assoc()) {
                                                $pastExams[] = $row;
                                            }
                                        }
                                    } catch (Exception $e) {
                                        // Silently handle missing student_exams table
                                    }
                                }
                            } catch (Exception $e) {
                                // Just initialize empty arrays if any errors
                                $availableExams = [];
                                $pastExams = [];
                            }
                            ?>
                            
                            <?php if (count($availableExams) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Title</th>
                                                <th>Duration</th>
                                                <th>Questions</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($availableExams as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['time_limit']); ?> minutes</td>
                                                <td><?php echo htmlspecialchars($exam['total_questions'] ?? 'Not specified'); ?></td>
                                                <td>
                                                    <span class="badge badge-success">Available</span>
                                                </td>
                                                <td>
                                                    <form method="POST" action="../../../backends/cbt/take_exam.php" class="d-inline">
                                                        <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                                        <input type="hidden" name="start_exam" value="1">
                                                        <button type="submit" 
                                                           class="btn btn-primary btn-sm start-exam-btn"
                                                           data-exam-id="<?php echo $exam['id']; ?>"
                                                           data-exam-title="<?php echo htmlspecialchars($exam['title']); ?>"
                                                           data-exam-subject="<?php echo htmlspecialchars($exam['subject']); ?>"
                                                           data-exam-duration="<?php echo htmlspecialchars($exam['time_limit']); ?>"
                                                           data-exam-questions="<?php echo htmlspecialchars($exam['total_questions'] ?? 'N/A'); ?>">
                                                            Start Exam
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <p><i class="fas fa-info-circle mr-2"></i> There are no CBT exams currently available for your class.</p>
                                    <p>Current class: <strong><?php echo htmlspecialchars($studentClass ?? 'Not set'); ?></strong></p>
                                    
                                    <!-- If no exams found, provide helpful info for debugging -->
                                    <hr>
                                    <p><small><i class="fas fa-question-circle"></i> If you believe this is an error, please ask your teacher to verify:</small></p>
                                    <ol class="small">
                                        <li>That exams have been created for your class "<?php echo htmlspecialchars($studentClass); ?>"</li>
                                        <li>That those exams are marked as "Active"</li>
                                    </ol>
                                </div>
                            <?php endif; ?>
                            
                            <h4 class="mt-5"><i class="fas fa-history"></i> My Past Exams</h4>
                            
                            <?php if (count($pastExams) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Title</th>
                                                <th>Date Taken</th>
                                                <th>Status</th>
                                                <th>Score</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pastExams as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td>
                                                    <?php echo isset($exam['completed_at']) 
                                                          ? date('M d, Y g:i A', strtotime($exam['completed_at'])) 
                                                          : (isset($exam['start_time']) 
                                                             ? date('M d, Y g:i A', strtotime($exam['start_time'])) . ' (Not submitted)' 
                                                             : 'N/A'); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $status = $exam['status'] ?? '';
                                                    switch ($status) {
                                                        case 'Completed':
                                                        case 'passed':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'In Progress':
                                                            $statusClass = 'warning';
                                                            break;
                                                        case 'failed':
                                                            $statusClass = 'danger';
                                                            break;
                                                        default:
                                                            $statusClass = 'secondary';
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (isset($exam['score']) && ($exam['show_results'] == 1)): ?>
                                                        <span class="badge badge-<?php echo ($exam['score'] >= $exam['passing_score']) ? 'success' : 'danger'; ?>">
                                                            <?php echo $exam['score']; ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Not available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="../../cbt/view_result.php?exam_id=<?php echo $exam['exam_id']; ?>&student_id=<?php echo $_SESSION['student_id']; ?>" 
                                                       class="btn btn-info btn-sm <?php echo (!$exam['show_results'] && $exam['status'] !== 'In Progress') ? 'disabled' : ''; ?>"
                                                       <?php echo (!$exam['show_results'] && $exam['status'] !== 'In Progress') ? 'title="Results not available yet"' : ''; ?>>
                                                        <i class="fas fa-eye"></i> View Result
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-secondary">
                                    <p><i class="fas fa-info-circle mr-2"></i> You haven't taken any CBT exams yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Payments Tab -->
                    <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                        <div class="dashboard-card">
                            <h4><i class="fas fa-money-bill-wave"></i> Payment History</h4>
                            
                            <?php
                            // Get payment history for this student
                            $payments_query = "SELECT * FROM payments 
                                             WHERE student_id = ? 
                                             ORDER BY payment_date DESC, created_at DESC";
                            
                            $stmt = $conn->prepare($payments_query);
                            $stmt->bind_param("i", $student_id);
                            $stmt->execute();
                            $payments_result = $stmt->get_result();
                            
                            if ($payments_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Payment Type</th>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Reference</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_paid = 0;
                                            while ($payment = $payments_result->fetch_assoc()): 
                                                if ($payment['status'] === 'completed') {
                                                    $total_paid += $payment['amount'];
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        $payment_type = str_replace('_', ' ', $payment['payment_type']);
                                                        echo ucwords($payment_type); 
                                                        ?>
                                                    </td>
                                                    <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                                    <td>
                                                        <?php 
                                                        $method = str_replace('_', ' ', $payment['payment_method']);
                                                        echo ucwords($method); 
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $payment['reference_number'] ? $payment['reference_number'] : '<span class="text-muted">-</span>'; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        switch ($payment['status']) {
                                                            case 'completed':
                                                                $status_class = 'success';
                                                                break;
                                                            case 'pending':
                                                                $status_class = 'warning';
                                                                break;
                                                            case 'failed':
                                                                $status_class = 'danger';
                                                                break;
                                                            default:
                                                                $status_class = 'secondary';
                                                        }
                                                        ?>
                                                        <span class="badge badge-<?php echo $status_class; ?>">
                                                            <?php echo ucfirst($payment['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-light">
                                                <td colspan="2"><strong>Total Paid:</strong></td>
                                                <td colspan="4"><strong>₦<?php echo number_format($total_paid, 2); ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <!-- Payment Summary Cards -->
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <div class="card bg-success text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Total Paid</h5>
                                                <h3 class="mb-0">₦<?php echo number_format($total_paid, 2); ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php
                                    // Get pending payments
                                    $pending_query = "SELECT SUM(amount) as pending_amount FROM payments 
                                                    WHERE student_id = ? AND status = 'pending'";
                                    $stmt = $conn->prepare($pending_query);
                                    $stmt->bind_param("i", $student_id);
                                    $stmt->execute();
                                    $pending_result = $stmt->get_result()->fetch_assoc();
                                    $pending_amount = $pending_result['pending_amount'] ?? 0;
                                    ?>
                                    
                                    <div class="col-md-4">
                                        <div class="card bg-warning text-dark">
                                            <div class="card-body">
                                                <h5 class="card-title">Pending Payments</h5>
                                                <h3 class="mb-0">₦<?php echo number_format($pending_amount, 2); ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card bg-info text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Last Payment</h5>
                                                <?php
                                                $payments_result->data_seek(0);
                                                $last_payment = $payments_result->fetch_assoc();
                                                if ($last_payment):
                                                ?>
                                                    <p class="mb-0">
                                                        ₦<?php echo number_format($last_payment['amount'], 2); ?><br>
                                                        <small><?php echo date('M d, Y', strtotime($last_payment['payment_date'])); ?></small>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="mb-0">No payments yet</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <p><i class="fas fa-info-circle mr-2"></i> No payment records found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Calendar Tab -->
                    <div class="tab-pane fade" id="calendar" role="tabpanel" aria-labelledby="calendar-tab">
                        <div class="dashboard-card">
                            <h4><i class="fas fa-calendar-alt"></i> Academic Calendar</h4>
                            <div class="calendar-container">
                                <div id="schoolCalendar"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Teacher Feedback Tab -->
                    <div class="tab-pane fade" id="teacher-feedback" role="tabpanel" aria-labelledby="teacher-feedback-tab">
                        <div class="dashboard-card">
                            <h4><i class="fas fa-clipboard-check"></i> Teacher Feedback</h4>
                            <?php if (count($teacherActivities) > 0 || count($teacherComments) > 0): ?>
                                <div class="row">
                                    <div class="col-md-12 mb-4">
                                        <h5><i class="fas fa-clipboard-list"></i> Activities</h5>
                                        <?php if (count($teacherActivities) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 15%">Date</th>
                                                        <th style="width: 15%">Type</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($teacherActivities as $activity): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></td>
                                                        <td>
                                                            <?php 
                                                            $typeClass = '';
                                                            switch ($activity['activity_type']) {
                                                                case 'attendance': $typeClass = 'badge-info'; break;
                                                                case 'behavioral': $typeClass = 'badge-warning'; break;
                                                                case 'academic': $typeClass = 'badge-success'; break;
                                                                case 'health': $typeClass = 'badge-danger'; break;
                                                                default: $typeClass = 'badge-secondary';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $typeClass; ?>">
                                                                <?php echo ucfirst($activity['activity_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $activity['description']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> No activities have been recorded for you by your class teacher.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-12 mb-4">
                                        <h5><i class="fas fa-comments"></i> Comments</h5>
                                        <?php if (count($teacherComments) > 0): ?>
                                        <div class="direct-chat-messages" style="height: auto;">
                                            <?php foreach ($teacherComments as $comment): ?>
                                            <div class="direct-chat-msg">
                                                <div class="direct-chat-infos clearfix">
                                                    <span class="direct-chat-name float-left">Class Teacher</span>
                                                    <span class="direct-chat-timestamp float-right">
                                                        <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                                    </span>
                                                </div>
                                                <div class="direct-chat-img">
                                                    <i class="fas fa-user-circle fa-2x"></i>
                                                </div>
                                                <div class="direct-chat-text">
                                                    <strong><?php echo ucfirst($comment['comment_type']); ?>:</strong>
                                                    <?php echo nl2br($comment['comment']); ?>
                                                    <?php if (!empty($comment['term'])): ?>
                                                    <small class="text-muted d-block mt-1">
                                                        Term: <?php echo $comment['term']; ?>, 
                                                        Session: <?php echo $comment['session']; ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> No comments have been added by your class teacher.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <p><i class="fas fa-info-circle mr-2"></i> No teacher feedback is available at this time.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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
    
    <!-- Add CBT Exams Section -->
    <!-- <div class="row" id="cbt-exams">
        <div class="col-md-12 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">CBT Exams</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="examTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="upcoming-tab" data-toggle="tab" href="#upcoming" role="tab">
                                Upcoming Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="completed-tab" data-toggle="tab" href="#completed" role="tab">
                                Completed Exams
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="examTabContent">
                        <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                            <?php if ($upcoming_exams->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Title</th>
                                                <th>Start Time</th>
                                                <th>Duration</th>
                                                <th>Questions</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($exam = $upcoming_exams->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($exam['created_at'])); ?></td>
                                                    <td><?php echo $exam['duration']; ?> minutes</td>
                                                    <td><?php echo htmlspecialchars($exam['question_count'] ?? 'Not specified'); ?> questions</td>
                                                    <td>
                                                        <?php if ($exam['is_active']): ?>
                                                            <a href="take_exam.php?exam_id=<?php echo $exam['id']; ?>" 
                                                               class="btn btn-primary btn-sm start-exam-btn"
                                                               data-exam-id="<?php echo $exam['id']; ?>"
                                                               data-exam-title="<?php echo htmlspecialchars($exam['title']); ?>"
                                                               data-exam-subject="<?php echo htmlspecialchars($exam['subject_name']); ?>"
                                                               data-exam-duration="<?php echo htmlspecialchars($exam['duration']); ?>"
                                                               data-exam-questions="<?php echo htmlspecialchars($exam['question_count'] ?? 'N/A'); ?>">
                                                                Start Exam
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-secondary btn-sm" disabled>Not Available</button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">No upcoming exams.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tab-pane fade" id="completed" role="tabpanel">
                            <?php if ($completed_exams->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Title</th>
                                                <th>Score</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($exam = $completed_exams->fetch_assoc()): ?>
                                                <?php 
                                                // Calculate score
                                                $score = 0;
                                                if (isset($exam['question_count']) && $exam['question_count'] > 0) {
                                                    $score = round(($exam['correct_answers'] / $exam['question_count']) * 100, 1);
                                                } elseif (isset($exam['score'])) {
                                                    $score = $exam['score'];
                                                }
                                                
                                                // Determine status class
                                                $statusClass = $score >= ($exam['passing_score'] ?? 50) ? 'success' : 'danger';
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                    <td>
                                                        <span class="text-<?php echo $statusClass; ?>">
                                                            <?php echo $score; ?>%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                                            <?php echo $score >= ($exam['passing_score'] ?? 50) ? 'Passed' : 'Failed'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="view_cbt_result.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-eye"></i> View Result
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">No completed exams.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> -->
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css" rel="stylesheet">

    <script>
        $(document).ready(function() {
            // Initialize charts and other UI components here
            try {
                // Academic progress chart
                var academicCtx = document.getElementById('academicChart');
                if (academicCtx) {
                    var academicChart = new Chart(academicCtx, {
                        type: 'line',
                        data: {
                            labels: ['Term 1', 'Term 2', 'Term 3'],
                            datasets: [{
                                label: 'Average Score',
                                data: [75, 82, 88],
                                backgroundColor: 'rgba(66, 133, 244, 0.2)',
                                borderColor: 'rgba(66, 133, 244, 1)',
                                borderWidth: 2,
                                pointBackgroundColor: 'rgba(66, 133, 244, 1)',
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        beginAtZero: true,
                                        max: 100
                                    }
                                }]
                            }
                        }
                    });
                }
                
                // Exam charts
                var examChartCtx = document.getElementById('examChart');
                if (examChartCtx) {
                    var examChart = new Chart(examChartCtx, {
                        type: 'bar',
                        data: {
                            labels: ['First Term', 'Second Term', 'Third Term'],
                            datasets: [{
                                label: 'Exam Performance',
                                data: [72, 85, 90],
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.7)',
                                    'rgba(54, 162, 235, 0.7)',
                                    'rgba(75, 192, 192, 0.7)'
                                ],
                                borderColor: [
                                    'rgba(255, 99, 132, 1)',
                                    'rgba(54, 162, 235, 1)',
                                    'rgba(75, 192, 192, 1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        beginAtZero: true,
                                        max: 100
                                    }
                                }]
                            }
                        }
                    });
                }
                
                // Exam doughnut chart
                var examDoughnutCtx = document.getElementById('examDoughnutChart');
                if (examDoughnutCtx) {
                    var examDoughnutChart = new Chart(examDoughnutCtx, {
                        type: 'doughnut',
                        data: {
                            datasets: [{
                                data: [<?php echo $passRate; ?>, <?php echo 100 - $passRate; ?>],
                                backgroundColor: [
                                    'rgba(75, 192, 192, 0.7)',
                                    'rgba(201, 203, 207, 0.3)'
                                ],
                                borderColor: [
                                    'rgba(75, 192, 192, 1)',
                                    'rgba(201, 203, 207, 0.5)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            cutoutPercentage: 70,
                            legend: {
                                display: false
                            },
                            tooltips: {
                                enabled: false
                            }
                        }
                    });
                }
                
                // Payment chart
                var paymentChartCtx = document.getElementById('paymentChart');
                if (paymentChartCtx) {
                    var paymentChart = new Chart(paymentChartCtx, {
                        type: 'pie',
                        data: {
                            labels: ['Tuition', 'Books', 'Uniform', 'Others'],
                            datasets: [{
                                data: [60, 15, 10, 15],
                                backgroundColor: [
                                    'rgba(54, 162, 235, 0.7)',
                                    'rgba(255, 206, 86, 0.7)',
                                    'rgba(75, 192, 192, 0.7)',
                                    'rgba(153, 102, 255, 0.7)'
                                ],
                                borderColor: [
                                    'rgba(54, 162, 235, 1)',
                                    'rgba(255, 206, 86, 1)',
                                    'rgba(75, 192, 192, 1)',
                                    'rgba(153, 102, 255, 1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true
                        }
                    });
                }
                
                // Initialize calendar if element exists
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
                                title: 'First Term Begins',
                                start: '2023-09-11',
                                allDay: true,
                                backgroundColor: '#4285f4'
                            },
                            {
                                title: 'First Term Exam',
                                start: '2023-12-04',
                                end: '2023-12-15',
                                backgroundColor: '#ea4335'
                            },
                            {
                                title: 'Christmas Break',
                                start: '2023-12-18',
                                end: '2024-01-08',
                                backgroundColor: '#34a853'
                            }
                        ]
                    });
                    calendar.render();
                }
            } catch (error) {
                console.error("Error initializing charts or calendar:", error);
            }
        });
    </script>

    <!-- Add script to update JavaScript to handle deep linking and anchor navigation -->
    <script>
        $(document).ready(function() {
            // Update all tab link event handlers
            $('.btn[data-toggle="tab"]').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $('.sidebar .nav-link[href="'+target+'"]').tab('show');
            });
            
            // Back/Forward button support for tabs
            window.addEventListener('popstate', function(event) {
                var hash = window.location.hash;
                if (hash) {
                    $('.sidebar .nav-link[href="'+hash+'"]').tab('show');
                } else {
                    $('.sidebar .nav-link:first').tab('show');
                }
            });
            
            // Set target when coming from announcement link
            $('a[href="#announcements"]').on('click', function() {
                $('.sidebar .nav-link[href="#announcements"]').tab('show');
                return false;
            });
            
            // Handle exam start buttons
            $('.start-exam-btn').on('click', function(e) {
                e.preventDefault();
                var examId = $(this).data('exam-id');
                var examTitle = $(this).data('exam-title');
                var examSubject = $(this).data('exam-subject');
                var examDuration = $(this).data('exam-duration');
                var examQuestions = $(this).data('exam-questions');
                var examUrl = $(this).attr('href');
                
                // Set modal content
                $('#modal-subject').text(examSubject);
                $('#modal-title').text(examTitle);
                $('#modal-duration').text(examDuration + ' minutes');
                $('#modal-questions').text(examQuestions + ' questions');
                
                // Set start button URL
                $('#startExamBtn').attr('href', examUrl);
                
                // Show modal
                $('#examInstructionsModal').modal('show');
            });
        });
    </script>

    <!-- Exam Instructions Modal -->
    <div class="modal fade" id="examInstructionsModal" tabindex="-1" role="dialog" aria-labelledby="examInstructionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="examInstructionsModalLabel">Exam Instructions</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Please read all instructions carefully before starting the exam.
                    </div>
                    
                    <h5 class="font-weight-bold mb-3">General Instructions:</h5>
                    <ol>
                        <li>This is a timed exam. Once you start, the timer cannot be paused.</li>
                        <li>Do not refresh the page or navigate away during the exam.</li>
                        <li>Ensure you have a stable internet connection before starting.</li>
                        <li>Answer all questions. You can review your answers before final submission.</li>
                        <li>Click "Submit Exam" when you are done to record your answers.</li>
                    </ol>
                    
                    <h5 class="font-weight-bold mb-3 mt-4">Exam Details:</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">Subject:</th>
                            <td id="modal-subject"></td>
                        </tr>
                        <tr>
                            <th>Title:</th>
                            <td id="modal-title"></td>
                        </tr>
                        <tr>
                            <th>Duration:</th>
                            <td id="modal-duration"></td>
                        </tr>
                        <tr>
                            <th>Total Questions:</th>
                            <td id="modal-questions"></td>
                        </tr>
                    </table>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle mr-2"></i> Once you click "Start Exam", you will be redirected to the exam page.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="#" id="startExamBtn" class="btn btn-primary">Start Exam</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Handle file input change for passport photo
        $('#passport_photo').on('change', function() {
            const file = this.files[0];
            const preview = $('#imagePreview img');
            const previewContainer = $('#imagePreview');
            
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, JPEG, or PNG)');
                    this.value = '';
                    previewContainer.hide();
                    return;
                }
                
                // Validate file size (2MB max)
                const maxSize = 2 * 1024 * 1024; // 2MB in bytes
                if (file.size > maxSize) {
                    alert('File size should not exceed 2MB');
                    this.value = '';
                    previewContainer.hide();
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.attr('src', e.target.result);
                    previewContainer.show();
                }
                reader.readAsDataURL(file);
                
                // Update file input label
                $(this).next('.custom-file-label').html(file.name);
            } else {
                previewContainer.hide();
                $(this).next('.custom-file-label').html('Choose file...');
            }
        });
        
        // Handle form submission
        $('#passportForm').on('submit', function(e) {
            e.preventDefault();
            
            const fileInput = $('#passport_photo')[0];
            if (!fileInput.files.length) {
                alert('Please select a photo to upload');
                return;
            }
            
            const formData = new FormData(this);
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Uploading...').prop('disabled', true);
            
            $.ajax({
                url: 'update_passport.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        
                        if (data.status === 'success') {
                            // Update all instances of the student photo on the page
                            const newPhotoPath = '../../../' + data.photo_path;
                            $('.student-photo, .profile-image').attr('src', newPhotoPath);
                            
                            // Show success message
                            alert('Photo uploaded successfully!');
                            
                            // Close the modal
                            $('#changePassportModal').modal('hide');
                            
                            // Reload the page to reflect all changes
                            location.reload();
                        } else {
                            alert('Error uploading photo: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error processing response: ' + response);
                    }
                    
                    // Reset button state
                    submitBtn.html(originalText).prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    alert('An error occurred while uploading the photo: ' + error);
                    submitBtn.html(originalText).prop('disabled', false);
                }
            });
        });
    });
    </script>
</body>
</html>