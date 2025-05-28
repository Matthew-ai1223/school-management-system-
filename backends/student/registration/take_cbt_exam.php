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

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get exam ID from URL
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    $_SESSION['error'] = "Invalid exam selected.";
    header('Location: student_dashboard.php');
    exit;
}

// Check if the exam exists and is available for this student
$examQuery = "SELECT e.*, s.name AS subject_name 
              FROM cbt_exams e
              JOIN subjects s ON e.subject_id = s.id
              JOIN class_subjects cs ON s.id = cs.subject_id
              JOIN students st ON st.class = cs.class_id
              WHERE e.id = ? AND e.is_active = 1 
              AND e.start_datetime <= NOW() 
              AND e.end_datetime >= NOW()
              AND st.id = ?";

$stmt = $conn->prepare($examQuery);
$stmt->bind_param("ii", $exam_id, $student_id);
$stmt->execute();
$examResult = $stmt->get_result();

if ($examResult->num_rows === 0) {
    $_SESSION['error'] = "This exam is not available for you to take.";
    header('Location: student_dashboard.php');
    exit;
}

$exam = $examResult->fetch_assoc();

// Check if student has an existing attempt for this exam
$checkAttemptQuery = "SELECT * FROM cbt_student_attempts WHERE student_id = ? AND exam_id = ?";
$stmt = $conn->prepare($checkAttemptQuery);
$stmt->bind_param("ii", $student_id, $exam_id);
$stmt->execute();
$attemptResult = $stmt->get_result();
$studentExam = null;
$isNewAttempt = false;

if ($attemptResult->num_rows === 0) {
    // Create new attempt
    $isNewAttempt = true;
    
    // Calculate end time based on exam duration
    $duration = $exam['time_limit'] ?? 60; // Default to 60 minutes if not set
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime("+{$duration} minutes"));
    
    $createAttemptQuery = "INSERT INTO cbt_student_attempts (
        exam_id,
        student_id,
        start_time,
        end_time,
        status,
        total_marks,
        marks_obtained,
        score,
        show_result,
        attempt_number,
        time_spent,
        ip_address,
        user_agent
    ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, ?, 0, ?, ?)";
    
    $total_marks = $exam['total_questions'] * ($exam['marks_per_question'] ?? 1);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $status = 'In Progress';
    
    $stmt = $conn->prepare($createAttemptQuery);
    $stmt->bind_param("iisssiss", 
        $exam_id, 
        $student_id, 
        $start_time, 
        $end_time,
        $status,
        $total_marks,
        $attempt_number,
        $ip_address,
        $user_agent
    );
    $stmt->execute();
    
    $studentExam = [
        'id' => $conn->insert_id,
        'student_id' => $student_id,
        'exam_id' => $exam_id,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'score' => null,
        'status' => 'In Progress'
    ];
} else {
    $studentExam = $attemptResult->fetch_assoc();
    
    // Check if exam is already completed
    if ($studentExam['status'] === 'Completed') {
        $_SESSION['error'] = "You have already completed this exam.";
        header('Location: student_dashboard.php#cbt-exams');
        exit;
    }
}

$student_exam_id = $studentExam['id'];

// Get questions for this exam
$questionsQuery = "SELECT q.* FROM cbt_questions q WHERE q.exam_id = ? ORDER BY q.id ASC";
$stmt = $conn->prepare($questionsQuery);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questionsResult = $stmt->get_result();
$questions = [];

while ($row = $questionsResult->fetch_assoc()) {
    // Get options for multiple choice questions
    if ($row['question_type'] === 'Multiple Choice') {
        $optionsQuery = "SELECT * FROM cbt_options WHERE question_id = ?";
        $optStmt = $conn->prepare($optionsQuery);
        $optStmt->bind_param("i", $row['id']);
        $optStmt->execute();
        $optionsResult = $optStmt->get_result();
        $options = [];
        
        while ($option = $optionsResult->fetch_assoc()) {
            $options[] = $option;
        }
        
        $row['options'] = $options;
    }
    
    // Check if student has already answered this question
    $answerQuery = "SELECT * FROM cbt_student_answers 
                   WHERE student_exam_id = ? AND question_id = ?";
    $ansStmt = $conn->prepare($answerQuery);
    $ansStmt->bind_param("ii", $student_exam_id, $row['id']);
    $ansStmt->execute();
    $answerResult = $ansStmt->get_result();
    
    if ($answerResult->num_rows > 0) {
        $answer = $answerResult->fetch_assoc();
        $row['student_answer'] = $answer;
    } else {
        $row['student_answer'] = null;
    }
    
    $questions[] = $row;
}

// Process submission of answers
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_answer'])) {
        // Save individual answer
        $question_id = $_POST['question_id'] ?? 0;
        $selected_option_id = $_POST['selected_option_id'] ?? null;
        $text_answer = $_POST['text_answer'] ?? null;
        
        // First, check if an answer already exists
        $checkQuery = "SELECT id FROM cbt_student_answers 
                      WHERE student_exam_id = ? AND question_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ii", $student_exam_id, $question_id);
        $stmt->execute();
        $checkResult = $stmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing answer
            $answer_id = $checkResult->fetch_assoc()['id'];
            $updateQuery = "UPDATE cbt_student_answers 
                           SET selected_option_id = ?, text_answer = ? 
                           WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("isi", $selected_option_id, $text_answer, $answer_id);
            $stmt->execute();
        } else {
            // Insert new answer
            $insertQuery = "INSERT INTO cbt_student_answers 
                          (student_exam_id, question_id, selected_option_id, text_answer) 
                          VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iiis", $student_exam_id, $question_id, $selected_option_id, $text_answer);
            $stmt->execute();
        }
        
        // Return JSON response for AJAX calls
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Redirect to same page to prevent form resubmission
        header("Location: {$_SERVER['REQUEST_URI']}");
        exit;
        
    } elseif (isset($_POST['submit_exam'])) {
        // Submit the entire exam
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Calculate score
            $totalScore = 0;
            $totalMarks = 0;
            
            foreach ($questions as $question) {
                $totalMarks += $question['marks'];
                
                if (!empty($question['student_answer'])) {
                    if ($question['question_type'] === 'Multiple Choice') {
                        // Check if selected option is correct
                        $selected_option_id = $question['student_answer']['selected_option_id'];
                        
                        if ($selected_option_id) {
                            $correctQuery = "SELECT is_correct FROM cbt_options WHERE id = ?";
                            $stmt = $conn->prepare($correctQuery);
                            $stmt->bind_param("i", $selected_option_id);
                            $stmt->execute();
                            $correctResult = $stmt->get_result();
                            
                            if ($correctResult->num_rows > 0) {
                                $correct = $correctResult->fetch_assoc();
                                if ($correct['is_correct']) {
                                    $totalScore += $question['marks'];
                                }
                            }
                        }
                    } elseif ($question['question_type'] === 'True/False') {
                        // For True/False questions
                        $text_answer = strtolower($question['student_answer']['text_answer']);
                        $correct_answer = "true"; // Default to true for this example
                        
                        if ($text_answer === $correct_answer) {
                            $totalScore += $question['marks'];
                        }
                    }
                }
            }
            
            // Calculate percentage score
            $percentageScore = ($totalMarks > 0) ? round(($totalScore / $totalMarks) * 100) : 0;
            
            // Update the student exam record
            $updateExamQuery = "UPDATE cbt_student_attempts 
                              SET end_time = NOW(), status = 'Completed', score = ? 
                              WHERE id = ?";
            $stmt = $conn->prepare($updateExamQuery);
            $stmt->bind_param("di", $percentageScore, $student_exam_id);
            $stmt->execute();
            
            $conn->commit();
            
            // Redirect to results page
            header("Location: view_cbt_result.php?exam_id=" . $exam_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = "Error submitting exam: " . $e->getMessage();
        }
    }
}

// Timer logic
$startTime = strtotime($studentExam['start_time']);
$currentTime = time();
$elapsedSeconds = $currentTime - $startTime;
$timeLimit = $exam['time_limit'] * 60; // convert minutes to seconds
$remainingSeconds = $timeLimit - $elapsedSeconds;

if ($remainingSeconds <= 0 && $studentExam['status'] === 'In Progress') {
    // Time's up - force submission
    header("Location: {$_SERVER['PHP_SELF']}?exam_id={$exam_id}&time_up=1");
    exit;
}

// Format time for display
$remainingMinutes = floor($remainingSeconds / 60);
$remainingSecondsDisplay = $remainingSeconds % 60;

// Show student progress
$answeredCount = 0;
foreach ($questions as $question) {
    if (!empty($question['student_answer'])) {
        $answeredCount++;
    }
}
$progressPercentage = count($questions) > 0 ? round(($answeredCount / count($questions)) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Exam: <?php echo htmlspecialchars($exam['title']); ?> - <?php echo SCHOOL_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:300,400,700" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1a237e;    /* Deep Blue */
            --primary-light: #534bae;    /* Lighter Primary */
            --primary-dark: #000051;     /* Darker Primary */
            --secondary-color: #0d47a1;  /* Medium Blue */
            --accent-color: #2962ff;     /* Bright Blue */
        }
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .exam-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .exam-header h1 {
            font-size: 1.8rem;
            font-family: 'Roboto Slab', serif;
            margin-bottom: 15px;
        }
        
        .exam-timer {
            background-color: #fff;
            color: var(--primary-dark);
            border-left: 4px solid var(--accent-color);
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 20px;
            z-index: 1000;
        }
        
        .exam-timer .time-display {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            text-align: center;
        }
        
        .exam-timer .time-warning {
            color: #e53935;
        }
        
        .question-card {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary-light);
        }
        
        .question-number {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .question-text {
            font-size: 1.1rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .question-image {
            max-width: 100%;
            height: auto;
            margin-bottom: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .option-label {
            display: block;
            padding: 12px 15px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .option-label:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .option-input:checked + .option-label {
            background-color: #e3f2fd;
            border-left: 4px solid var(--accent-color);
            font-weight: 600;
        }
        
        .option-input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .answered {
            background-color: #e8f5e9;
            border-left-color: #43a047;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        .exam-controls {
            position: sticky;
            bottom: 0;
            background-color: #fff;
            padding: 15px;
            border-top: 1px solid #e9ecef;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            margin: 0 -15px;
            z-index: 1000;
        }
        
        .question-navigation {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .question-nav-item {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            margin: 5px;
            border-radius: 50%;
            background-color: #f8f9fa;
            color: var(--primary-dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .question-nav-item:hover {
            background-color: #e9ecef;
            transform: scale(1.1);
        }
        
        .question-nav-item.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .question-nav-item.answered {
            background-color: #4caf50;
            color: white;
        }
        
        @media (max-width: 767px) {
            .exam-header h1 {
                font-size: 1.5rem;
            }
            
            .question-nav-item {
                width: 35px;
                height: 35px;
                line-height: 35px;
                margin: 3px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-md-8">
                <div class="exam-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
                            <p class="mb-0"><strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject_name']); ?></p>
                            <p class="mb-0"><strong>Total Questions:</strong> <?php echo count($questions); ?></p>
                        </div>
                        <a href="student_dashboard.php#cbt-exams" class="btn btn-light btn-sm">
                            <i class="fas fa-times"></i> Exit
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger">
                        <?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['time_up'])): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Time's Up!</h5>
                        <p>Your exam time has expired. Please click the "Submit Exam" button below to submit your answers.</p>
                    </div>
                <?php endif; ?>
                
                <form id="examForm" method="post" action="">
                    <?php foreach ($questions as $index => $question): ?>
                    <div id="question-<?php echo $index + 1; ?>" class="question-card <?php echo !empty($question['student_answer']) ? 'answered' : ''; ?>">
                        <div class="question-number">
                            Question <?php echo $index + 1; ?> 
                            <span class="float-right text-muted"><?php echo $question['marks']; ?> mark<?php echo $question['marks'] > 1 ? 's' : ''; ?></span>
                        </div>
                        
                        <div class="question-text">
                            <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                        </div>
                        
                        <?php if (!empty($question['image_path'])): ?>
                            <div class="text-center mb-4">
                                <img src="../../uploads/cbt_images/<?php echo $question['image_path']; ?>" 
                                     class="question-image" alt="Question Image">
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($question['question_type'] === 'Multiple Choice'): ?>
                            <div class="options-container">
                                <?php foreach ($question['options'] as $optionIndex => $option): ?>
                                    <div class="option-item">
                                        <input type="radio" 
                                               name="answer[<?php echo $question['id']; ?>]" 
                                               id="option-<?php echo $question['id']; ?>-<?php echo $option['id']; ?>" 
                                               value="<?php echo $option['id']; ?>" 
                                               class="option-input"
                                               data-question-id="<?php echo $question['id']; ?>"
                                               <?php echo (!empty($question['student_answer']) && $question['student_answer']['selected_option_id'] == $option['id']) ? 'checked' : ''; ?>>
                                        <label for="option-<?php echo $question['id']; ?>-<?php echo $option['id']; ?>" class="option-label">
                                            <?php echo htmlspecialchars($option['option_text']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($question['question_type'] === 'True/False'): ?>
                            <div class="options-container">
                                <div class="option-item">
                                    <input type="radio" 
                                           name="answer[<?php echo $question['id']; ?>]" 
                                           id="option-<?php echo $question['id']; ?>-true" 
                                           value="true" 
                                           class="option-input"
                                           data-question-id="<?php echo $question['id']; ?>"
                                           <?php echo (!empty($question['student_answer']) && strtolower($question['student_answer']['text_answer']) === 'true') ? 'checked' : ''; ?>>
                                    <label for="option-<?php echo $question['id']; ?>-true" class="option-label">
                                        True
                                    </label>
                                </div>
                                <div class="option-item">
                                    <input type="radio" 
                                           name="answer[<?php echo $question['id']; ?>]" 
                                           id="option-<?php echo $question['id']; ?>-false" 
                                           value="false" 
                                           class="option-input"
                                           data-question-id="<?php echo $question['id']; ?>"
                                           <?php echo (!empty($question['student_answer']) && strtolower($question['student_answer']['text_answer']) === 'false') ? 'checked' : ''; ?>>
                                    <label for="option-<?php echo $question['id']; ?>-false" class="option-label">
                                        False
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <?php if ($index > 0): ?>
                                <button type="button" class="btn btn-outline-secondary prev-question" data-target="<?php echo $index; ?>">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($index < count($questions) - 1): ?>
                                <button type="button" class="btn btn-primary float-right next-question" data-target="<?php echo $index + 2; ?>">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="exam-controls">
                        <div class="row align-items-center">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $progressPercentage; ?>%" 
                                         aria-valuenow="<?php echo $progressPercentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <small class="text-muted"><?php echo $answeredCount; ?> of <?php echo count($questions); ?> questions answered (<?php echo $progressPercentage; ?>%)</small>
                            </div>
                            <div class="col-md-6 text-center text-md-right">
                                <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#confirmSubmitModal">
                                    <i class="fas fa-check-circle"></i> Submit Exam
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hidden inputs for saving answers -->
                    <input type="hidden" id="question_id" name="question_id" value="">
                    <input type="hidden" id="selected_option_id" name="selected_option_id" value="">
                    <input type="hidden" id="text_answer" name="text_answer" value="">
                    <input type="hidden" id="ajax" name="ajax" value="1">
                    <input type="hidden" name="save_answer" value="1">
                </form>
            </div>
            
            <div class="col-md-4">
                <div class="exam-timer">
                    <div class="text-center mb-2">
                        <strong>Time Remaining</strong>
                    </div>
                    <div id="timer" class="time-display">
                        <span id="minutes"><?php echo str_pad($remainingMinutes, 2, '0', STR_PAD_LEFT); ?></span>:
                        <span id="seconds"><?php echo str_pad($remainingSecondsDisplay, 2, '0', STR_PAD_LEFT); ?></span>
                    </div>
                </div>
                
                <div class="card question-navigation mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Question Navigation</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="question-nav-item <?php echo !empty($question['student_answer']) ? 'answered' : ''; ?> <?php echo $index === 0 ? 'active' : ''; ?>"
                                 data-target="<?php echo $index + 1; ?>">
                                <?php echo $index + 1; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="question-nav-item mr-2" style="background-color: #f8f9fa;"></div>
                                <small>Not answered</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="question-nav-item mr-2" style="background-color: #4caf50; color: white;"></div>
                                <small>Answered</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Exam Instructions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($exam['instructions'])): ?>
                            <?php echo nl2br(htmlspecialchars($exam['instructions'])); ?>
                        <?php else: ?>
                            <ol>
                                <li>Answer all questions to the best of your ability.</li>
                                <li>Select the appropriate option for each question.</li>
                                <li>Your answers are automatically saved as you progress.</li>
                                <li>Click "Submit Exam" when you are finished.</li>
                                <li>The exam will automatically submit when time expires.</li>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirm Submit Modal -->
    <div class="modal fade" id="confirmSubmitModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Submit Exam?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to submit the exam? You have answered <strong id="answered-count"><?php echo $answeredCount; ?></strong> out of <strong><?php echo count($questions); ?></strong> questions.</p>
                    
                    <?php if ($answeredCount < count($questions)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle"></i> Warning: You have not answered all questions.
                        </div>
                    <?php endif; ?>
                    
                    <p>Once submitted, you will not be able to change your answers.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <form method="post" action="">
                        <button type="submit" name="submit_exam" class="btn btn-warning">
                            <i class="fas fa-check"></i> Submit Exam
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Hide all questions except the first one
            $(".question-card").hide();
            $("#question-1").show();
            
            // Navigation buttons
            $(".next-question, .prev-question").click(function() {
                const targetQuestion = $(this).data("target");
                $(".question-card").hide();
                $("#question-" + targetQuestion).show();
                
                // Update active navigation item
                $(".question-nav-item").removeClass("active");
                $(`.question-nav-item[data-target="${targetQuestion}"]`).addClass("active");
                
                // Scroll to top of question
                $('html, body').animate({
                    scrollTop: $("#question-" + targetQuestion).offset().top - 20
                }, 200);
            });
            
            // Question navigation
            $(".question-nav-item").click(function() {
                const targetQuestion = $(this).data("target");
                $(".question-card").hide();
                $("#question-" + targetQuestion).show();
                
                // Update active navigation item
                $(".question-nav-item").removeClass("active");
                $(this).addClass("active");
                
                // Scroll to top of question
                $('html, body').animate({
                    scrollTop: $("#question-" + targetQuestion).offset().top - 20
                }, 200);
            });
            
            // Autosave answers when an option is selected
            $(".option-input").change(function() {
                const questionId = $(this).data("question-id");
                const value = $(this).val();
                
                // Set values in hidden fields
                $("#question_id").val(questionId);
                
                if ($(this).attr("type") === "radio") {
                    if ($(this).attr("id").includes("-true") || $(this).attr("id").includes("-false")) {
                        // True/False question
                        $("#text_answer").val(value);
                        $("#selected_option_id").val("");
                    } else {
                        // Multiple choice question
                        $("#selected_option_id").val(value);
                        $("#text_answer").val("");
                    }
                }
                
                // Save via AJAX
                $.ajax({
                    type: "POST",
                    url: window.location.href,
                    data: $("#examForm").serialize(),
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                // Mark question as answered
                                $(`#question-${$(".question-card:visible").attr("id").split("-")[1]}`).addClass("answered");
                                $(`.question-nav-item[data-target="${$(".question-card:visible").attr("id").split("-")[1]}"]`).addClass("answered");
                                
                                // Update progress
                                updateProgress();
                            }
                        } catch (e) {
                            console.error("Error parsing response:", e);
                        }
                    }
                });
            });
            
            // Timer logic
            let remainingSeconds = <?php echo $remainingSeconds; ?>;
            const timerInterval = setInterval(function() {
                remainingSeconds--;
                
                if (remainingSeconds <= 0) {
                    clearInterval(timerInterval);
                    
                    // Force submission
                    alert("Time's up! Your exam will be submitted now.");
                    window.location.href = "<?php echo $_SERVER['PHP_SELF'] . '?exam_id=' . $exam_id . '&time_up=1'; ?>";
                    return;
                }
                
                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = remainingSeconds % 60;
                
                $("#minutes").text(minutes.toString().padStart(2, '0'));
                $("#seconds").text(seconds.toString().padStart(2, '0'));
                
                // Add warning color when time is running out
                if (remainingSeconds <= 300) { // Less than 5 minutes
                    $("#timer").addClass("time-warning");
                }
            }, 1000);
            
            // Function to update progress
            function updateProgress() {
                const totalQuestions = <?php echo count($questions); ?>;
                const answeredQuestions = $(".question-card.answered").length;
                const progressPercentage = Math.round((answeredQuestions / totalQuestions) * 100);
                
                $(".progress-bar").css("width", progressPercentage + "%").attr("aria-valuenow", progressPercentage);
                $(".text-muted").text(`${answeredQuestions} of ${totalQuestions} questions answered (${progressPercentage}%)`);
                $("#answered-count").text(answeredQuestions);
            }
            
            // Update progress initially
            updateProgress();
            
            // Warn before leaving page
            window.addEventListener('beforeunload', function(e) {
                // Cancel the event
                e.preventDefault();
                // Chrome requires returnValue to be set
                e.returnValue = '';
            });
            
            // Don't warn when submitting the form
            $('form').submit(function() {
                window.removeEventListener('beforeunload', function() {});
            });
        });
    </script>
</body>
</html> 