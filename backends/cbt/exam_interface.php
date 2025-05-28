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

// Set timezone to UTC for consistent timestamps
date_default_timezone_set('UTC');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$error = '';
$success = '';
$exam = null;
$questions = [];
$current_question = 0;
$time_remaining = 0;
$student_answers = [];

// Check if session_id is provided
if (!isset($_GET['session_id'])) {
    error_log("No session_id provided in exam_interface.php");
    $_SESSION['error_message'] = "No exam session found. Please select an exam first.";
    header("Location: take_exam.php");
    exit;
}

$session_id = $_GET['session_id'];
error_log("Starting exam interface with session_id: $session_id");

// Validate student is logged in
if (!isset($_SESSION['student_id'])) {
    error_log("No student_id in session for exam_interface.php");
    $_SESSION['error_message'] = "Please log in to take the exam.";
    header("Location: take_exam.php");
    exit;
}

// Initialize database
$testDb = TestDatabase::getInstance();
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Get exam session details with duration
    $stmt = $conn->prepare("
        SELECT sa.*, e.*, e.duration as exam_duration,
               UNIX_TIMESTAMP(sa.start_time) as start_timestamp,
               UNIX_TIMESTAMP(sa.start_time) + (e.duration * 60) as end_timestamp
        FROM cbt_student_attempts sa 
        JOIN cbt_exams e ON e.id = sa.exam_id 
        WHERE sa.id = ? AND sa.student_id = ? AND sa.status = 'In Progress'
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $session_id, $_SESSION['student_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No valid exam session found");
    }
    
    $exam_session = $result->fetch_assoc();
    
    // Get exam questions
    $questions = $testDb->getExamQuestions($exam_session['exam_id']);
    
    // After fetching $questions, process options for each question
    foreach ($questions as &$question) {
        if (($question['question_type'] ?? '') === 'Multiple Choice') {
            $question['processed_options'] = [];
            foreach (['option_a', 'option_b', 'option_c', 'option_d'] as $optKey) {
                if (!empty($question[$optKey])) {
                    $question['processed_options'][] = [
                        'text' => $question[$optKey]
                    ];
                }
            }
        } elseif (($question['question_type'] ?? '') === 'True/False') {
            $question['processed_options'] = [
                ['text' => 'True'],
                ['text' => 'False']
            ];
        }
    }
    unset($question); // break reference
    
    // Calculate time remaining using UTC timestamps
    $current_time = time(); // UTC timestamp
    $end_time = $exam_session['end_timestamp']; // UTC timestamp from database
    $time_remaining = max(0, $end_time - $current_time);

    error_log("Time calculation (UTC) - End time: " . date('Y-m-d H:i:s', $end_time) . 
              ", Current time: " . date('Y-m-d H:i:s', $current_time) . 
              ", Remaining: $time_remaining seconds");

    // Only auto-submit if time is actually up and this is not an AJAX request
    if ($time_remaining <= 0 && !isset($_POST['action'])) {
        error_log("Auto-submitting exam - Time remaining: $time_remaining seconds");
        // Auto-submit exam if time is up
        $result = $testDb->submitExam($session_id);
        if ($result['success']) {
            error_log("Auto-submit successful");
            header("Location: view_result.php?session_id=" . $session_id);
            exit;
        } else {
            error_log("Auto-submit failed: " . $result['message']);
            throw new Exception("Failed to submit exam: " . $result['message']);
        }
    }
    
    // Get student's previous answers
    $stmt = $conn->prepare("
        SELECT question_id, selected_answer 
        FROM cbt_student_answers 
        WHERE attempt_id = ?
    ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $answers_result = $stmt->get_result();
    
    while ($answer = $answers_result->fetch_assoc()) {
        $student_answers[$answer['question_id']] = $answer['selected_answer'];
    }
    
} catch (Exception $e) {
    error_log("Error in exam interface: " . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: take_exam.php");
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_answer':
            $question_id = $_POST['question_id'] ?? null;
            $answer = $_POST['answer'] ?? null;
            
            if ($question_id && $answer !== null) {
                $result = $testDb->saveStudentAnswer($session_id, $question_id, $answer);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Missing question_id or answer']);
            }
            exit;
            
        case 'submit_exam':
            $result = $testDb->submitExam($session_id);
            echo json_encode($result);
            exit;
            
        case 'sync_time':
            $current_time = time();
            $end_time = $exam_session['end_timestamp'];
            $remaining_time = max(0, $end_time - $current_time);
            
            echo json_encode([
                'success' => true,
                'server_time' => $current_time,
                'end_time' => $end_time,
                'remaining_time' => $remaining_time
            ]);
            exit;
    }
}

// Add these variables for JavaScript
$exam_end_time = $exam_session['end_timestamp'];
$current_server_time = time();
$exam_duration = intval($exam_session['exam_duration'] ?? 60); // Default to 60 minutes if not set
$start_time = $exam_session['start_timestamp'];
$time_elapsed = $current_server_time - $start_time;
$time_remaining = max(0, ($exam_duration * 60) - $time_elapsed); // Convert duration to seconds
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Exam Interface - <?php echo SCHOOL_NAME; ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap & FontAwesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6fb;
            min-height: 100vh;
            margin: 0;
        }
        .exam-header {
            background: linear-gradient(90deg, #1a237e 0%, #3949ab 100%);
            color: #fff;
            padding: 0.75rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(30,40,90,0.08);
        }
        .exam-header .logo {
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: 1px;
            margin-right: 1.5rem;
        }
        .exam-header .exam-title {
            font-size: 1.1rem;
            font-weight: 600;
            flex: 1;
        }
        .exam-header .user-info {
            font-size: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 0.3rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .exam-timer-card {
            background: #e3f0ff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(30,40,90,0.07);
            padding: 1.5rem 2.5rem;
            margin: 2rem auto 1.5rem auto;
            max-width: 340px;
            text-align: center;
        }
        .exam-timer-card .timer-digits {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: #1a237e;
        }
        .exam-timer-card .timer-labels {
            font-size: 0.9rem;
            color: #3949ab;
            margin-top: 0.2rem;
        }
        .exam-timer-card.danger {
            background: #ffeaea;
            color: #e74a3b;
        }
        .exam-timer-card.warning {
            background: #fff8e1;
            color: #f6c23e;
        }
        .main-content {
            display: flex;
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding-bottom: 2rem;
        }
        .question-panel {
            flex: 2;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(30,40,90,0.08);
            padding: 2rem 2rem 1.5rem 2rem;
            min-height: 500px;
        }
        .question-number {
            font-size: 1.1rem;
            color: #3949ab;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .question-text {
            font-size: 1.15rem;
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }
        .options-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .option-item {
            background: #f4f6fb;
            border: 2px solid #e3e7f0;
            border-radius: 10px;
            padding: 1rem 1.2rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        .option-item.selected, .option-item:hover {
            border-color: #1a237e;
            background: #e3f0ff;
        }
        .option-item input[type="radio"] {
            accent-color: #1a237e;
            width: 1.2em;
            height: 1.2em;
        }
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }
        .btn-previous, .btn-next {
            flex: 1;
            padding: 0.7rem 0;
            font-size: 1rem;
            border-radius: 8px;
        }
        .navigation-panel {
            flex: 1;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(30,40,90,0.08);
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            min-width: 270px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .progress {
            width: 100%;
            height: 10px;
            background: #e3e7f0;
            border-radius: 5px;
            margin-bottom: 1.2rem;
        }
        .progress-bar {
            height: 100%;
            background: #1cc88a;
            border-radius: 5px;
            transition: width 0.3s;
        }
        .nav-questions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .nav-button {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: none;
            background: #e3e7f0;
            color: #3949ab;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.2s;
            margin: 0.1rem;
        }
        .nav-button.current {
            background: #1a237e;
            color: #fff;
            box-shadow: 0 2px 8px rgba(30,40,90,0.10);
        }
        .nav-button.answered {
            background: #1cc88a;
            color: #fff;
        }
        .nav-button:hover {
            background: #3949ab;
            color: #fff;
        }
        .answered-remaining {
            width: 100%;
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.2rem;
        }
        .answered-remaining .badge {
            font-size: 0.95rem;
            padding: 0.5em 1em;
            border-radius: 12px;
        }
        .submit-section {
            margin-top: auto;
            width: 100%;
        }
        .btn-submit {
            width: 100%;
            padding: 0.9rem 0;
            font-size: 1.1rem;
            border-radius: 10px;
            font-weight: 600;
        }
        @media (max-width: 992px) {
            .main-content {
                flex-direction: column;
                gap: 1.5rem;
                padding: 0 0.5rem;
            }
            .navigation-panel {
                min-width: unset;
                width: 100%;
                margin-bottom: 1.5rem;
            }
        }
        @media (max-width: 600px) {
            .exam-header {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.7rem 0.7rem;
            }
            .exam-timer-card {
                padding: 1rem 0.5rem;
            }
            .main-content {
                padding: 0 0.2rem;
            }
            .question-panel, .navigation-panel {
                padding: 1rem 0.7rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="exam-header">
        <div class="logo"><i class="fas fa-graduation-cap mr-2"></i>ACE CBT</div>
        <div class="exam-title"><?php echo htmlspecialchars($exam_session['title']); ?></div>
        <div class="user-info">
            <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($_SESSION['student_name']); ?>
        </div>
    </div>
    <!-- Timer Card -->
    <div class="exam-timer-card" id="examTimerCard">
        <div class="timer-digits">
            <span id="hours">00</span> : <span id="minutes">00</span> : <span id="seconds">00</span>
        </div>
        <div class="timer-labels">HOURS &nbsp;&nbsp; MINUTES &nbsp;&nbsp; SECONDS</div>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Question Panel -->
        <div class="question-panel">
            <form id="examForm" method="POST">
                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                <?php
                if (empty(
                    $questions)) {
                    echo "<div class='alert alert-danger'>No questions found for this exam. (Exam ID: " . htmlspecialchars($exam_session['exam_id']) . ")</div>";
                } else {
                    echo "<div class='alert alert-info'>Loaded ".count($questions)." questions.</div>";
                }
                ?>
                <?php foreach ($questions as $index => $question): ?>
                    <?php // DEBUG: Show options array for each question
                    echo "<pre style='background:#fffbe6;border:1px solid #ffe58f;padding:8px;font-size:12px;'>";
                    echo "<b>Debug options for Q".($index+1).":</b> ";
                    print_r($question['processed_options'] ?? 'NO processed_options');
                    echo "</pre>";
                    ?>
                    <div class="question-container" id="question<?php echo $index; ?>" style="display: <?php echo $index === $current_question ? 'block' : 'none'; ?>;">
                        <div class="question-number">
                            Question <?php echo $index + 1; ?> of <?php echo count($questions); ?>
                        </div>
                        <div class="question-text">
                            <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                        </div>
                        <div class="options-container">
                            <?php if ($question['question_type'] === 'Multiple Choice'): ?>
                                <?php foreach ($question['processed_options'] as $option): ?>
                                    <label class="option-item <?php echo isset($student_answers[$question['id']]) && $student_answers[$question['id']] === $option['text'] ? 'selected' : ''; ?>">
                                        <input type="radio" 
                                               name="answers[<?php echo $question['id']; ?>]" 
                                               value="<?php echo htmlspecialchars($option['text']); ?>"
                                               style="margin-right: 0.7rem;"
                                               <?php echo isset($student_answers[$question['id']]) && $student_answers[$question['id']] === $option['text'] ? 'checked' : ''; ?>
                                               onclick="selectOption(<?php echo $index; ?>, '<?php echo htmlspecialchars(addslashes($option['text'])); ?>')">
                                        <?php echo htmlspecialchars($option['text']); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php elseif ($question['question_type'] === 'True/False'): ?>
                                <?php foreach (['True', 'False'] as $option): ?>
                                    <label class="option-item <?php echo isset($student_answers[$question['id']]) && $student_answers[$question['id']] === $option ? 'selected' : ''; ?>">
                                        <input type="radio" 
                                               name="answers[<?php echo $question['id']; ?>]" 
                                               value="<?php echo $option; ?>"
                                               style="margin-right: 0.7rem;"
                                               <?php echo isset($student_answers[$question['id']]) && $student_answers[$question['id']] === $option ? 'checked' : ''; ?>
                                               onclick="selectOption(<?php echo $index; ?>, '<?php echo $option; ?>')">
                                        <?php echo $option; ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons">
                            <?php if ($index > 0): ?>
                                <button type="button" class="btn btn-outline-primary btn-previous" onclick="showQuestion(<?php echo $index - 1; ?>)">
                                    <i class="fas fa-arrow-left mr-1"></i> Previous
                                </button>
                            <?php endif; ?>
                            <?php if ($index < count($questions) - 1): ?>
                                <button type="button" class="btn btn-primary btn-next" onclick="showQuestion(<?php echo $index + 1; ?>)">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </form>
        </div>
        <!-- Navigation Panel -->
        <div class="navigation-panel">
            <div class="progress">
                <div class="progress-bar bg-success" id="progressBar" role="progressbar" style="width: 0%"></div>
            </div>
            <div class="answered-remaining">
                <span class="badge badge-success">
                    <i class="fas fa-check mr-1"></i> <span id="answeredCount">0</span> Answered
                </span>
                <span class="badge badge-secondary">
                    <i class="fas fa-question mr-1"></i> <span id="remainingCount"><?php echo count($questions); ?></span> Left
                </span>
            </div>
            <div class="nav-questions">
                <?php for ($i = 0; $i < count($questions); $i++): ?>
                    <button type="button" 
                            class="nav-button" 
                            onclick="showQuestion(<?php echo $i; ?>)">
                        <?php echo $i + 1; ?>
                    </button>
                <?php endfor; ?>
            </div>
            <div class="submit-section">
                <button type="button" 
                        class="btn btn-success btn-submit"
                        onclick="confirmSubmit()">
                    <i class="fas fa-check-circle mr-1"></i> Submit Exam
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize variables
        const totalQuestions = <?php echo count($questions); ?>;
        const answeredQuestions = new Set(<?php 
            echo json_encode(array_map(function($id) use ($student_answers) {
                return array_search($id, array_keys($student_answers));
            }, array_keys($student_answers)));
        ?>);
        let currentQuestion = <?php echo $current_question; ?>;
        const sessionId = <?php echo $session_id; ?>;
        const examDuration = <?php echo $exam_duration; ?>; // Duration in minutes
        const examStartTime = <?php echo $start_time; ?>;
        const examEndTime = <?php echo $exam_end_time; ?>;
        const initialTimeRemaining = <?php echo $time_remaining; ?>;
        let serverClientTimeDiff = 0;
        let timerInterval;
        
        // Function to get current UTC timestamp in seconds
        function getCurrentUTCTimestamp() {
            return Math.floor(Date.now() / 1000);
        }
        
        // Function to get server time
        function getServerTime() {
            return getCurrentUTCTimestamp() + serverClientTimeDiff;
        }
        
        function updateTimerDisplay(hours, minutes, seconds) {
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            // Update timer card class based on remaining time
            const timerCard = document.getElementById('examTimerCard');
            const totalSeconds = (hours * 3600) + (minutes * 60) + seconds;
            timerCard.className = 'exam-timer-card';
            if (totalSeconds <= 300) { // 5 minutes
                timerCard.classList.add('danger');
            } else if (totalSeconds <= 600) { // 10 minutes
                timerCard.classList.add('warning');
            }
        }
        
        function updateTimer() {
            const currentTime = getCurrentUTCTimestamp();
            const remaining = Math.max(0, examEndTime - currentTime);
            
            if (remaining <= 0) {
                clearInterval(timerInterval);
                document.getElementById('examTimerCard').innerHTML = '<div class="alert alert-danger">Time\'s Up! Submitting exam...</div>';
                submitExam();
                return;
            }
            
            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;
            
            updateTimerDisplay(hours, minutes, seconds);
        }
        
        function syncWithServer() {
            console.log('Syncing time with server...');
            fetch('exam_interface.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=sync_time&session_id=${sessionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const serverTime = data.server_time;
                    const clientTime = getCurrentUTCTimestamp();
                    serverClientTimeDiff = serverTime - clientTime;
                    
                    console.log('Time sync:', {
                        serverTime: new Date(serverTime * 1000).toISOString(),
                        clientTime: new Date(clientTime * 1000).toISOString(),
                        timeDiff: serverClientTimeDiff,
                        remaining: data.remaining_time
                    });
                    
                    // Update timer immediately after sync
                    updateTimer();
                }
            })
            .catch(error => console.error('Error syncing time:', error));
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing exam interface...');
            
            // Perform initial server sync
            syncWithServer();
            
            // Initialize timer if exam is still in progress
            if (initialTimeRemaining > 0) {
                console.log('Starting timer with ' + initialTimeRemaining + ' seconds remaining');
                updateTimer();
                timerInterval = setInterval(updateTimer, 1000);
                
                // Sync with server every minute
                setInterval(syncWithServer, 60000);
            } else {
                console.log('No time remaining:', initialTimeRemaining);
                document.getElementById('examTimerCard').innerHTML = '<div class="alert alert-danger">Time\'s Up! Submitting exam...</div>';
                submitExam();
            }
            
            // Initialize progress and navigation
            updateProgress();
            updateNavButtons();
            
            // Prevent form submission on Enter key
            document.getElementById('examForm').onkeypress = function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    return false;
                }
            };
            
            // Show warning on page leave
            window.onbeforeunload = function() {
                return "Are you sure you want to leave? Your progress may be lost.";
            };
        });
        
        // Function to show a specific question
        function showQuestion(index) {
            document.querySelectorAll('.question-container').forEach(q => q.style.display = 'none');
            document.getElementById(`question${index}`).style.display = 'block';
            currentQuestion = index;
            updateNavButtons();
        }
        
        // Function to select an option
        function selectOption(questionIndex, option) {
            const questionId = <?php echo json_encode(array_column($questions, 'id')); ?>[questionIndex];
            
            document.querySelector(`input[name="answers[${questionId}]"][value="${option}"]`).checked = true;
            
            const options = document.querySelectorAll(`#question${questionIndex} .option-item`);
            options.forEach(opt => opt.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            answeredQuestions.add(questionIndex);
            
            // Save answer via AJAX
            fetch('exam_interface.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_answer&session_id=${sessionId}&question_id=${questionId}&answer=${encodeURIComponent(option)}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to save answer:', data.message);
                }
            })
            .catch(error => console.error('Error:', error));
            
            updateProgress();
            updateNavButtons();
        }
        
        // Function to update navigation buttons
        function updateNavButtons() {
            document.querySelectorAll('.nav-button').forEach((btn, index) => {
                btn.classList.remove('current', 'answered');
                if (index === currentQuestion) {
                    btn.classList.add('current');
                }
                if (answeredQuestions.has(index)) {
                    btn.classList.add('answered');
                }
            });
        }
        
        // Function to update progress
        function updateProgress() {
            const answered = answeredQuestions.size;
            const percentage = (answered / totalQuestions) * 100;
            
            document.getElementById('progressBar').style.width = `${percentage}%`;
            document.getElementById('answeredCount').textContent = answered;
            document.getElementById('remainingCount').textContent = totalQuestions - answered;
        }
        
        // Function to submit exam
        function submitExam() {
            const submitButton = document.querySelector('.btn-submit');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Submitting...';
            submitButton.disabled = true;
            
            fetch('exam_interface.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=submit_exam&session_id=${sessionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `view_result.php?session_id=${sessionId}`;
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to submit exam. Please try again.',
                        icon: 'error'
                    });
                    submitButton.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Submit Exam';
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to submit exam. Please try again.',
                    icon: 'error'
                });
                submitButton.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Submit Exam';
                submitButton.disabled = false;
            });
        }
        
        // Function to confirm exam submission
        function confirmSubmit() {
            const unanswered = totalQuestions - answeredQuestions.size;
            const warningMessage = unanswered > 0 ? 
                `You have ${unanswered} unanswered questions. Are you sure you want to submit?` :
                'Are you sure you want to submit your exam?';
            
            Swal.fire({
                title: 'Confirm Submission',
                text: warningMessage,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, submit!',
                cancelButtonText: 'No, review answers'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitExam();
                }
            });
        }
    </script>
</body>
</html>
