<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

session_start();

error_log("=== Exam Start Attempt ===");
error_log("Session student_id: " . (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'Not set'));

if (!isset($_SESSION['student_id'])) {
    error_log("No student_id in session - redirecting to login");
    header('Location: login.php');
    exit();
}

$exam_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$exam_id) {
    header('Location: dashboard.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify student's status
    $stmt = $db->prepare("SELECT * FROM students WHERE id = :student_id AND status IN ('active', 'registered')");
    $stmt->execute([':student_id' => $_SESSION['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        error_log("Student not found or not active - redirecting to login");
        session_destroy();
        header('Location: login.php?error=not_found');
        exit();
    }

    // Check if user has remaining attempts
    $attempt_query = "SELECT COUNT(*) as attempts FROM ace_school_system.exam_attempts 
                     WHERE student_id = :student_id AND exam_id = :exam_id";
    $stmt = $db->prepare($attempt_query);
    $stmt->execute([
        ':student_id' => $_SESSION['student_id'],
        ':exam_id' => $exam_id
    ]);
    $attempt_count = $stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

    // Get exam details
    $exam_query = "SELECT * FROM ace_school_system.exams WHERE id = :exam_id AND is_active = true AND (class = :class OR class = 'all')";
    $stmt = $db->prepare($exam_query);
    $stmt->execute([
        ':exam_id' => $exam_id,
        ':class' => $student['class']
    ]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        $_SESSION['error'] = "This exam is not available for your class.";
        header('Location: dashboard.php');
        exit();
    }

    if ($attempt_count >= $exam['max_attempts']) {
        $_SESSION['error'] = "You have exceeded the maximum number of attempts for this exam.";
        header('Location: dashboard.php');
        exit();
    }

    // Create new attempt
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime("+{$exam['duration']} minutes"));

    $attempt_insert = "INSERT INTO ace_school_system.exam_attempts (student_id, exam_id, start_time, end_time) 
                      VALUES (:student_id, :exam_id, :start_time, :end_time)";
    $stmt = $db->prepare($attempt_insert);
    $stmt->execute([
        ':student_id' => $_SESSION['student_id'],
        ':exam_id' => $exam_id,
        ':start_time' => $start_time,
        ':end_time' => $end_time
    ]);

    $attempt_id = $db->lastInsertId();

    // Get randomized questions
    $questions_query = "SELECT * FROM ace_school_system.questions WHERE exam_id = :exam_id ORDER BY RAND()";
    $stmt = $db->prepare($questions_query);
    $stmt->execute([':exam_id' => $exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $_SESSION['exam_attempt'] = [
        'attempt_id' => $attempt_id,
        'end_time' => $end_time,
        'questions' => $questions
    ];

    header('Location: exam.php');
    exit();

} catch (PDOException $e) {
    error_log("Database error in start-exam.php: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while starting the exam. Please try again.";
    header('Location: dashboard.php');
    exit();
}
?> 