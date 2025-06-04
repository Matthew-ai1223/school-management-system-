<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Handle subject selection form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_subjects'])) {
    try {
        // Validate number of subjects selected
        if (!isset($_POST['subjects']) || !is_array($_POST['subjects'])) {
            throw new Exception("Please select at least one subject.");
        }

        // Remove any duplicate selections
        $selectedSubjects = array_unique($_POST['subjects']);

        // Check if more than 5 subjects are selected
        if (count($selectedSubjects) > 5) {
            throw new Exception("You can only select up to 5 subjects.");
        }

        // Begin transaction
        $db->beginTransaction();
        
        // Delete existing subjects for this teacher
        $deleteStmt = $db->prepare("DELETE FROM teacher_subjects WHERE teacher_id = :teacher_id");
        $deleteStmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
        
        // Insert new selected subjects
        $insertStmt = $db->prepare("INSERT INTO teacher_subjects (teacher_id, subject, created_at) VALUES (:teacher_id, :subject, NOW())");
        
        foreach ($selectedSubjects as $subject) {
            $insertStmt->execute([
                ':teacher_id' => $_SESSION['teacher_id'],
                ':subject' => $subject
            ]);
        }
        
        // Commit transaction
        $db->commit();
        $_SESSION['success_message'] = "Successfully assigned " . count($selectedSubjects) . " subjects!";
    } catch (Exception $e) {
        // Rollback on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    // Redirect to refresh the page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get all available subjects
try {
    $stmt = $db->query("SELECT subject_name, subject_code, category, is_compulsory FROM all_subjects ORDER BY category, subject_name");
    $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($all_subjects)) {
        error_log("No subjects found in the database");
    }

    // Group subjects by category
    $subjects_by_category = [];
    foreach ($all_subjects as $subject) {
        $category = $subject['category'] ?: 'Uncategorized';
        if (!isset($subjects_by_category[$category])) {
            $subjects_by_category[$category] = [];
        }
        $subjects_by_category[$category][] = $subject;
    }
} catch (PDOException $e) {
    error_log("Error fetching all subjects: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    $subjects_by_category = [];
}

// Get teacher's currently assigned subjects
try {
    $stmt = $db->prepare("SELECT subject FROM teacher_subjects WHERE teacher_id = :teacher_id");
    $stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
    $assigned_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($assigned_subjects)) {
        error_log("No assigned subjects found for teacher_id: " . $_SESSION['teacher_id']);
    }
} catch (PDOException $e) {
    error_log("Error fetching assigned subjects: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    $assigned_subjects = [];
}

// Get teacher details
try {
    $stmt = $db->prepare("SELECT t.*, u.username, u.email, u.role 
                         FROM teachers t 
                         JOIN users u ON t.user_id = u.id 
                         WHERE t.id = :teacher_id");
    $stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        error_log("No teacher found with ID: " . $_SESSION['teacher_id']);
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching teacher details: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    session_destroy();
    header('Location: login.php');
    exit();
}

// Get teacher's subjects
try {
    $stmt = $db->prepare("SELECT DISTINCT ts.subject, s.subject_code, s.is_compulsory 
                         FROM teacher_subjects ts
                         LEFT JOIN all_subjects s ON ts.subject = s.subject_name
                         WHERE ts.teacher_id = :teacher_id 
                         ORDER BY ts.subject
                         LIMIT 5");
    $stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
    $teacher_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no subjects are assigned, show a warning message
    if (empty($teacher_subjects)) {
        error_log("No subjects found for teacher_id: " . $_SESSION['teacher_id']);
        $_SESSION['warning_message'] = "You haven't been assigned any subjects yet. Please visit the Assign Subjects page to set up your subjects.";
        $teacher_subjects = [
            ['subject' => 'All Subjects']
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching teacher subjects: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    // If table doesn't exist or error occurs, use default subjects
    $teacher_subjects = [
        ['subject' => 'All Subjects']
    ];
}

// Get selected subject with validation
$selected_subject = 'All Subjects';
if (isset($_GET['subject'])) {
    // Verify the selected subject is one of the teacher's assigned subjects
    foreach ($teacher_subjects as $subject) {
        if ($_GET['subject'] === $subject['subject']) {
            $selected_subject = $_GET['subject'];
            break;
        }
    }
}

// Get subject-specific statistics with improved queries
$stats = [];

// Build the statistics query with proper joins and conditions
$statsQuery = "SELECT 
    (SELECT COUNT(*) 
     FROM exams e 
     WHERE e.created_by = :teacher_id1" . 
    ($selected_subject !== 'All Subjects' ? " AND e.subject = :subject1" : "") . ") as total_exams,
    
    (SELECT COUNT(*) 
     FROM exam_attempts ea 
     JOIN exams e ON ea.exam_id = e.id 
     WHERE e.created_by = :teacher_id2" .
    ($selected_subject !== 'All Subjects' ? " AND e.subject = :subject2" : "") . "
     AND ea.status = 'completed') as total_attempts,
     
    (SELECT COUNT(DISTINCT ea.student_id)
     FROM exam_attempts ea
     JOIN exams e ON ea.exam_id = e.id
     WHERE e.created_by = :teacher_id3" .
    ($selected_subject !== 'All Subjects' ? " AND e.subject = :subject3" : "") . "
     AND ea.status = 'completed') as total_students,
     
    (SELECT ROUND(AVG(ea.score), 2)
     FROM exam_attempts ea
     JOIN exams e ON ea.exam_id = e.id
     WHERE e.created_by = :teacher_id4" .
    ($selected_subject !== 'All Subjects' ? " AND e.subject = :subject4" : "") . "
     AND ea.status = 'completed') as avg_score";

$params = [
    ':teacher_id1' => $_SESSION['teacher_id'],
    ':teacher_id2' => $_SESSION['teacher_id'],
    ':teacher_id3' => $_SESSION['teacher_id'],
    ':teacher_id4' => $_SESSION['teacher_id']
];

if ($selected_subject !== 'All Subjects') {
    $params[':subject1'] = $selected_subject;
    $params[':subject2'] = $selected_subject;
    $params[':subject3'] = $selected_subject;
    $params[':subject4'] = $selected_subject;
}

try {
    $stmt = $db->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    $stats = [
        'total_exams' => 0,
        'total_attempts' => 0,
        'total_students' => 0,
        'avg_score' => 0
    ];
}

// Get recent exam attempts with improved query
$recentAttemptsQuery = "
    SELECT 
        ea.*,
        e.title as exam_title,
        e.subject,
        e.passing_score,
        u.username as student_name,
        u.id as student_id
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    JOIN users u ON ea.student_id = u.id
    WHERE e.created_by = :teacher_id" .
    ($selected_subject !== 'All Subjects' ? " AND e.subject = :subject" : "") . "
    AND ea.status = 'completed'
    ORDER BY ea.start_time DESC
    LIMIT 5";

try {
    $stmt = $db->prepare($recentAttemptsQuery);
    $params = [':teacher_id' => $_SESSION['teacher_id']];
    if ($selected_subject !== 'All Subjects') {
        $params[':subject'] = $selected_subject;
    }
    $stmt->execute($params);
    $recent_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent attempts: " . $e->getMessage());
    $recent_attempts = [];
}

// Display any warning messages
if (isset($_SESSION['warning_message'])) {
    $message = '<div class="alert alert-warning">' . $_SESSION['warning_message'] . '</div>';
    unset($_SESSION['warning_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            width: 250px;
        }
        .sidebar-content {
            padding: 20px;
            height: 100vh;
            overflow-y: auto;
            position: relative;
        }
        .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
            margin: -20px -20px 20px -20px;
        }
        .sidebar-header h4 {
            color: #fff;
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: #fff;
            color: #2c3e50;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .sidebar .nav-link.text-danger {
            background: rgba(220, 53, 69, 0.1);
        }
        .sidebar .nav-link.text-danger:hover {
            background: rgba(220, 53, 69, 0.2);
        }
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #2c3e50;
            border: none;
            color: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        @media (max-width: 768px) {
            .sidebar-toggle {
                padding: 3px 5px;
                top: 5px;
                left: 5px;
                transform: scale(0.8);
            }
            .sidebar-toggle i {
                font-size: 0.8rem;
            }
        }
        .main-wrapper {
            min-height: 100vh;
            padding-left: 250px;
            transition: all 0.3s ease;
            width: 100%;
        }
        .main-content {
            padding: 20px;
            margin-top: 15px;
            width: 100%;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card i {
            font-size: 2rem;
            color: #3498db;
        }
        .subject-selector select {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .subject-selector select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .subject-selector .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
        .subject-selector .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        .alert {
            border: none;
            border-radius: 0.5rem;
        }
        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: #2980b9;
        }
        @media (max-width: 768px) {
            .subject-selector {
                width: 100%;
            }
            .subject-selector form {
                width: 100%;
            }
            .subject-selector .d-flex {
                flex-direction: column;
                width: 100%;
            }
            .subject-selector select,
            .subject-selector button {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
        .subject-limit-warning {
            display: none;
            color: #dc3545;
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            background-color: rgba(220, 53, 69, 0.1);
        }
        .subject-selection-card {
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .subject-selection-card .card-header {
            background: linear-gradient(45deg, #2c3e50, #3498db);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 20px;
            border: none;
        }
        .subject-selection-card .card-body {
            padding: 25px;
        }
        .subject-checkbox-container {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .subject-checkbox-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        .subject-checkbox-container .form-check {
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
        }
        .subject-checkbox-container .form-check-input {
            margin: 0;
            margin-right: 12px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .subject-checkbox-container .form-check-input:checked {
            background-color: #3498db;
            border-color: #3498db;
        }
        .subject-checkbox-container .form-check-input:checked ~ .form-check-label {
            color: #3498db;
        }
        .subject-checkbox-container .form-check-label {
            margin: 0;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .badge.bg-danger {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
            border-radius: 6px;
            background: linear-gradient(45deg, #e74c3c, #c0392b) !important;
        }
        .submit-button {
            background: linear-gradient(45deg, #2c3e50, #3498db);
            border: none;
            padding: 12px 25px;
            font-weight: 500;
            letter-spacing: 0.5px;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
            background: linear-gradient(45deg, #34495e, #2980b9);
        }
        .submit-button:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }
        .submit-button i {
            margin-right: 8px;
        }
        .selection-counter {
            background: rgba(52, 152, 219, 0.1);
            color: #2c3e50;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .selection-counter span {
            color: #3498db;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .subject-selection-card .card-body {
                padding: 15px;
            }
            .subject-checkbox-container {
                padding: 12px;
            }
            .subject-checkbox-container .form-check-label {
                font-size: 0.9rem;
            }
            .badge.bg-danger {
                font-size: 0.7rem;
            }
            .submit-button {
                width: 100%;
                padding: 15px;
            }
            .selection-counter {
                flex-direction: column;
                text-align: center;
                gap: 5px;
            }
        }
        .category-section {
            margin-bottom: 30px;
        }
        .category-header {
            background: rgba(52, 152, 219, 0.1);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #2c3e50;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .category-header .category-name {
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .category-header .subject-count {
            font-size: 0.9rem;
            color: #3498db;
            background: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        .subject-checkbox-container {
            position: relative;
        }
        .subject-category-tag {
            position: absolute;
            top: 0;
            right: 0;
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 0 8px 0 8px;
        }
        @media (max-width: 768px) {
            .category-header {
                flex-direction: column;
                text-align: center;
                gap: 8px;
                padding: 15px;
            }
            .category-header .subject-count {
                font-size: 0.8rem;
            }
        }
        @media (max-width: 991px) {
            .sidebar {
                width: 250px;
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .sidebar-toggle {
                display: block;
            }
            .main-wrapper {
                padding-left: 0;
                width: 100%;
                margin-left: 0;
            }
            .main-wrapper .container-fluid {
                padding-left: 15px;
                padding-right: 15px;
            }
            .main-content {
                margin-top: 60px;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
<!-- Sidebar Toggle Button -->
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class='bx bx-menu'></i>
            </button>

            <!-- Sidebar Overlay -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <!-- Sidebar -->
            <div class="sidebar col-md-3 col-lg-2" id="sidebar">
                <div class="sidebar-content">
                    <div class="sidebar-header">
                        <h4><i class='bx bxs-graduation me-2'></i>Teacher Panel</h4>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class='bx bxs-dashboard'></i>
                            <span>Dashboard</span>
                        </a>
                        <a class="nav-link" href="students.php">
                            <i class='bx bxs-user-detail'></i>
                            <span>Students</span>
                        </a>
                        <a class="nav-link" href="exams.php">
                            <i class='bx bxs-book'></i>
                                        <span>Exams</span>
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class='bx bxs-report'></i>
                            <span>Reports</span>
                        </a>
                        <a class="nav-link text-danger" href="logout.php">
                            <i class='bx bxs-log-out'></i>
                            <span>Logout</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content Wrapper -->
            <div class="main-wrapper" id="mainWrapper">
                <div class="container-fluid py-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                        <div>
                            <h2 class="mb-1">Welcome, <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>!</h2>
                            <p class="text-muted mb-0">Here's what's happening with your subjects today.</p>
                        </div>
                        <div class="subject-selector">
                            <form method="GET" class="bg-white p-2 rounded shadow-sm">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="position-relative">
                                        <select name="subject" id="subject" class="form-select form-select-lg pe-5" onchange="this.form.submit()" style="min-width: 200px;">
                                            <?php foreach ($teacher_subjects as $subject): ?>
                                                <option value="<?php echo htmlspecialchars($subject['subject']); ?>"
                                                        <?php echo $selected_subject === $subject['subject'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($subject['subject']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class='bx bx-book-open position-absolute' style="right: 2rem; top: 50%; transform: translateY(-50%);"></i>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class='bx bx-filter-alt'></i>
                                        Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="alert alert-info d-flex align-items-center" role="alert">
                                <i class='bx bx-info-circle me-2 fs-5'></i>
                                <div>
                                    <?php if ($selected_subject === 'All Subjects'): ?>
                                        Showing statistics for all your subjects
                                    <?php else: ?>
                                        Currently viewing statistics for <strong><?php echo htmlspecialchars($selected_subject); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Total Exams<?php echo $selected_subject !== 'All Subjects' ? ' (' . htmlspecialchars($selected_subject) . ')' : ''; ?></h6>
                                        <h3><?php echo $stats['total_exams'] ?? 0; ?></h3>
                                    </div>
                                    <i class='bx bxs-book-content'></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Total Attempts<?php echo $selected_subject !== 'All Subjects' ? ' (' . htmlspecialchars($selected_subject) . ')' : ''; ?></h6>
                                        <h3><?php echo $stats['total_attempts'] ?? 0; ?></h3>
                                    </div>
                                    <i class='bx bxs-bar-chart-alt-2'></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add this right after the Quick Stats section and before Recent Activity -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card subject-selection-card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class='bx bx-book-reader me-2'></i>
                                        Select Your Subjects (Maximum 5)
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (isset($_SESSION['success_message'])): ?>
                                        <div class="alert alert-success" role="alert">
                                            <i class='bx bx-check-circle me-2'></i>
                                            <?php 
                                            echo $_SESSION['success_message'];
                                            unset($_SESSION['success_message']);
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($_SESSION['error_message'])): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <i class='bx bx-error-circle me-2'></i>
                                            <?php 
                                            echo $_SESSION['error_message'];
                                            unset($_SESSION['error_message']);
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" action="" id="subjectForm">
                                        <div class="selection-counter">
                                            <div>Selected Subjects: <span id="selectedCount">0</span>/5</div>
                                            <div>Remaining: <span id="remainingCount">5</span></div>
                                        </div>

                                        <?php foreach ($subjects_by_category as $category => $subjects): ?>
                                        <div class="category-section">
                                            <div class="category-header">
                                                <div class="category-name">
                                                    <i class='bx bx-bookmark'></i>
                                                    <?php echo htmlspecialchars($category); ?>
                                                </div>
                                                <div class="subject-count">
                                                    <?php echo count($subjects); ?> Subject<?php echo count($subjects) !== 1 ? 's' : ''; ?>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <?php foreach ($subjects as $subject): ?>
                                                <div class="col-md-6 col-lg-4">
                                                    <div class="subject-checkbox-container">
                                                        <div class="form-check">
                                                            <input class="form-check-input subject-checkbox" type="checkbox" 
                                                                   name="subjects[]" 
                                                                   value="<?php echo htmlspecialchars($subject['subject_name']); ?>"
                                                                   id="subject_<?php echo htmlspecialchars($subject['subject_code']); ?>"
                                                                   <?php echo in_array($subject['subject_name'], $assigned_subjects) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="subject_<?php echo htmlspecialchars($subject['subject_code']); ?>">
                                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                                <?php if ($subject['is_compulsory']): ?>
                                                                    <span class="badge bg-danger">Compulsory</span>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>

                                        <div class="subject-limit-warning" id="subjectLimitWarning">
                                            <i class='bx bx-error me-2'></i>
                                            You can only select up to 5 subjects. Please uncheck some subjects before proceeding.
                                        </div>

                                        <button type="submit" name="select_subjects" class="btn btn-primary submit-button mt-4" id="submitButton">
                                            <i class='bx bx-save'></i>
                                            Save Subject Selection
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Recent Exam Attempts</h5>
                                    <div>
                                        <button id="refresh-attempts" class="btn btn-sm btn-outline-primary me-2">
                                            <i class="fas fa-sync-alt"></i> Refresh
                                        </button>
                                        <a href="view-attempts.php" class="btn btn-sm btn-primary">View All</a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="recentAttemptsTable">
                                            <thead>
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Exam</th>
                                                    <th>Score</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_attempts as $attempt): 
                                                    // Calculate status based on score
                                                    $status = '';
                                                    $status_class = '';
                                                    if ($attempt['score'] >= 70) {
                                                        $status = 'Excellent';
                                                        $status_class = 'success';
                                                    } elseif ($attempt['score'] >= 50) {
                                                        $status = 'Pass';
                                                        $status_class = 'primary';
                                                    } else {
                                                        $status = 'Fail';
                                                        $status_class = 'danger';
                                                    }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center">
                                                                <span class="text-primary"><?php echo strtoupper(substr($attempt['student_name'] ?? 'NA', 0, 2)); ?></span>
                                                            </div>
                                                            <div>
                                                                <?php echo htmlspecialchars($attempt['student_name'] ?? 'Unknown Student'); ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    ID: <?php echo htmlspecialchars($attempt['student_id'] ?? 'N/A'); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $attempt['score']; ?>%"
                                                                 aria-valuenow="<?php echo $attempt['score']; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                                <?php echo $attempt['score']; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                                    <td><?php echo date('M d, Y h:i A', strtotime($attempt['start_time'])); ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="view-attempt.php?id=<?php echo $attempt['id']; ?>" 
                                                               class="btn btn-sm btn-info" 
                                                               title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-success" 
                                                                    onclick="downloadResult(<?php echo $attempt['id']; ?>)"
                                                                    title="Download Result">
                                                                <i class="fas fa-download"></i>
                                                            </button>
                                                        </div>
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
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mainWrapper = document.getElementById('mainWrapper');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                sidebar.classList.toggle('show');
                mainWrapper.classList.toggle('sidebar-open');
                sidebarOverlay.classList.toggle('show');
            }

            sidebarToggle.addEventListener('click', toggleSidebar);
            sidebarOverlay.addEventListener('click', toggleSidebar);

            // Close sidebar when clicking a link on mobile
            const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 991) {
                        toggleSidebar();
                    }
                });
            });

            // Handle window resize
            let windowWidth = window.innerWidth;
            window.addEventListener('resize', () => {
                if (window.innerWidth !== windowWidth) {
                    windowWidth = window.innerWidth;
                    if (windowWidth > 991) {
                        sidebar.classList.remove('show');
                        mainWrapper.classList.remove('sidebar-open');
                        sidebarOverlay.classList.remove('show');
                    }
                }
            });

            // Add custom styles
            const style = document.createElement('style');
            style.textContent = `
                .avatar-sm {
                    width: 32px;
                    height: 32px;
                    font-size: 0.875rem;
                }
                .progress {
                    background-color: #e9ecef;
                    border-radius: 0.25rem;
                }
                .toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 1050;
                }
            `;
            document.head.appendChild(style);

            // Refresh button functionality
            document.getElementById('refresh-attempts').addEventListener('click', function() {
                location.reload();
            });

            // Function to handle result download
            function downloadResult(attemptId) {
                // Add your download logic here
                alert('Downloading result for attempt ' + attemptId);
            }

            const form = document.getElementById('subjectForm');
            const checkboxes = document.querySelectorAll('.subject-checkbox');
            const warning = document.getElementById('subjectLimitWarning');
            const submitButton = document.getElementById('submitButton');
            const selectedCountElement = document.getElementById('selectedCount');
            const remainingCountElement = document.getElementById('remainingCount');
            const MAX_SUBJECTS = 5;

            function updateSubjectSelection() {
                const checkedBoxes = document.querySelectorAll('.subject-checkbox:checked');
                const checkedCount = checkedBoxes.length;
                const remaining = MAX_SUBJECTS - checkedCount;

                selectedCountElement.textContent = checkedCount;
                remainingCountElement.textContent = remaining;

                if (checkedCount > MAX_SUBJECTS) {
                    warning.style.display = 'block';
                    submitButton.disabled = true;
                } else {
                    warning.style.display = 'none';
                    submitButton.disabled = false;
                }
            }

            // Make the entire container clickable
            document.querySelectorAll('.subject-checkbox-container').forEach(container => {
                container.addEventListener('click', function(e) {
                    if (e.target !== this && !e.target.classList.contains('form-check-label')) return;
                    const checkbox = this.querySelector('.subject-checkbox');
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                });
            });

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSubjectSelection);
            });

            form.addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('.subject-checkbox:checked');
                if (checkedBoxes.length > MAX_SUBJECTS) {
                    e.preventDefault();
                    warning.style.display = 'block';
                }
            });

            // Initial check
            updateSubjectSelection();
        });
    </script>
</body>
</html>