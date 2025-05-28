<?php
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';
require_once 'test_db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set error log path
ini_set('error_log', 'C:/xampp/htdocs/ACE MODEL COLLEGE/logs/php_errors.log');
if (!file_exists('C:/xampp/htdocs/ACE MODEL COLLEGE/logs')) {
    mkdir('C:/xampp/htdocs/ACE MODEL COLLEGE/logs', 0777, true);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$error = '';
$student = null;
$exam = null;
$available_exams = [];

// Initialize database
$testDb = TestDatabase::getInstance();
$db = Database::getInstance();
$conn = $db->getConnection();

// Function to get student by registration number and class
function getStudentByRegNumAndClass($conn, $reg_number, $class) {
    $stmt = $conn->prepare("
        SELECT * FROM students 
        WHERE (registration_number = ? OR admission_number = ?) 
        AND TRIM(class) = ? 
        LIMIT 1
    ");
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("sss", $reg_number, $reg_number, $class);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to get available exams for a class
function getAvailableExamsByClass($conn, $class) {
    $exams = [];
    $searchPattern = '%' . trim($class) . '%';
    
    $stmt = $conn->prepare("
        SELECT * FROM cbt_exams 
        WHERE is_active = 1 
        AND TRIM(class) LIKE ? 
        ORDER BY created_at DESC
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
    }
    
    return $exams;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['student_login'])) {
        $reg_number = trim($_POST['registration_number']);
        $class = trim($_POST['class']);
        
        error_log("Student Login - Registration Number: $reg_number, Class: $class");
        
        if (empty($reg_number) || empty($class)) {
            $error = "Please provide both registration number and class";
            error_log("Login Error: $error");
        } else {
            $student = getStudentByRegNumAndClass($conn, $reg_number, $class);
            
            if ($student) {
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['registration_number'] = $student['registration_number'] ?? $student['admission_number'];
                $_SESSION['student_class'] = $student['class'];
                
                $available_exams = getAvailableExamsByClass($conn, $student['class']);
            } else {
                $error = "Invalid registration number or class. Please try again.";
            }
        }
    } elseif (isset($_POST['start_exam'])) {
        $student_id = $_SESSION['student_id'] ?? null;
        $exam_id = $_POST['exam_id'] ?? null;
        
        if (!$student_id || !$exam_id) {
            $error = "Missing student ID or exam ID";
            $_SESSION['error_message'] = $error;
            header("Location: take_exam.php");
            exit;
        }
        
        try {
            // Verify exam exists and is active
            $exam = $testDb->getExamById($exam_id);
            if (!$exam) {
                throw new Exception("Exam not found or is no longer active");
            }
            
            // Create exam attempt
            $attempt_id = $testDb->createExamAttempt($student_id, $exam_id);
            
            if (!$attempt_id) {
                throw new Exception("Failed to create exam attempt. Please try again.");
            }
            
            // Redirect to exam interface
            header("Location: exam_interface.php?session_id=" . $attempt_id);
            exit;
            
        } catch (Exception $e) {
            error_log("Error starting exam: " . $e->getMessage());
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
            header("Location: take_exam.php");
            exit;
        }
    }
}

// Check if exam_id is provided in URL
if (isset($_GET['exam_id']) && isset($_GET['student_id'])) {
    $exam_id = $_GET['exam_id'];
    $student_id = $_GET['student_id'];
    
    if (isset($_SESSION['student_id']) || $student_id) {
        $student_id = $_SESSION['student_id'] ?? $student_id;
        
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $student = $result->fetch_assoc();
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['registration_number'] = $student['registration_number'] ?? $student['admission_number'];
                $_SESSION['student_class'] = $student['class'];
                
                $exam = $testDb->getExamById($exam_id);
                
                if (!$exam) {
                    $error = "Exam not found or not active";
                }
            } else {
                $error = "Student not found";
            }
        }
    }
} elseif (isset($_SESSION['student_id'])) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $available_exams = getAvailableExamsByClass($conn, $student['class']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam - <?php echo SCHOOL_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        
        .main-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .school-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .school-logo {
            max-height: 80px;
            margin-bottom: 15px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            background-color: #1a237e;
            color: white;
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .exam-list {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .exam-list .list-group-item {
            border-left: none;
            border-right: none;
            padding: 15px 20px;
            transition: all 0.3s;
        }
        
        .exam-list .list-group-item:first-child {
            border-top: none;
        }
        
        .exam-list .list-group-item:last-child {
            border-bottom: none;
        }
        
        .exam-list .list-group-item:hover {
            background-color: #f8f9fa;
        }
        
        .badge-subject {
            background-color: #e3f2fd;
            color: #1565c0;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 4px;
        }
        
        .btn-primary {
            background-color: #1a237e;
            border-color: #1a237e;
        }
        
        .btn-primary:hover {
            background-color: #0d47a1;
            border-color: #0d47a1;
        }
        
        .btn-outline-primary {
            color: #1a237e;
            border-color: #1a237e;
        }
        
        .btn-outline-primary:hover {
            background-color: #1a237e;
            border-color: #1a237e;
        }
        
        .login-form {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .alert {
            border-radius: 8px;
            font-weight: 500;
        }
        
        .exam-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .exam-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .exam-details .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .exam-details .detail-label {
            font-weight: 600;
            color: #495057;
        }
        
        .exam-info {
            background-color: #e3f2fd;
            border-left: 4px solid #1565c0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #1a237e;
            font-weight: 600;
        }
        
        .back-link i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="school-header">
            <img src="../../images/logo.png" alt="School Logo" class="school-logo">
            <h2><?php echo SCHOOL_NAME; ?></h2>
            <h4>Computer Based Test (CBT) Portal</h4>
        </div>
        
        <?php 
        // Display session error messages if any
        if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php 
            // Clear the error message after displaying
            unset($_SESSION['error_message']);
        endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!$student): ?>
        <!-- Student Login Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-check mr-2"></i> Student Login
            </div>
            <div class="card-body">
                <form class="login-form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="form-group">
                        <label for="registration_number">Registration/Admission Number</label>
                        <input type="text" class="form-control" id="registration_number" name="registration_number" required>
                    </div>
                    <div class="form-group">
                        <label for="class">Class</label>
                        <input type="text" class="form-control" id="class" name="class" required>
                        <small class="form-text text-muted">Enter your class exactly as registered (e.g. JSS3 Pearl)</small>
                    </div>
                    <div class="text-center">
                        <button type="submit" name="student_login" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-sign-in-alt mr-2"></i> Login
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="../student/registration/student_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Student Dashboard
            </a>
        </div>
        <?php elseif ($exam): ?>
        <!-- Specific Exam Information -->
        <a href="../student/registration/student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-alt mr-2"></i> Exam Information
            </div>
            <div class="card-body">
                <div class="exam-info">
                    <div class="row">
                        <div class="col-md-9">
                            <h4><?php echo htmlspecialchars($exam['title']); ?></h4>
                            <p class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['student_name']); ?>!</p>
                        </div>
                        <div class="col-md-3 text-right">
                            <span class="badge badge-subject">
                                <?php echo htmlspecialchars($exam['subject']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="exam-details">
                    <div class="detail-row">
                        <span class="detail-label">Duration:</span>
                        <span><?php echo htmlspecialchars($exam['duration']); ?> minutes</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Number of Questions:</span>
                        <span><?php echo htmlspecialchars($exam['total_questions'] ?? 'Not specified'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Passing Score:</span>
                        <span><?php echo htmlspecialchars($exam['passing_score']); ?>%</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Class:</span>
                        <span><?php echo htmlspecialchars($exam['class']); ?></span>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle mr-2"></i> Important Instructions:</h5>
                    <ol>
                        <li>This is a timed exam. Once started, the timer cannot be paused.</li>
                        <li>Do not refresh the page or close the browser during the exam.</li>
                        <li>Ensure you have a stable internet connection before starting.</li>
                        <li>Answer all questions. You can review your answers before submitting.</li>
                        <li>Click the "Submit" button when you've completed the exam.</li>
                    </ol>
                </div>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                    <div class="text-center mt-4">
                        <button type="submit" name="start_exam" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-play-circle mr-2"></i> Start Exam
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- Available Exams List -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-list-alt mr-2"></i> Available Exams
                    </div>
                    <div>
                        <a href="../student/registration/student_dashboard.php" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <h5><i class="fas fa-user mr-2"></i> Student Information</h5>
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['student_name']); ?></p>
                            <p class="mb-1"><strong>Registration Number:</strong> <?php echo htmlspecialchars($_SESSION['registration_number']); ?></p>
                            <p class="mb-0"><strong>Class:</strong> <?php echo htmlspecialchars($_SESSION['student_class'] ?? 'Not specified'); ?></p>
                        </div>
                        <div class="col-md-3 text-right">
                            <a href="?logout=1" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sign-out-alt mr-1"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (count($available_exams) > 0): ?>
                <div class="list-group exam-list">
                    <?php foreach ($available_exams as $exam): ?>
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-1"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                <span class="badge badge-subject"><?php echo htmlspecialchars($exam['subject']); ?></span>
                            </div>
                            <div class="col-md-3">
                                <div><strong>Duration:</strong> <?php echo htmlspecialchars($exam['duration']); ?> minutes</div>
                                <div><strong>Questions:</strong> <?php echo htmlspecialchars($exam['total_questions'] ?? 'Not specified'); ?></div>
                            </div>
                            <div class="col-md-3 text-right">
                                <a href="?exam_id=<?php echo $exam['id']; ?>&student_id=<?php echo $student['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-play-circle mr-1"></i> Take Exam
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle mr-2"></i> There are no exams currently available for your class.
                    <p class="mt-2 mb-0">If you believe this is an error, please contact your teacher or administrator.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <p class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo SCHOOL_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 