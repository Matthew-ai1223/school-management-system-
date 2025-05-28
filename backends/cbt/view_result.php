<?php
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';
require_once 'test_db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    $_SESSION['error_message'] = "Please log in to view results.";
    header("Location: ../student/login.php");
    exit;
}

// Check if session_id is provided
if (!isset($_GET['session_id'])) {
    $_SESSION['error_message'] = "No exam session specified.";
    header("Location: take_exam.php");
    exit;
}

$session_id = $_GET['session_id'];
$student_id = $_SESSION['student_id'];
$error = '';
$exam_data = null;
$answers = [];
$hide_details = false;

try {
    $testDb = TestDatabase::getInstance();
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get exam attempt details with exam info
    $stmt = $conn->prepare("
        SELECT 
            sa.*,
            e.title as exam_title,
            e.subject,
            e.class,
            e.passing_score
        FROM cbt_student_attempts sa
        JOIN cbt_exams e ON e.id = sa.exam_id
        WHERE sa.id = ? AND sa.student_id = ? AND sa.status = 'Completed'
    ");
    
    $stmt->bind_param("ii", $session_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No completed exam found");
    }
    
    $exam_data = $result->fetch_assoc();
    
    // Get detailed answers
    $stmt = $conn->prepare("
        SELECT 
            q.question_text,
            q.question_type,
            q.correct_answer,
            sa.selected_answer,
            sa.is_correct,
            GROUP_CONCAT(o.option_text ORDER BY o.id) as options
        FROM cbt_questions q
        LEFT JOIN cbt_options o ON q.id = o.question_id
        LEFT JOIN cbt_student_answers sa ON q.id = sa.question_id AND sa.attempt_id = ?
        WHERE q.exam_id = ?
        GROUP BY q.id
        ORDER BY q.sort_order, q.id
    ");
    
    $stmt->bind_param("ii", $session_id, $exam_data['exam_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $answers[] = $row;
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Error in view_result.php: " . $e->getMessage());
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
        
        .result-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .score-card {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .score-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .question-review {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .correct {
            color: #28a745;
        }
        
        .incorrect {
            color: #dc3545;
        }
        
        .unanswered {
            color: #6c757d;
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
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3>Exam Results</h3>
                </div>
                <div class="card-body">
                    <!-- Display exam information -->
                    <div class="exam-info">
                        <h4>Exam Details</h4>
                        <p><strong>Title:</strong> <?php echo htmlspecialchars($exam_data['exam_title']); ?></p>
                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($exam_data['subject']); ?></p>
                        <p><strong>Class:</strong> <?php echo htmlspecialchars($exam_data['class']); ?></p>
                    </div>

                    <!-- Display score information -->
                    <div class="score-info">
                        <h4>Score Summary</h4>
                        <?php
                        $marks_obtained = isset($exam_data['marks_obtained']) ? floatval($exam_data['marks_obtained']) : 0;
                        $total_marks = isset($exam_data['total_marks']) && floatval($exam_data['total_marks']) > 0 ? floatval($exam_data['total_marks']) : 1;
                        $percentage = $total_marks > 0 ? ($marks_obtained / $total_marks) * 100 : 0;
                        $passing_score = isset($exam_data['passing_score']) ? floatval($exam_data['passing_score']) : 50;
                        ?>
                        <p><strong>Score:</strong> <?php echo number_format($marks_obtained, 2); ?> / <?php echo number_format($total_marks, 2); ?></p>
                        <p><strong>Percentage:</strong> <?php echo number_format($percentage, 2); ?>%</p>
                        <p><strong>Status:</strong> 
                            <?php if ($percentage >= $passing_score): ?>
                                <span class="text-success">PASSED</span>
                            <?php else: ?>
                                <span class="text-danger">FAILED</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (!$hide_details): ?>
                        <!-- Display question details -->
                        <div class="question-details">
                            <h4>Question Details</h4>
                            <?php foreach ($answers as $index => $answer): ?>
                                <div class="question-item">
                                    <p class="question-text">
                                        <strong>Q<?php echo $index + 1; ?>:</strong> 
                                        <?php echo htmlspecialchars($answer['question_text']); ?>
                                    </p>
                                    <p class="answer-text">
                                        <strong>Your Answer:</strong> 
                                        <?php echo htmlspecialchars($answer['selected_answer'] ?? 'Not answered'); ?>
                                        <?php if ($answer['is_correct']): ?>
                                            <span class="text-success">(Correct)</span>
                                        <?php else: ?>
                                            <span class="text-danger">(Incorrect)</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="correct-answer">
                                        <strong>Correct Answer:</strong> 
                                        <?php echo htmlspecialchars($answer['correct_answer']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="../student/registration/student_dashboard.php" class="btn btn-primary">Return to Dashboard</a>
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