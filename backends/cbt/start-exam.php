<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("=== Exam Start Attempt ===");
error_log("Session student_id: " . (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'Not set'));

if (!isset($_SESSION['student_id'])) {
    error_log("No student_id in session - redirecting to login");
    header('Location: login.php?error=no_session');
    exit();
}

$exam_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$exam_id) {
    error_log("Invalid exam ID provided: " . ($_GET['id'] ?? 'not set'));
    header('Location: dashboard.php?error=invalid_exam');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify student's status
    $stmt = $db->prepare("SELECT * FROM students WHERE id = :student_id AND status IN ('active', 'registered')");
    if (!$stmt->execute([':student_id' => $_SESSION['student_id']])) {
        error_log("Error verifying student: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Error verifying student status");
    }
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        error_log("Student not found or not active - redirecting to login");
        session_destroy();
        header('Location: login.php?error=not_found');
        exit();
    }

    // Check if user has remaining attempts
    $attempt_query = "SELECT COUNT(*) as attempts FROM exam_attempts 
                     WHERE student_id = :student_id AND exam_id = :exam_id";
    $stmt = $db->prepare($attempt_query);
    if (!$stmt->execute([
        ':student_id' => $_SESSION['student_id'],
        ':exam_id' => $exam_id
    ])) {
        error_log("Error checking attempts: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Error checking exam attempts");
    }
    $attempt_count = $stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

    // Get exam details
    $exam_query = "SELECT * FROM exams WHERE id = :exam_id AND is_active = true AND (class = :class OR class = 'all')";
    $stmt = $db->prepare($exam_query);
    if (!$stmt->execute([
        ':exam_id' => $exam_id,
        ':class' => $student['class']
    ])) {
        error_log("Error fetching exam details: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Error retrieving exam information");
    }
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        error_log("Exam not available for student class: " . $student['class']);
        $_SESSION['error'] = "This exam is not available for your class.";
        header('Location: dashboard.php');
        exit();
    }

    if ($attempt_count >= $exam['max_attempts']) {
        error_log("Maximum attempts reached for exam ID: " . $exam_id);
        $_SESSION['error'] = "You have exceeded the maximum number of attempts for this exam.";
        header('Location: dashboard.php');
        exit();
    }

    // Create new attempt
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime("+{$exam['duration']} minutes"));

    $attempt_insert = "INSERT INTO exam_attempts (student_id, exam_id, start_time, end_time, status) 
                      VALUES (:student_id, :exam_id, :start_time, :end_time, 'in_progress')";
    $stmt = $db->prepare($attempt_insert);
    if (!$stmt->execute([
        ':student_id' => $_SESSION['student_id'],
        ':exam_id' => $exam_id,
        ':start_time' => $start_time,
        ':end_time' => $end_time
    ])) {
        error_log("Error creating exam attempt: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Error starting exam attempt");
    }

    $attempt_id = $db->lastInsertId();
    error_log("Created exam attempt ID: " . $attempt_id);

    // Get randomized questions
    $questions_query = "SELECT * FROM questions WHERE exam_id = :exam_id ORDER BY RAND()";
    $stmt = $db->prepare($questions_query);
    if (!$stmt->execute([':exam_id' => $exam_id])) {
        error_log("Error fetching exam questions: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Error loading exam questions");
    }
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        error_log("No questions found for exam ID: " . $exam_id);
        throw new Exception("No questions found for this exam");
    }

    $_SESSION['exam_attempt'] = [
        'attempt_id' => $attempt_id,
        'end_time' => $end_time,
        'questions' => $questions
    ];

    error_log("Exam started successfully - Attempt ID: " . $attempt_id);
    header('Location: exam.php');
    exit();

} catch (PDOException $e) {
    error_log("Database error in start-exam.php: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error'] = "An error occurred while starting the exam. Please try again.";
    header('Location: dashboard.php');
    exit();
} catch (Exception $e) {
    error_log("General error in start-exam.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error'] = $e->getMessage();
    header('Location: dashboard.php');
    exit();
}
?> 