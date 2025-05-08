<?php
// Student Dashboard
session_start();

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';
require_once '../auth.php';

// Require student login
requireLogin('student', '../../login.php');

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Get student information
$userId = $_SESSION[SESSION_PREFIX . 'user_id'];
$profileId = $_SESSION[SESSION_PREFIX . 'profile_id'];

try {
    // Get student details
    $stmt = $conn->prepare("SELECT s.*, c.name as class_name, c.section
                           FROM students s
                           LEFT JOIN classes c ON s.class_id = c.id
                           WHERE s.id = :profile_id");
    $stmt->bindParam(':profile_id', $profileId);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get current academic session
    $currentSession = getCurrentAcademicSession($conn);
    
    // Get announcements for students
    $stmt = $conn->prepare("SELECT * FROM announcements 
                          WHERE (visibility = 'Student' OR visibility = 'All')
                          AND expiry_date >= CURDATE()
                          ORDER BY published_date DESC LIMIT 5");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming exams
    $stmt = $conn->prepare("SELECT e.*, s.name as subject_name
                          FROM exams e
                          JOIN class_subjects cs ON e.subject_id = cs.subject_id
                          JOIN subjects s ON cs.subject_id = s.id
                          WHERE cs.class_id = :class_id
                          AND e.start_date >= CURDATE()
                          ORDER BY e.start_date ASC LIMIT 5");
    $stmt->bindParam(':class_id', $student['class_id']);
    $stmt->execute();
    $upcomingExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance summary
    $stmt = $conn->prepare("SELECT 
                            COUNT(*) as total_days,
                            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days
                          FROM attendance
                          WHERE student_id = :student_id
                          AND attendance_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()");
    $stmt->bindParam(':student_id', $profileId);
    $stmt->execute();
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate attendance percentage
    $attendancePercentage = 0;
    if ($attendance['total_days'] > 0) {
        $attendancePercentage = round(($attendance['present_days'] / $attendance['total_days']) * 100);
    }
    
    // Get timetable for today
    $dayOfWeek = date('l');
    $stmt = $conn->prepare("SELECT t.*, s.name as subject_name, s.code as subject_code, 
                          CONCAT(tc.first_name, ' ', tc.last_name) as teacher_name
                          FROM timetable t
                          JOIN subjects s ON t.subject_id = s.id
                          LEFT JOIN teachers tc ON t.teacher_id = tc.id
                          WHERE t.class_id = :class_id 
                          AND t.day_of_week = :day_of_week
                          ORDER BY t.start_time ASC");
    $stmt->bindParam(':class_id', $student['class_id']);
    $stmt->bindParam(':day_of_week', $dayOfWeek);
    $stmt->execute();
    $todayTimetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent grades
    $stmt = $conn->prepare("SELECT g.*, s.name as subject_name, e.title as exam_title
                          FROM grades g
                          JOIN subjects s ON g.subject_id = s.id
                          JOIN exams e ON g.exam_id = e.id
                          WHERE g.student_id = :student_id
                          ORDER BY g.id DESC LIMIT 5");
    $stmt->bindParam(':student_id', $profileId);
    $stmt->execute();
    $recentGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread notifications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications 
                          WHERE (user_id = :user_id OR role = 'student' OR role = 'all')
                          AND is_read = 0");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $unreadNotifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get CBT exams for student
    $stmt = $conn->prepare("SELECT ce.*, s.name as subject_name,
                          (SELECT COUNT(*) FROM cbt_student_exams WHERE student_id = :student_id AND exam_id = ce.id) as attempted
                          FROM cbt_exams ce
                          JOIN subjects s ON ce.subject_id = s.id
                          WHERE ce.class_id = :class_id
                          AND ce.is_active = 1
                          AND ce.start_datetime <= NOW()
                          AND ce.end_datetime >= NOW()
                          ORDER BY ce.start_datetime ASC");
    $stmt->bindParam(':student_id', $profileId);
    $stmt->bindParam(':class_id', $student['class_id']);
    $stmt->execute();
    $availableCbtExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Page title
$pageTitle = "Student Dashboard";
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
        
        .user-welcome {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .attendance-chart {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            position: relative;
        }
        
        .attendance-chart .percentage {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .timetable-item {
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f8f9fa;
        }
        
        .announcement-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .exam-item {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .exam-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .subject-grade {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .grade-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="#"><?php echo APP_NAME; ?></a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="user-welcome text-white px-3">
            <img src="<?php echo !empty($_SESSION[SESSION_PREFIX . 'profile_image']) ? $_SESSION[SESSION_PREFIX . 'profile_image'] : DEFAULT_STUDENT_IMAGE; ?>" alt="Profile" class="user-avatar">
            <span>Welcome, <?php echo $_SESSION[SESSION_PREFIX . 'name']; ?></span>
        </div>
        
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
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="timetable.php">
                                <i class="fas fa-calendar-alt"></i> Class Timetable
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="attendance.php">
                                <i class="fas fa-clipboard-check"></i> My Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="grades.php">
                                <i class="fas fa-graduation-cap"></i> Grades & Results
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exams.php">
                                <i class="fas fa-file-alt"></i> Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cbt.php">
                                <i class="fas fa-laptop"></i> CBT Exams
                                <?php if (count($availableCbtExams) > 0): ?>
                                <span class="badge bg-danger"><?php echo count($availableCbtExams); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="fees.php">
                                <i class="fas fa-money-bill-wave"></i> Fees & Payments
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
                            <a class="nav-link" href="notifications.php">
                                <i class="fas fa-bell"></i> Notifications
                                <?php if ($unreadNotifications > 0): ?>
                                <span class="badge bg-danger"><?php echo $unreadNotifications; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                    
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Account</span>
                    </h6>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link" href="change_password.php">
                                <i class="fas fa-key"></i> Change Password
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
                    <h1 class="h2">Student Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar"></i> <?php echo date('F Y'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-2 text-center">
                                        <img src="<?php echo !empty($_SESSION[SESSION_PREFIX . 'profile_image']) ? $_SESSION[SESSION_PREFIX . 'profile_image'] : DEFAULT_STUDENT_IMAGE; ?>" alt="Profile" class="img-fluid rounded-circle mb-3" style="max-width: 120px;">
                                    </div>
                                    <div class="col-md-5">
                                        <h5><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h5>
                                        <p class="text-muted mb-1">Admission No: <?php echo $student['admission_number']; ?></p>
                                        <p class="text-muted mb-1">Class: <?php echo $student['class_name'] . ' ' . $student['section']; ?></p>
                                        <p class="text-muted mb-1">Roll Number: <?php echo $student['roll_number']; ?></p>
                                        <p class="text-muted mb-0">
                                            <span class="badge bg-info">Student</span>
                                        </p>
                                    </div>
                                    <div class="col-md-5">
                                        <h6>Current Academic Session:</h6>
                                        <p class="text-muted mb-1"><?php echo $currentSession ? $currentSession['name'] : 'Not set'; ?></p>
                                        
                                        <h6 class="mt-3">Attendance Overview (Last 30 days):</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="attendance-chart">
                                                    <canvas id="attendanceChart" width="100" height="100"></canvas>
                                                    <div class="percentage"><?php echo $attendancePercentage; ?>%</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1">Present: <?php echo $attendance['present_days'] ?? 0; ?> days</p>
                                                <p class="mb-1">Absent: <?php echo $attendance['absent_days'] ?? 0; ?> days</p>
                                                <p class="mb-0">Late: <?php echo $attendance['late_days'] ?? 0; ?> days</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <!-- Today's Timetable -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Today's Timetable (<?php echo date('l'); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($todayTimetable)): ?>
                                    <p class="text-center">No classes scheduled for today.</p>
                                <?php else: ?>
                                    <?php foreach ($todayTimetable as $class): ?>
                                        <div class="timetable-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $class['subject_name']; ?> (<?php echo $class['subject_code']; ?>)</h6>
                                                    <p class="mb-0 small"><?php echo $class['teacher_name']; ?></p>
                                                </div>
                                                <div class="text-end">
                                                    <p class="mb-0 fw-bold"><?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?></p>
                                                    <p class="mb-0 small">Room: <?php echo $class['room_number']; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="timetable.php" class="btn btn-sm btn-primary">View Full Timetable</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Announcements -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">Announcements</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($announcements)): ?>
                                    <p class="text-center">No announcements available.</p>
                                <?php else: ?>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="announcement mb-3">
                                            <h6><?php echo $announcement['title']; ?></h6>
                                            <p class="mb-1"><?php echo substr($announcement['content'], 0, 100) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></p>
                                            <p class="announcement-date mb-0">
                                                <i class="fas fa-calendar-alt"></i> <?php echo formatDate($announcement['published_date']); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="announcements.php" class="btn btn-sm btn-info">View All Announcements</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Upcoming Exams -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">Upcoming Exams</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcomingExams)): ?>
                                    <p class="text-center">No upcoming exams scheduled.</p>
                                <?php else: ?>
                                    <?php foreach ($upcomingExams as $exam): ?>
                                        <div class="exam-item">
                                            <h6><?php echo $exam['title']; ?></h6>
                                            <p class="mb-1"><?php echo $exam['subject_name']; ?></p>
                                            <p class="exam-date mb-0">
                                                <i class="fas fa-calendar-alt"></i> <?php echo formatDate($exam['start_date']); ?> to <?php echo formatDate($exam['end_date']); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="exams.php" class="btn btn-sm btn-warning">View All Exams</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Grades -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">Recent Grades</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentGrades)): ?>
                                    <p class="text-center">No grades available yet.</p>
                                <?php else: ?>
                                    <?php foreach ($recentGrades as $grade): 
                                        $percentage = ($grade['marks_obtained'] / $grade['max_marks']) * 100;
                                        $gradeInfo = getGrade($percentage);
                                        $bgColor = '';
                                        
                                        if ($percentage >= 80) {
                                            $bgColor = 'bg-success text-white';
                                        } elseif ($percentage >= 60) {
                                            $bgColor = 'bg-info text-white';
                                        } elseif ($percentage >= 40) {
                                            $bgColor = 'bg-warning';
                                        } else {
                                            $bgColor = 'bg-danger text-white';
                                        }
                                    ?>
                                        <div class="subject-grade">
                                            <div>
                                                <h6 class="mb-0"><?php echo $grade['subject_name']; ?></h6>
                                                <small class="text-muted"><?php echo $grade['exam_title']; ?></small>
                                            </div>
                                            <div class="text-center">
                                                <span class="grade-badge <?php echo $bgColor; ?>">
                                                    <?php echo $grade['marks_obtained']; ?>/<?php echo $grade['max_marks']; ?>
                                                    (<?php echo $gradeInfo['grade']; ?>)
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="grades.php" class="btn btn-sm btn-success">View All Grades</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- CBT Exams -->
                <?php if (!empty($availableCbtExams)): ?>
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card shadow border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">Available Online Exams (CBT)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Exam Title</th>
                                                <th>Subject</th>
                                                <th>Duration</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($availableCbtExams as $exam): ?>
                                            <tr>
                                                <td><?php echo $exam['title']; ?></td>
                                                <td><?php echo $exam['subject_name']; ?></td>
                                                <td><?php echo $exam['time_limit']; ?> minutes</td>
                                                <td><?php echo formatDate($exam['start_datetime'], 'M d, Y h:i A'); ?></td>
                                                <td><?php echo formatDate($exam['end_datetime'], 'M d, Y h:i A'); ?></td>
                                                <td>
                                                    <?php if ($exam['attempted'] > 0): ?>
                                                        <span class="badge bg-success">Attempted</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Not Attempted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($exam['attempted'] > 0): ?>
                                                        <a href="view_cbt_result.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">View Result</a>
                                                    <?php else: ?>
                                                        <a href="take_cbt_exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-danger">Take Exam</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Attendance chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceData = {
            datasets: [{
                data: [
                    <?php echo $attendance['present_days'] ?? 0; ?>,
                    <?php echo $attendance['absent_days'] ?? 0; ?>,
                    <?php echo $attendance['late_days'] ?? 0; ?>
                ],
                backgroundColor: [
                    '#28a745',
                    '#dc3545',
                    '#ffc107'
                ],
                borderWidth: 0
            }]
        };
        
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'doughnut',
            data: attendanceData,
            options: {
                cutout: '70%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false
                    }
                }
            }
        });
    </script>
</body>
</html> 