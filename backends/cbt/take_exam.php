<?php
require_once '../config.php';
require_once '../database.php';
session_start();

// Check if user is logged in as a student
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    // Redirect to student login page
    header('Location: ../student/login.php?error=Please+login+to+access+this+page');
    exit;
}

// Get student information
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get exam ID from GET parameter
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    // Redirect to dashboard if no valid exam ID
    header('Location: ../student/registration/student_dashboard.php?error=Invalid+exam');
    exit;
}

// Get student's class
$classQuery = "SELECT class FROM students WHERE id = ?";
$stmt = $conn->prepare($classQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$classResult = $stmt->get_result();
$studentClass = "";

if ($row = $classResult->fetch_assoc()) {
    $studentClass = $row['class'];
}

// Verify that the exam exists, is active, and the student is eligible to take it
$examQuery = "SELECT e.*, s.name AS subject_name
              FROM cbt_exams e
              JOIN subjects s ON e.subject_id = s.id
              WHERE e.id = ? 
              AND e.is_active = 1
              AND NOW() BETWEEN e.start_datetime AND e.end_datetime
              AND (e.class_id = ? OR e.class_id = ?)";

$stmt = $conn->prepare($examQuery);
$stmt->bind_param("iss", $exam_id, $studentClass, $studentClass);
$stmt->execute();
$examResult = $stmt->get_result();

if ($examResult->num_rows === 0) {
    // Exam not found or not available to this student
    header('Location: ../student/registration/student_dashboard.php?error=Exam+not+available');
    exit;
}

$exam = $examResult->fetch_assoc();

// Check if the student has already taken this exam
$checkAttemptQuery = "SELECT id FROM cbt_student_exams WHERE student_id = ? AND exam_id = ?";
$stmt = $conn->prepare($checkAttemptQuery);
$stmt->bind_param("ii", $student_id, $exam_id);
$stmt->execute();
$attemptResult = $stmt->get_result();

if ($attemptResult->num_rows > 0) {
    // Student has already taken this exam
    header('Location: ../student/registration/student_dashboard.php?error=You+have+already+taken+this+exam');
    exit;
}

// Get questions for this exam
$questionsQuery = "SELECT q.* FROM cbt_questions q WHERE q.exam_id = ? ORDER BY RAND() LIMIT ?";
$stmt = $conn->prepare($questionsQuery);
$stmt->bind_param("ii", $exam_id, $exam['total_questions']);
$stmt->execute();
$questionsResult = $stmt->get_result();

$questions = [];
while ($row = $questionsResult->fetch_assoc()) {
    $questions[] = $row;
}

// Check if we have enough questions
if (count($questions) < $exam['total_questions']) {
    // Not enough questions
    header('Location: ../student/registration/student_dashboard.php?error=Not+enough+questions+in+the+exam');
    exit;
}

// Process form submission
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        $score = 0;
        $total_possible = 0;
        $answer_data = [];
        
        // Process each answer
        foreach ($_POST['answers'] as $question_id => $answer) {
            $question_id = (int)$question_id;
            
            // Find the question in our array
            $questionIndex = array_search($question_id, array_column($questions, 'id'));
            
            if ($questionIndex !== false) {
                $question = $questions[$questionIndex];
                $total_possible += $question['marks'];
                
                // Handle different question types
                if ($question['question_type'] === 'Multiple Choice') {
                    // Get correct option for this question
                    $optionsQuery = "SELECT option_text FROM cbt_options WHERE question_id = ? AND is_correct = 1";
                    $stmt = $conn->prepare($optionsQuery);
                    $stmt->bind_param("i", $question_id);
                    $stmt->execute();
                    $correctOptionsResult = $stmt->get_result();
                    
                    $correctOptions = [];
                    while ($row = $correctOptionsResult->fetch_assoc()) {
                        $correctOptions[] = $row['option_text'];
                    }
                    
                    // Check if answer is correct
                    $student_answer = is_array($answer) ? $answer : [$answer];
                    $is_correct = count(array_intersect($student_answer, $correctOptions)) > 0;
                    
                    if ($is_correct) {
                        $score += $question['marks'];
                    }
                    
                    // Store answer data
                    $answer_data[] = [
                        'question_id' => $question_id,
                        'student_answer' => implode('||', $student_answer),
                        'is_correct' => $is_correct ? 1 : 0,
                        'marks_earned' => $is_correct ? $question['marks'] : 0,
                        'possible_marks' => $question['marks']
                    ];
                }
                else if ($question['question_type'] === 'True/False') {
                    // Process True/False question
                    $optionsQuery = "SELECT option_text FROM cbt_options WHERE question_id = ? AND is_correct = 1";
                    $stmt = $conn->prepare($optionsQuery);
                    $stmt->bind_param("i", $question_id);
                    $stmt->execute();
                    $correctOptionResult = $stmt->get_result();
                    $correctOption = $correctOptionResult->fetch_assoc()['option_text'] ?? '';
                    
                    $is_correct = strcasecmp($answer, $correctOption) === 0;
                    
                    if ($is_correct) {
                        $score += $question['marks'];
                    }
                    
                    // Store answer data
                    $answer_data[] = [
                        'question_id' => $question_id,
                        'student_answer' => $answer,
                        'is_correct' => $is_correct ? 1 : 0,
                        'marks_earned' => $is_correct ? $question['marks'] : 0,
                        'possible_marks' => $question['marks']
                    ];
                }
            }
        }
        
        // Calculate percentage
        $percentage = ($total_possible > 0) ? ($score / $total_possible) * 100 : 0;
        
        // Determine if passing
        $is_passing = $percentage >= $exam['passing_score'];
        
        // Insert student exam record
        $insertExamQuery = "INSERT INTO cbt_student_exams 
                            (student_id, exam_id, score, total_score, percentage, status, completed_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $status = $is_passing ? 'passed' : 'failed';
        
        $stmt = $conn->prepare($insertExamQuery);
        $stmt->bind_param("iiidds", 
            $student_id, 
            $exam_id, 
            $score, 
            $total_possible, 
            $percentage, 
            $status
        );
        $stmt->execute();
        
        $student_exam_id = $conn->insert_id;
        
        // Insert answer details
        foreach ($answer_data as $answer) {
            $insertAnswerQuery = "INSERT INTO cbt_student_answers
                                 (student_exam_id, question_id, student_answer, is_correct, marks_earned, possible_marks) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insertAnswerQuery);
            $stmt->bind_param("iisidi", 
                $student_exam_id,
                $answer['question_id'],
                $answer['student_answer'],
                $answer['is_correct'],
                $answer['marks_earned'],
                $answer['possible_marks']
            );
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to results page
        header('Location: view_result.php?exam_id=' . $exam_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $errorMessage = "Error submitting exam: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam - <?php echo htmlspecialchars($exam['title']); ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3a56d4;
            --secondary-color: #f8f9fa;
            --success-color: #2ec4b6;
            --info-color: #4895ef;
            --warning-color: #ff9f1c;
            --danger-color: #e71d36;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding-top: 20px;
            padding-bottom: 50px;
        }
        
        .exam-container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .exam-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .exam-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .exam-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .info-item {
            padding: 12px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 10px;
            flex-basis: 19%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }
        
        .info-item strong {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .info-item span {
            font-size: 1.1rem;
            font-weight: 600;
            color: #343a40;
        }
        
        .timer-container {
            position: sticky;
            top: 20px;
            z-index: 1000;
            text-align: center;
            padding: 15px;
            background: var(--primary-color);
            color: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
        }
        
        .timer {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .question-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            padding: 25px;
            border-left: 5px solid var(--primary-color);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .question-number {
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .question-marks {
            background: #f0f4ff;
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .question-text {
            font-size: 1.1rem;
            margin-bottom: 20px;
            color: #2d3748;
            line-height: 1.6;
        }
        
        .question-image {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .options {
            margin-top: 15px;
        }
        
        .option-item {
            display: block;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .option-item:hover {
            background-color: #f0f4ff;
            border-color: var(--primary-color);
        }
        
        .option-item input {
            margin-right: 10px;
        }
        
        .btn-submit {
            background: linear-gradient(45deg, var(--primary-color), #4361ee);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(67, 97, 238, 0.2);
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
        }
        
        .instructions-card {
            background: #f0f4ff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 5px solid var(--info-color);
        }
        
        .instructions-title {
            color: var(--info-color);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .exam-container {
                padding: 20px 15px;
            }
            
            .info-item {
                flex-basis: 48%;
            }
            
            .question-card {
                padding: 20px 15px;
            }
        }
        
        @media (max-width: 576px) {
            .info-item {
                flex-basis: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="exam-container">
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <div class="exam-header">
                <h2 class="exam-title">
                    <i class="fas fa-file-alt"></i>
                    <?php echo htmlspecialchars($exam['title']); ?>
                </h2>
                <p class="text-muted"><?php echo htmlspecialchars($exam['description']); ?></p>
                
                <div class="exam-info">
                    <div class="info-item">
                        <strong>Subject</strong>
                        <span><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Questions</strong>
                        <span><?php echo htmlspecialchars($exam['total_questions']); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Duration</strong>
                        <span><?php echo htmlspecialchars($exam['time_limit']); ?> min</span>
                    </div>
                    <div class="info-item">
                        <strong>Pass Score</strong>
                        <span><?php echo htmlspecialchars($exam['passing_score']); ?>%</span>
                    </div>
                    <div class="info-item">
                        <strong>Student</strong>
                        <span><?php echo htmlspecialchars($student_name); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($exam['instructions'])): ?>
                <div class="instructions-card">
                    <h5 class="instructions-title">
                        <i class="fas fa-info-circle"></i> Instructions
                    </h5>
                    <p><?php echo nl2br(htmlspecialchars($exam['instructions'])); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="timer-container">
                <div>Time Remaining:</div>
                <div class="timer" id="timer">
                    <?php echo $exam['time_limit']; ?>:00
                </div>
            </div>
            
            <form id="exam-form" action="" method="post" onsubmit="return confirmSubmit()">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card">
                        <div class="question-header">
                            <div class="question-number"><?php echo $index + 1; ?></div>
                            <div class="question-marks">
                                <i class="fas fa-star"></i> <?php echo $question['marks']; ?> marks
                            </div>
                        </div>
                        
                        <div class="question-text">
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </div>
                        
                        <?php if (!empty($question['image_path'])): ?>
                            <img src="../../uploads/cbt_images/<?php echo $question['image_path']; ?>" 
                                 alt="Question Image" class="question-image">
                        <?php endif; ?>
                        
                        <div class="options">
                            <?php if ($question['question_type'] === 'Multiple Choice'): ?>
                                <?php
                                // Get options for this question
                                $optionsQuery = "SELECT * FROM cbt_options WHERE question_id = ?";
                                $stmt = $conn->prepare($optionsQuery);
                                $stmt->bind_param("i", $question['id']);
                                $stmt->execute();
                                $optionsResult = $stmt->get_result();
                                
                                while ($option = $optionsResult->fetch_assoc()):
                                ?>
                                    <label class="option-item">
                                        <input type="radio" name="answers[<?php echo $question['id']; ?>]" 
                                               value="<?php echo htmlspecialchars($option['option_text']); ?>" required>
                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                    </label>
                                <?php endwhile; ?>
                            <?php elseif ($question['question_type'] === 'True/False'): ?>
                                <label class="option-item">
                                    <input type="radio" name="answers[<?php echo $question['id']; ?>]" 
                                           value="True" required>
                                    True
                                </label>
                                <label class="option-item">
                                    <input type="radio" name="answers[<?php echo $question['id']; ?>]" 
                                           value="False" required>
                                    False
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <button type="submit" name="submit_exam" class="btn btn-primary btn-lg btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        // Timer functionality
        document.addEventListener('DOMContentLoaded', function() {
            let timeLimit = <?php echo $exam['time_limit']; ?> * 60; // Convert to seconds
            const timerDisplay = document.getElementById('timer');
            
            const timer = setInterval(function() {
                timeLimit--;
                
                const minutes = Math.floor(timeLimit / 60);
                const seconds = timeLimit % 60;
                
                // Format the time display
                timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Change color when time is running low
                if (timeLimit < 300) { // Less than 5 minutes
                    timerDisplay.style.color = '#ff9f1c';
                }
                if (timeLimit < 60) { // Less than 1 minute
                    timerDisplay.style.color = '#e71d36';
                }
                
                // Auto-submit when time is up
                if (timeLimit <= 0) {
                    clearInterval(timer);
                    alert('Time is up! Your exam will be submitted automatically.');
                    document.getElementById('exam-form').submit();
                }
            }, 1000);
            
            // Save timer value in sessionStorage
            window.addEventListener('beforeunload', function() {
                sessionStorage.setItem('examTimeRemaining', timeLimit);
            });
            
            // Check if there's a saved timer value and use it if it exists
            const savedTime = sessionStorage.getItem('examTimeRemaining');
            if (savedTime !== null && !isNaN(savedTime)) {
                timeLimit = parseInt(savedTime);
            }
        });
        
        // Confirmation before submitting
        function confirmSubmit() {
            // Check if all questions are answered
            const form = document.getElementById('exam-form');
            const questions = <?php echo count($questions); ?>;
            let answeredCount = 0;
            
            const inputs = form.querySelectorAll('input[type="radio"]:checked');
            answeredCount = inputs.length;
            
            if (answeredCount < questions) {
                const unanswered = questions - answeredCount;
                return confirm(`You have ${unanswered} unanswered question(s). Are you sure you want to submit your exam?`);
            }
            
            return confirm('Are you sure you want to submit your exam? You cannot change your answers after submission.');
        }
    </script>
</body>
</html> 