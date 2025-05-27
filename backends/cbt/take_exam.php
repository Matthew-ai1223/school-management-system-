<?php
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';

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

// Connect to database
$db = Database::getInstance();
$conn = $db->getConnection();

// Function to get exam by ID
function getExamById($conn, $exam_id) {
    $stmt = $conn->prepare("SELECT * FROM cbt_exams WHERE id = ? AND is_active = 1");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to get student by registration number and class
function getStudentByRegNumAndClass($conn, $reg_number, $class) {
    // Try with both admission_number and registration_number
    $stmt = $conn->prepare("SELECT * FROM students WHERE (registration_number = ? OR admission_number = ?) AND TRIM(class) = ? LIMIT 1");
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
    
    // First try with pattern matching
    $searchPattern = '%' . trim($class) . '%';
    $stmt = $conn->prepare("SELECT * FROM cbt_exams WHERE is_active = 1 AND TRIM(class) LIKE ? ORDER BY created_at DESC");
    
    if ($stmt) {
        $stmt->bind_param("s", $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
        
        // If no results, try with exact matching
        if (count($exams) == 0) {
            $all_exams_query = "SELECT * FROM cbt_exams WHERE is_active = 1 ORDER BY created_at DESC";
            $result = $conn->query($all_exams_query);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Compare class names case-insensitively after trimming
                    if (strcasecmp(trim($row['class']), trim($class)) == 0) {
                        $exams[] = $row;
                    }
                }
            }
        }
    }
    
    return $exams;
}

// Function to save student exam results
function saveExamResults($conn, $student_id, $exam_id, $answers, $score) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get exam passing score
        $stmt = $conn->prepare("SELECT passing_score FROM cbt_exams WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare exam query");
        }
        
        $stmt->bind_param("i", $exam_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to get exam details");
        }
        
        $result = $stmt->get_result();
        $exam = $result->fetch_assoc();
        if (!$exam) {
            throw new Exception("Exam not found");
        }
        
        // 1. Update cbt_student_exams
        $status = ($score >= $exam['passing_score']) ? 'passed' : 'failed';
        $stmt = $conn->prepare("
            UPDATE cbt_student_exams 
            SET score = ?, status = ?, completed_at = NOW() 
            WHERE student_id = ? AND exam_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare student exams update statement");
        }
        
        $stmt->bind_param("dsii", $score, $status, $student_id, $exam_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update student exams");
        }
        
        // 2. Create/Update exam attempt
        $stmt = $conn->prepare("
            INSERT INTO cbt_exam_attempts (exam_id, student_id, start_time, end_time, submit_time, status)
            VALUES (?, ?, NOW(), NOW(), NOW(), 'completed')
            ON DUPLICATE KEY UPDATE end_time = NOW(), submit_time = NOW(), status = 'completed'
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare exam attempts statement");
        }
        
        $stmt->bind_param("ii", $exam_id, $student_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to create/update exam attempt");
        }
        
        $attempt_id = $stmt->insert_id ?: getExistingAttemptId($conn, $student_id, $exam_id);
        
        // 3. Save student answers
        $stmt = $conn->prepare("
            INSERT INTO cbt_student_answers (attempt_id, question_id, selected_option, is_correct)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), is_correct = VALUES(is_correct)
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare student answers statement");
        }
        
        foreach ($answers as $question_id => $answer) {
            $is_correct = isAnswerCorrect($conn, $question_id, $answer);
            $stmt->bind_param("iisi", $attempt_id, $question_id, $answer, $is_correct);
            if (!$stmt->execute()) {
                throw new Exception("Failed to save student answer");
            }
        }
        
        // Commit transaction
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error saving exam results: " . $e->getMessage());
        return false;
    }
}

// Helper function to get existing attempt ID
function getExistingAttemptId($conn, $student_id, $exam_id) {
    $stmt = $conn->prepare("
        SELECT id FROM cbt_exam_attempts 
        WHERE student_id = ? AND exam_id = ? 
        ORDER BY start_time DESC LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("ii", $student_id, $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            return $row['id'];
        }
    }
    return null;
}

// Helper function to check if answer is correct
function isAnswerCorrect($conn, $question_id, $selected_option) {
    // First get the question type
    $stmt = $conn->prepare("
        SELECT question_type, correct_answer 
        FROM cbt_questions 
        WHERE id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            if ($row['question_type'] === 'True/False') {
                // For True/False questions, compare directly with correct_answer
                return strtoupper(trim($selected_option)) === strtoupper(trim($row['correct_answer'])) ? 1 : 0;
            } else {
                // For Multiple Choice questions, check if the selected option is marked as correct
                $stmt = $conn->prepare("
                    SELECT is_correct 
                    FROM cbt_options 
                    WHERE question_id = ? AND option_text = ?
                ");
                $stmt->bind_param("is", $question_id, $selected_option);
                $stmt->execute();
                $optionResult = $stmt->get_result();
                if ($optionResult && $optionRow = $optionResult->fetch_assoc()) {
                    return $optionRow['is_correct'] ? 1 : 0;
                }
            }
        }
    }
    return 0;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['student_login'])) {
        // Student login form submission
        $reg_number = trim($_POST['registration_number']);
        $class = trim($_POST['class']);
        
        // Add debug logging
        error_log("Student Login - Registration Number: $reg_number, Class: $class");
        error_log("Session Data Before Login: " . json_encode($_SESSION));
        
        if (empty($reg_number) || empty($class)) {
            $error = "Please provide both registration number and class";
            error_log("Login Error: $error");
        } else {
            $student = getStudentByRegNumAndClass($conn, $reg_number, $class);
            error_log("Student Data: " . json_encode($student));
            
            if ($student) {
                // Set session variables
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['registration_number'] = $student['registration_number'] ?? $student['admission_number'];
                $_SESSION['student_class'] = $student['class'];
                error_log("Session Data After Login: " . json_encode($_SESSION));
                
                // Get available exams
                $available_exams = getAvailableExamsByClass($conn, $student['class']);
            } else {
                $error = "Invalid registration number or class. Please try again.";
                error_log("Login Error: $error");
            }
        }
    } elseif (isset($_POST['start_exam'])) {
        $student_id = $_POST['student_id'] ?? $_SESSION['student_id'] ?? null;
        $exam_id = $_POST['exam_id'] ?? null;
        
        // Debug log
        error_log("Starting exam - Student ID: $student_id, Exam ID: $exam_id");
        error_log("Session Data Before Starting Exam: " . json_encode($_SESSION));
        
        if (!$student_id || !$exam_id) {
            $error = "Missing student ID or exam ID";
            error_log("Start Exam Error: $error");
            $_SESSION['error_message'] = $error;
            header("Location: ../student/registration/student_dashboard.php");
            exit;
        }
        
        try {
            // Start transaction
            $conn->begin_transaction();
            error_log("Transaction started");
            
            // First check if there's an existing in-progress session
            $check_session = "SELECT id FROM cbt_student_exams 
                            WHERE student_id = ? AND exam_id = ? AND status = 'In Progress'";
            $stmt = $conn->prepare($check_session);
            $stmt->bind_param("ii", $student_id, $exam_id);
            $stmt->execute();
            $existing_session = $stmt->get_result()->fetch_assoc();
            
            $session_id = null;
            if ($existing_session) {
                // Use existing session
                $session_id = $existing_session['id'];
                error_log("Using existing exam session with ID: $session_id");
            } else {
                // Create new exam session
                $create_session = "INSERT INTO cbt_student_exams (student_id, exam_id, status, started_at) 
                                 VALUES (?, ?, 'In Progress', NOW())";
                $stmt = $conn->prepare($create_session);
                $stmt->bind_param("ii", $student_id, $exam_id);
                $stmt->execute();
                $session_id = $conn->insert_id;
                error_log("Created new exam session with ID: $session_id");
            }
            
            // Get attempt info
            $check_attempts = "SELECT COUNT(*) as attempts, MAX(attempt_number) as last_attempt 
                             FROM cbt_exam_attempts 
                             WHERE student_id = ? AND exam_id = ? AND status = 'completed'";
            $stmt = $conn->prepare($check_attempts);
            $stmt->bind_param("ii", $student_id, $exam_id);
            $stmt->execute();
            $attempt_info = $stmt->get_result()->fetch_assoc();
            error_log("Attempt Info: " . json_encode($attempt_info));
            
            // Check if retakes are allowed for completed exams
            if ($attempt_info['attempts'] > 0) {
                $check_retake = "SELECT allow_retake FROM cbt_exams WHERE id = ?";
                $stmt = $conn->prepare($check_retake);
                $stmt->bind_param("i", $exam_id);
                $stmt->execute();
                $retake_result = $stmt->get_result()->fetch_assoc();
                
                if (!$retake_result['allow_retake']) {
                    throw new Exception("You have already completed this exam and retakes are not allowed.");
                }
            }
            
            // Check for existing in-progress attempt
            $check_attempt = "SELECT id FROM cbt_exam_attempts 
                            WHERE student_id = ? AND exam_id = ? AND status = 'in_progress'";
            $stmt = $conn->prepare($check_attempt);
            $stmt->bind_param("ii", $student_id, $exam_id);
            $stmt->execute();
            $existing_attempt = $stmt->get_result()->fetch_assoc();
            
            $attempt_id = null;
            $attempt_number = 1;
            if ($existing_attempt) {
                $attempt_id = $existing_attempt['id'];
                error_log("Using existing attempt ID: $attempt_id");
            } else {
                // Get the next attempt number
                $attempt_number = ($attempt_info['last_attempt'] ?? 0) + 1;
                
                // Create new attempt
                $create_attempt = "INSERT INTO cbt_exam_attempts 
                                 (student_id, exam_id, start_time, status, attempt_number) 
                                 VALUES (?, ?, NOW(), 'in_progress', ?)";
                $stmt = $conn->prepare($create_attempt);
                $stmt->bind_param("iii", $student_id, $exam_id, $attempt_number);
                $stmt->execute();
                $attempt_id = $conn->insert_id;
                error_log("Created new attempt number: $attempt_number, ID: $attempt_id");
            }
            
            // Store exam session in PHP session
            $_SESSION['current_exam'] = [
                'session_id' => $session_id,
                'exam_id' => $exam_id,
                'student_id' => $student_id,
                'attempt_id' => $attempt_id,
                'attempt_number' => $attempt_number,
                'start_time' => time()
            ];
            error_log("Stored exam session in PHP session: " . json_encode($_SESSION['current_exam']));
            
            // Commit transaction
            $conn->commit();
            error_log("Transaction committed successfully");
            
            // Set the full URL for the redirect
            $exam_interface_url = "exam_interface.php?session_id=" . $session_id;
            error_log("Redirecting to: $exam_interface_url");
            
            // Redirect to exam interface with session ID
            header("Location: " . $exam_interface_url);
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            error_log("Error occurred: " . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: ../student/registration/student_dashboard.php");
            exit;
        }
    } elseif (isset($_POST['submit_exam'])) {
        $student_id = $_SESSION['student_id'] ?? null;
        $exam_id = $_POST['exam_id'] ?? null;
        $answers = $_POST['answers'] ?? [];
        
        if (!$student_id || !$exam_id) {
            $error = "Missing student ID or exam ID";
        } else {
            // Calculate score
            $total_questions = count($answers);
            $correct_answers = 0;
            
            foreach ($answers as $question_id => $answer) {
                if (isAnswerCorrect($conn, $question_id, $answer)) {
                    $correct_answers++;
                }
            }
            
            $score = $total_questions > 0 ? ($correct_answers / $total_questions) * 100 : 0;
            
            // Save results
            if (saveExamResults($conn, $student_id, $exam_id, $answers, $score)) {
                // Redirect to results page
                header("Location: view_result.php?exam_id=$exam_id&student_id=$student_id");
                exit;
            } else {
                $error = "Failed to save exam results. Please try again or contact administrator.";
            }
        }
    }
}

// Check if exam_id is provided in URL
if (isset($_GET['exam_id']) && isset($_GET['student_id'])) {
    $exam_id = $_GET['exam_id'];
    $student_id = $_GET['student_id'];
    
    // Verify student is logged in or provided in URL
    if (isset($_SESSION['student_id']) || $student_id) {
        $student_id = $_SESSION['student_id'] ?? $student_id;
        
        // Get student details
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
                
                // Get exam details
                $exam = getExamById($conn, $exam_id);
                
                if (!$exam) {
                    $error = "Exam not found or not active";
                }
            } else {
                $error = "Student not found";
            }
        }
    } else {
        // If no student is logged in and none provided in URL, show login form
    }
} elseif (isset($_SESSION['student_id'])) {
    // Student is already logged in, get their details
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $student = $result->fetch_assoc();
            
            // Get available exams
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