<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../utils.php';  // Add this line to include the utils file

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

// Process form submission

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
    
    <!-- Custom Tab Navigation Script - Must be in head -->
    <script>
        // Simple custom tab system - defined globally
        function showTab(tabName) {
            console.log('Showing tab: ' + tabName);
            
            // Debug - list all available tab IDs
            var allTabElements = document.querySelectorAll('.tab-pane');
            console.log('Available tabs:');
            for (var i = 0; i < allTabElements.length; i++) {
                console.log(' - ' + allTabElements[i].id);
            }
            
            // Hide all tabs
            var allTabs = document.querySelectorAll('.tab-pane');
            for (var i = 0; i < allTabs.length; i++) {
                allTabs[i].style.display = 'none';
            }
            
            // Show the selected tab
            var selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.style.display = 'block';
                console.log('Successfully showed tab: ' + tabName);
            } else {
                console.error('Tab not found: ' + tabName);
                
                // Try direct case-insensitive matching first
                var tabFound = false;
                allTabElements.forEach(function(tab) {
                    if (tab.id.toLowerCase() === tabName.toLowerCase()) {
                        tab.style.display = 'block';
                        console.log('Found case-insensitive match: ' + tab.id);
                        tabFound = true;
                    }
                });
                
                if (!tabFound) {
                    // Try to find tab with similar ID
                    allTabElements.forEach(function(tab) {
                        if (tab.id.toLowerCase().includes(tabName.toLowerCase())) {
                            tab.style.display = 'block';
                            console.log('Found similar tab: ' + tab.id);
                            tabFound = true;
                        }
                    });
                }
                
                if (!tabFound) {
                    // Default to dashboard if no similar tab found
                    var dashboard = document.getElementById('dashboard');
                    if (dashboard) {
                        dashboard.style.display = 'block';
                    }
                }
            }
            
            // Update navigation active state
            var navLinks = document.querySelectorAll('.sidebar .nav-link');
            for (var i = 0; i < navLinks.length; i++) {
                navLinks[i].classList.remove('active');
            }
            
            var activeLink = document.getElementById(tabName + '-link');
            if (activeLink) {
                activeLink.classList.add('active');
            }
            
            // Close mobile sidebar if needed
            if (window.innerWidth < 992) {
                var sidebar = document.querySelector('.sidebar');
                if (sidebar) {
                    sidebar.classList.remove('mobile-active');
                }
            }
            
            // If profile tab is selected, show default subtab
            if (tabName === 'profile') {
                setTimeout(function() {
                    showProfileTab('personal-info');
                }, 50);
            }
            
            return false;
        }
        
        // Function to handle profile subtabs
        function showProfileTab(subtabId) {
            console.log('Showing profile subtab: ' + subtabId);
            
            // Hide all subtabs
            var allSubtabs = document.querySelectorAll('#profileTabsContent .tab-pane');
            for (var i = 0; i < allSubtabs.length; i++) {
                allSubtabs[i].style.display = 'none';
            }
            
            // Show selected subtab
            var selectedSubtab = document.getElementById(subtabId);
            if (selectedSubtab) {
                selectedSubtab.style.display = 'block';
            } else {
                console.error('Subtab not found: ' + subtabId);
                return false;
            }
            
            // Update active link
            var subtabLinks = document.querySelectorAll('#profileTabs .nav-link');
            for (var i = 0; i < subtabLinks.length; i++) {
                subtabLinks[i].classList.remove('active');
            }
            
            var activeSubtabLink = document.querySelector('#profileTabs .nav-link[href="#' + subtabId + '"]');
            if (activeSubtabLink) {
                activeSubtabLink.classList.add('active');
            }
            
            return false;
        }
        
        // Mobile sidebar toggle
        function toggleSidebar() {
            console.log('Toggling sidebar');
            var sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('mobile-active');
            } else {
                console.error('Sidebar element not found');
            }
            return false;
        }
        
        // Initialize when page is fully loaded
        window.addEventListener('load', function() {
            console.log('Page fully loaded - initializing tabs');
            
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
                
                /* Tab display properties */
                .tab-pane {
                    display: none;
                }
                
                #dashboard.tab-pane.active {
                    display: block;
                }
            `;
            document.head.appendChild(style);
            
            // Show dashboard by default
            setTimeout(function() {
                showTab('dashboard');
            }, 100);
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

        /* Force tab content to display properly */
        .tab-content > .tab-pane {
            display: none;
        }
        
        .tab-content > .active {
            display: block !important;
        }
        
        /* Additional styles to ensure tab content is visible */
        .show {
            display: block !important;
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
    </style>
</head>
<body>
    <!-- Toggle Button for Responsive Menu -->
    <button class="nav-toggle" id="navToggle" onclick="toggleSidebar(); return false;">
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
                        <a class="nav-link active" id="dashboard-link" href="#" onclick="javascript:showTab('dashboard'); return false;">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="profile-link" href="#" onclick="javascript:showTab('profile'); return false;">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="exams-link" href="#" onclick="javascript:showTab('exams'); return false;">
                            <i class="fas fa-file-alt"></i> Exams & Results
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="cbt-exams-link" href="#" onclick="javascript:showTab('cbt-exams'); return false;">
                            <i class="fas fa-laptop"></i> CBT Exams
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="payments-link" href="#" onclick="javascript:showTab('payments'); return false;">
                            <i class="fas fa-money-bill-wave"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="calendar-link" href="#" onclick="javascript:showTab('calendar'); return false;">
                            <i class="fas fa-calendar-alt"></i> Calendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="announcements-link" href="#" onclick="javascript:showTab('announcements'); return false;">
                            <i class="fas fa-bullhorn"></i> Announcements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="teacher-feedback-link" href="#" onclick="javascript:showTab('teacher-feedback'); return false;">
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
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <h5><i class="fas fa-bug"></i> Raw Student Data</h5>
                    <div>
                        <pre><?php print_r($_SESSION['debug_raw_student']); ?></pre>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['student_debug']) && ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1')): ?>
                <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                    <h5><i class="fas fa-info-circle"></i> Student Data Debug</h5>
                    <div>
                        <pre><?php print_r($_SESSION['student_debug']); ?></pre>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Tab Content -->
                <div class="tab-content" id="mainTabContent" style="min-height: 500px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane show active" id="dashboard">
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
                    <div class="tab-pane" id="profile">
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
                                            <a class="nav-link active" id="personal-tab" onclick="showProfileTab('personal-info'); return false;" href="#personal-info" role="tab">
                                                <i class="fas fa-user mr-2"></i> Personal
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="family-tab" onclick="showProfileTab('family-info'); return false;" href="#family-info" role="tab">
                                                <i class="fas fa-users mr-2"></i> Family
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="medical-tab" onclick="showProfileTab('medical-info'); return false;" href="#medical-info" role="tab">
                                                <i class="fas fa-heartbeat mr-2"></i> Medical
                                            </a>
                                        </li>
                                    </ul>
                                    
                                    <div class="tab-content profile-tab-content" id="profileTabsContent">
                                        <!-- Personal Information Tab -->
                                        <div class="tab-pane fade show active" id="personal-info" role="tabpanel">
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
                                        <div class="tab-pane fade" id="family-info" role="tabpanel">
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
                                        <div class="tab-pane fade" id="medical-info" role="tabpanel">
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
                    <div class="tab-pane" id="exams">
                        <!-- Available CBT Exams Section -->
                        <div class="dashboard-card mb-4">
                            <h4><i class="fas fa-pen-fancy"></i> Available CBT Exams</h4>
                            
                            <?php
                            // Get student's class
                            $student_id = $_SESSION['student_id'];
                            $classQuery = "SELECT class FROM students WHERE id = ?";
                            $stmt = $conn->prepare($classQuery);
                            $stmt->bind_param("i", $student_id);
                            $stmt->execute();
                            $classResult = $stmt->get_result();
                            $studentClass = "";
                            
                            if ($row = $classResult->fetch_assoc()) {
                                $studentClass = $row['class'];
                            }
                            
                            // Get available exams for this student's class
                            $examsQuery = "SELECT e.*, s.name AS subject_name,
                                          (SELECT COUNT(*) FROM cbt_student_exams WHERE exam_id = e.id AND student_id = ?) AS attempt_count 
                                          FROM cbt_exams e
                                          JOIN subjects s ON e.subject_id = s.id
                                          WHERE (e.class_id = ? OR e.class_id = ?)
                                          AND e.is_active = 1
                                          AND NOW() BETWEEN e.start_datetime AND e.end_datetime
                                          ORDER BY e.start_datetime";
                            
                            $stmt = $conn->prepare($examsQuery);
                            $stmt->bind_param("iss", $student_id, $studentClass, $studentClass);
                            $stmt->execute();
                            $examsResult = $stmt->get_result();
                            
                            if ($examsResult->num_rows > 0):
                            ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Exam Title</th>
                                            <th>Subject</th>
                                            <th>Questions</th>
                                            <th>Duration</th>
                                            <th>Available Until</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($exam = $examsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['total_questions']); ?> questions</td>
                                                <td><?php echo htmlspecialchars($exam['time_limit']); ?> minutes</td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($exam['end_datetime'])); ?></td>
                                                <td>
                                                    <?php if ($exam['attempt_count'] > 0): ?>
                                                        <span class="badge badge-success">Attempted</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">Not Attempted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($exam['attempt_count'] == 0): ?>
                                                        <a href="../cbt/take_exam.php?exam_id=<?php echo $exam['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i> Take Exam
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="../cbt/view_result.php?exam_id=<?php echo $exam['id']; ?>" 
                                                           class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i> View Result
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No active exams available for your class at this time.
                                </div>
                            <?php endif; ?>
                        </div>
                        
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
                    <div class="tab-pane" id="payments">
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
                                                <td><?php echo formatPaymentType($payment['payment_type']); ?></td>
                                                <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo ucfirst(htmlspecialchars($payment['payment_method'])); ?></td>
                                                <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                                                <td><?php echo formatPaymentStatus($payment['status']); ?></td>
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
                    <div class="tab-pane" id="calendar">
                        <div class="dashboard-card">
                            <h4><i class="fas fa-calendar"></i> School Calendar</h4>
                            <div class="calendar-container">
                                <div id="schoolCalendar"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Teacher Feedback Tab -->
                    <div class="tab-pane" id="teacher-feedback">
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="dashboard-card card-warning">
                                    <h4><i class="fas fa-clipboard-list"></i> Teacher Activities</h4>
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
                    
                    <!-- CBT Exams Tab -->
                    <div class="tab-pane" id="cbt-exams">
                        <div class="dashboard-card mb-4">
                            <h4><i class="fas fa-laptop"></i> Available CBT Exams</h4>
                            
                            <?php
                            // Get current date and time
                            $currentDateTime = date('Y-m-d H:i:s');
                            
                            // Get available exams for this student's class
                            $availableExamsQuery = "SELECT e.*, s.name AS subject_name,
                                                   (SELECT COUNT(*) FROM cbt_student_exams 
                                                    WHERE exam_id = e.id AND student_id = ?) AS has_attempted
                                                   FROM cbt_exams e
                                                   JOIN subjects s ON e.subject_id = s.id
                                                   JOIN class_subjects cs ON s.id = cs.subject_id
                                                   JOIN students st ON st.class = cs.class_id
                                                   WHERE e.is_active = 1
                                                   AND e.start_datetime <= ?
                                                   AND e.end_datetime >= ?
                                                   AND st.id = ?
                                                   ORDER BY e.end_datetime ASC";
                            
                            $stmt = $conn->prepare($availableExamsQuery);
                            $stmt->bind_param("issi", $student_id, $currentDateTime, $currentDateTime, $student_id);
                            $stmt->execute();
                            $availableExamsResult = $stmt->get_result();
                            $availableExams = [];
                            while ($row = $availableExamsResult->fetch_assoc()) {
                                $availableExams[] = $row;
                            }
                            
                            // Get past exams for this student
                            $pastExamsQuery = "SELECT e.*, s.name AS subject_name, 
                                              se.status, se.score, se.started_at, se.submitted_at,
                                              (CASE WHEN e.show_results = 1 THEN se.score ELSE NULL END) AS visible_score
                                              FROM cbt_student_exams se
                                              JOIN cbt_exams e ON se.exam_id = e.id
                                              JOIN subjects s ON e.subject_id = s.id
                                              WHERE se.student_id = ?
                                              ORDER BY se.submitted_at DESC";
                            
                            $stmt = $conn->prepare($pastExamsQuery);
                            $stmt->bind_param("i", $student_id);
                            $stmt->execute();
                            $pastExamsResult = $stmt->get_result();
                            $pastExams = [];
                            while ($row = $pastExamsResult->fetch_assoc()) {
                                $pastExams[] = $row;
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
                                                <th>Available Until</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($availableExams as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td><?php echo $exam['time_limit']; ?> minutes</td>
                                                <td><?php echo $exam['total_questions']; ?> questions</td>
                                                <td>
                                                    <span class="text-danger">
                                                        <?php echo date('M d, Y g:i A', strtotime($exam['end_datetime'])); ?>
                                                    </span>
                                                    <?php 
                                                    // Calculate time remaining
                                                    $endTime = new DateTime($exam['end_datetime']);
                                                    $now = new DateTime();
                                                    $timeRemaining = $now->diff($endTime);
                                                    $hoursRemaining = $timeRemaining->h + ($timeRemaining->days * 24);
                                                    
                                                    if ($hoursRemaining < 24) {
                                                        echo '<span class="badge badge-warning">Less than 24 hours left!</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($exam['has_attempted'] > 0): ?>
                                                        <span class="badge badge-info">Attempted</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="take_cbt_exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                        <?php echo ($exam['has_attempted'] > 0) ? 'Continue Exam' : 'Start Exam'; ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <p>There are no CBT exams currently available for you to take.</p>
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
                                                <td><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td>
                                                    <?php echo $exam['submitted_at'] 
                                                          ? date('M d, Y g:i A', strtotime($exam['submitted_at'])) 
                                                          : date('M d, Y g:i A', strtotime($exam['started_at'])) . ' (Not submitted)'; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($exam['status']) {
                                                        case 'Completed':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'In Progress':
                                                            $statusClass = 'warning';
                                                            break;
                                                        default:
                                                            $statusClass = 'secondary';
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo $exam['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($exam['visible_score'] !== null): ?>
                                                        <span class="badge badge-<?php echo ($exam['score'] >= $exam['passing_score']) ? 'success' : 'danger'; ?>">
                                                            <?php echo $exam['score']; ?>%
                                                        </span>
                                                        <?php if ($exam['score'] >= $exam['passing_score']): ?>
                                                            <i class="fas fa-check-circle text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times-circle text-danger"></i>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if ($exam['status'] === 'Completed'): ?>
                                                            <span class="badge badge-secondary">Results pending</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Not completed</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($exam['visible_score'] !== null || $exam['status'] === 'In Progress'): ?>
                                                        <a href="view_cbt_result.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-info btn-sm">
                                                            View Details
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary btn-sm" disabled>
                                                            Not Available
                                                        </button>
                                                    <?php endif; ?>
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
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Announcements Tab -->
                    <div class="tab-pane" id="announcements">
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
                                <div class="alert alert-info">
                                    <p class="mb-0">No announcements are currently available.</p>
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
            // This script will run after our custom tab system
        });
    </script>
</body>
</html>
