<?php
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$error = '';
$exam = null;
$student = null;
$student_exam = null;
$questions = [];
$student_answers = [];
$total_questions = 0;
$correct_answers = 0;
$show_answers = false;

// Connect to database
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if exam_id and student_id are provided
if (!isset($_GET['exam_id']) || !isset($_GET['student_id'])) {
    $error = "Missing exam or student ID";
} else {
    $exam_id = $_GET['exam_id'];
    $student_id = $_GET['student_id'];
    
    // Get exam details
    $stmt = $conn->prepare("SELECT * FROM cbt_exams WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $exam = $result->fetch_assoc();
            // Set default value for show_results if not set
            $exam['show_results'] = $exam['show_results'] ?? 0;
            $show_answers = (bool)$exam['show_results'];
            
            // Get student details
            $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $student = $result->fetch_assoc();
                
                // Get student exam details
                $stmt = $conn->prepare("SELECT * FROM cbt_student_exams WHERE student_id = ? AND exam_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->bind_param("ii", $student_id, $exam_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $student_exam = $result->fetch_assoc();
                    
                    // Get exam questions
                    $stmt = $conn->prepare("SELECT * FROM cbt_questions WHERE exam_id = ? ORDER BY id");
                    $stmt->bind_param("i", $exam_id);
                    $stmt->execute();
                    $questionsResult = $stmt->get_result();
                    
                    while ($row = $questionsResult->fetch_assoc()) {
                        $questions[] = $row;
                    }
                    
                    // Get student's answers - using cbt_exam_attempts to handle the foreign key constraint
                    // First find a corresponding exam attempt
                    $stmt = $conn->prepare("SELECT id FROM cbt_exam_attempts WHERE student_id = ? AND exam_id = ?");
                    $stmt->bind_param("ii", $student_id, $exam_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        // Use the existing attempt
                        $attempt = $result->fetch_assoc();
                        $attempt_id = $attempt['id'];
                    } else {
                        // Create a new attempt record if needed
                        $stmt = $conn->prepare("INSERT INTO cbt_exam_attempts (exam_id, student_id, start_time, status) VALUES (?, ?, NOW(), 'completed')");
                        $stmt->bind_param("ii", $exam_id, $student_id);
                        $stmt->execute();
                        $attempt_id = $conn->insert_id;
                    }
                    
                    // Now get the answers using the valid attempt_id
                    $stmt = $conn->prepare("SELECT * FROM cbt_student_answers WHERE attempt_id = ?");
                    $stmt->bind_param("i", $attempt_id);
                    $stmt->execute();
                    $answersResult = $stmt->get_result();
                    
                    // Check which column exists in the table
                    $columns_result = $conn->query("SHOW COLUMNS FROM cbt_student_answers");
                    $columns = [];
                    while ($column = $columns_result->fetch_assoc()) {
                        $columns[] = $column['Field'];
                    }
                    $has_student_answer_column = in_array('student_answer', $columns);
                    $has_selected_option_column = in_array('selected_option', $columns);
                    
                    while ($row = $answersResult->fetch_assoc()) {
                        // Try both possible column names for the answer
                        if (isset($row['student_answer'])) {
                            $student_answers[$row['question_id']] = $row['student_answer'];
                        } elseif (isset($row['selected_option'])) {
                            $student_answers[$row['question_id']] = $row['selected_option'];
                        }
                    }
                    
                    // Calculate score details
                    $total_questions = count($questions);
                    foreach ($questions as $question) {
                        $student_answer = $student_answers[$question['id']] ?? '';
                        
                        // Check for different possible column names for the correct answer
                        $correct_answer = null;
                        if (isset($question['correct_option'])) {
                            $correct_answer = $question['correct_option'];
                        } elseif (isset($question['correct_answer'])) {
                            $correct_answer = $question['correct_answer'];
                        }
                        
                        if ($correct_answer !== null && strtoupper(trim($student_answer)) === strtoupper(trim($correct_answer))) {
                            $correct_answers++;
                        }
                    }
                } else {
                    $error = "No exam results found for this student";
                }
            } else {
                $error = "Student not found";
            }
        } else {
            $error = "Exam not found";
        }
    } else {
        $error = "Database error";
    }
}

// Format date
function formatDate($date) {
    return date('M d, Y h:i A', strtotime($date));
}

// Check if the current user is authorized to view this result
$is_authorized = false;

// Student can view their own results
if (isset($_SESSION['student_id']) && $_SESSION['student_id'] == $student_id) {
    $is_authorized = true;
    // For students, check if the exam allows result viewing
    if ($exam && isset($exam['show_results']) && !$exam['show_results'] && 
        isset($student_exam['status']) && $student_exam['status'] !== 'In Progress') {
        $_SESSION['error'] = "Results are not available for viewing at this time.";
        // Remove the redirect since we're already showing an error message
        // header('Location: ../student/registration/student_dashboard.php#cbt-exams');
        // exit;
    }
}

// Admin can view any results
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $is_authorized = true;
}

// Teacher can view results for their subjects/class
if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'teacher' || $_SESSION['user_role'] === 'class_teacher')) {
    // Get teacher's assigned subjects and classes
    $teacherQuery = "SELECT ts.subject_id, ts.class 
                    FROM teacher_subjects ts 
                    JOIN subjects s ON ts.subject_id = s.id 
                    WHERE ts.teacher_id = ?";
    $stmt = $conn->prepare($teacherQuery);
    $stmt->bind_param("i", $_SESSION['teacher_id']);
    $stmt->execute();
    $teacherResult = $stmt->get_result();
    
    while ($row = $teacherResult->fetch_assoc()) {
        if ($row['subject_id'] == $exam['subject_id'] || $row['class'] == $student['class']) {
            $is_authorized = true;
            break;
        }
    }
}

if (!$is_authorized) {
    $_SESSION['error'] = "You are not authorized to view these results.";
    if (isset($_SESSION['student_id'])) {
        header('Location: ../student/registration/student_dashboard.php#cbt-exams');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// Check if we should hide detailed results
$hide_details = ($show_answers == false && !isset($_SESSION['user_role']));

// Add navigation links based on user role
$navigation = '';
if (isset($_SESSION['student_id'])) {
    $navigation = '
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../student/registration/student_dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="../student/registration/student_dashboard.php#cbt-exams">CBT Exams</a></li>
            <li class="breadcrumb-item active" aria-current="page">Exam Results</li>
        </ol>
    </nav>';
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher') {
    $navigation = '
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="manage_exams.php">Manage Exams</a></li>
            <li class="breadcrumb-item active" aria-current="page">View Results</li>
        </ol>
    </nav>';
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'class_teacher') {
    $navigation = '
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../class_teacher/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="../class_teacher/manage_cbt_exams.php">Manage Exams</a></li>
            <li class="breadcrumb-item active" aria-current="page">View Results</li>
        </ol>
    </nav>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - <?php echo SCHOOL_NAME; ?></title>
    
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
        
        .result-summary {
            background-color: #e3f2fd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .result-summary h4 {
            color: #1a237e;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 1px solid #bbdefb;
            padding-bottom: 10px;
        }
        
        .result-item {
            margin-bottom: 10px;
        }
        
        .result-label {
            font-weight: 600;
            color: #1a237e;
        }
        
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: conic-gradient(
                var(--color-primary) calc(var(--score) * 1%),
                #e9ecef 0
            );
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
        }
        
        .score-circle::before {
            content: "";
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .score-value {
            position: relative;
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-primary);
        }
        
        .status-label {
            text-align: center;
            font-weight: 700;
            font-size: 1.2rem;
            padding: 8px 15px;
            border-radius: 30px;
            margin: 0 auto;
            display: inline-block;
        }
        
        .status-passed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .question-item {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #1a237e;
        }
        
        .question-text {
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .option {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #fff;
            border: 1px solid #dee2e6;
        }
        
        .option.selected {
            background-color: #cfe2ff;
            border-color: #9ec5fe;
        }
        
        .option.correct {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .option.incorrect {
            background-color: #f8d7da;
            border-color: #f5c6cb;
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
        
        .print-btn {
            float: right;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
            }
            
            .card-header {
                background-color: #f8f9fa !important;
                color: #212529 !important;
                border-bottom: 1px solid #dee2e6 !important;
            }
            
            body {
                background-color: white !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="school-header">
            <img src="../../images/logo.png" alt="School Logo" class="school-logo">
            <h2><?php echo SCHOOL_NAME; ?></h2>
            <h4>Exam Results</h4>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            <div class="mt-3">
                <a href="../student/registration/student_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
            </div>
        </div>
        <?php elseif (!$is_authorized): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle mr-2"></i> You are not authorized to view these results.
            <div class="mt-3">
                <a href="../student/registration/student_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
            </div>
        </div>
        <?php else: ?>
        <div class="no-print">
            <a href="../student/registration/student_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="window.print();" class="btn btn-sm btn-outline-primary print-btn">
                <i class="fas fa-print mr-1"></i> Print Results
            </button>
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-poll mr-2"></i> Exam Results
                </div>
                <div>
                    <span class="badge badge-light"><?php echo formatDate($student_exam['completed_at'] ?? $student_exam['started_at']); ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="result-summary">
                    <div class="row">
                        <div class="col-md-8">
                            <h4>Student Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="result-item">
                                        <div class="result-label">Name</div>
                                        <div><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="result-item">
                                        <div class="result-label">Registration Number</div>
                                        <div><?php echo htmlspecialchars($student['registration_number'] ?? $student['admission_number']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="result-item">
                                        <div class="result-label">Class</div>
                                        <div><?php echo htmlspecialchars($student['class']); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="mt-4">Exam Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="result-item">
                                        <div class="result-label">Exam Title</div>
                                        <div><?php echo htmlspecialchars($exam['title']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="result-item">
                                        <div class="result-label">Subject</div>
                                        <div><?php echo htmlspecialchars($exam['subject']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="result-item">
                                        <div class="result-label">Number of Questions</div>
                                        <div><?php echo $total_questions; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="result-item">
                                        <div class="result-label">Passing Score</div>
                                        <div><?php echo htmlspecialchars($exam['passing_score']); ?>%</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="result-item">
                                        <div class="result-label">Exam Date</div>
                                        <div>
                                            <?php 
                                            if (isset($student_exam['completed_at'])) {
                                                echo formatDate($student_exam['completed_at']);
                                            } elseif (isset($student_exam['started_at'])) {
                                                echo formatDate($student_exam['started_at']) . ' (Not completed)';
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <?php 
                            $score = $student_exam['score'] ?? 0;
                            $status = $student_exam['status'] ?? 'Incomplete';
                            $scoreColor = ($status == 'passed') ? '#43a047' : '#e53935';
                            ?>
                            <div class="score-circle" style="--score: <?php echo $score; ?>; --color-primary: <?php echo $scoreColor; ?>">
                                <div class="score-value"><?php echo $score; ?>%</div>
                            </div>
                            
                            <div class="status-label <?php echo ($status == 'passed') ? 'status-passed' : 'status-failed'; ?>">
                                <?php echo ucfirst($status); ?>
                            </div>
                            
                            <div class="mt-3">
                                <div class="result-item">
                                    <div class="result-label">Correct Answers</div>
                                    <div><?php echo $correct_answers; ?> out of <?php echo $total_questions; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!$hide_details): ?>
                <h4>Question Breakdown</h4>
                <div class="question-breakdown">
                    <?php foreach($questions as $index => $question): 
                        $student_answer = $student_answers[$question['id']] ?? '';
                        
                        // Get question type and correct answer
                        $stmt = $conn->prepare("SELECT question_type, correct_answer FROM cbt_questions WHERE id = ?");
                        $stmt->bind_param("i", $question['id']);
                        $stmt->execute();
                        $qResult = $stmt->get_result();
                        $qData = $qResult->fetch_assoc();
                        
                        if ($qData['question_type'] === 'True/False') {
                            $is_correct = (strtoupper(trim($student_answer)) === strtoupper(trim($qData['correct_answer'])));
                        } else {
                            // For multiple choice, check if selected option is correct
                            $stmt = $conn->prepare("SELECT is_correct FROM cbt_options WHERE question_id = ? AND option_text = ?");
                            $stmt->bind_param("is", $question['id'], $student_answer);
                            $stmt->execute();
                            $optResult = $stmt->get_result();
                            $optData = $optResult->fetch_assoc();
                            $is_correct = $optData && $optData['is_correct'] ? true : false;
                        }
                    ?>
                    <div class="question-item">
                        <div class="question-text">
                            <span class="badge badge-secondary mr-2"><?php echo $index + 1; ?></span>
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </div>
                        
                        <?php if ($qData['question_type'] === 'Multiple Choice'): 
                            // Get all options for this question
                            $stmt = $conn->prepare("SELECT * FROM cbt_options WHERE question_id = ? ORDER BY id");
                            $stmt->bind_param("i", $question['id']);
                            $stmt->execute();
                            $optionsResult = $stmt->get_result();
                            $options = [];
                            while ($opt = $optionsResult->fetch_assoc()) {
                                $options[] = $opt;
                            }
                        ?>
                            <div class="options-list">
                                <?php foreach($options as $option): 
                                    $option_class = '';
                                    if ($student_answer == $option['option_text']) {
                                        $option_class = $is_correct ? 'correct' : 'incorrect';
                                    } elseif ($option['is_correct']) {
                                        $option_class = 'correct';
                                    }
                                ?>
                                    <div class="option <?php echo $option_class; ?>">
                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                        <?php if ($option['is_correct']): ?>
                                            <span class="badge badge-success">Correct Answer</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="options-list">
                                <div class="option <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                                    Student Answer: <?php echo htmlspecialchars($student_answer); ?>
                                </div>
                                <div class="option correct">
                                    Correct Answer: <?php echo htmlspecialchars($qData['correct_answer']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i> Detailed question results are not available for this exam.
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-4 no-print">
                    <a href="../student/registration/student_dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home mr-1"></i> Return to Dashboard
                    </a>
                </div>
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