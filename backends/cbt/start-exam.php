<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$exam_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$exam_id) {
    header('Location: dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Verify user's active status
$table = $_SESSION['user_table'];
$stmt = $db->prepare("SELECT * FROM $table WHERE id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if account is still active and not expired
$is_expired = strtotime($user['expiration_date']) < strtotime('today');
if (!$user['is_active'] || $is_expired) {
    session_destroy();
    header('Location: login.php?error=expired');
    exit();
}

// Check if user has remaining attempts
$attempt_query = "SELECT COUNT(*) as attempts FROM exam_attempts 
                 WHERE user_id = :user_id AND exam_id = :exam_id";
$stmt = $db->prepare($attempt_query);
$stmt->execute([
    ':user_id' => $_SESSION['user_id'],
    ':exam_id' => $exam_id
]);
$attempt_count = $stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

// Get exam details
$exam_query = "SELECT * FROM exams WHERE id = :exam_id AND is_active = true";
$stmt = $db->prepare($exam_query);
$stmt->execute([':exam_id' => $exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam || $attempt_count >= $exam['max_attempts']) {
    $_SESSION['error'] = "You have exceeded the maximum number of attempts for this exam.";
    header('Location: dashboard.php');
    exit();
}

// Create new attempt
$start_time = date('Y-m-d H:i:s');
$end_time = date('Y-m-d H:i:s', strtotime("+{$exam['duration']} minutes"));

$attempt_insert = "INSERT INTO exam_attempts (user_id, exam_id, start_time, end_time) 
                  VALUES (:user_id, :exam_id, :start_time, :end_time)";
$stmt = $db->prepare($attempt_insert);
$stmt->execute([
    ':user_id' => $_SESSION['user_id'],
    ':exam_id' => $exam_id,
    ':start_time' => $start_time,
    ':end_time' => $end_time
]);

$attempt_id = $db->lastInsertId();

// Get randomized questions
$questions_query = "SELECT * FROM questions WHERE exam_id = :exam_id ORDER BY RAND()";
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
?> 