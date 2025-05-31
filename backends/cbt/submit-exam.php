<?php
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';

session_start();

if (!isset($_SESSION['exam_attempt'])) {
    header('Location: dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$attempt = $_SESSION['exam_attempt'];
$timeout = isset($_GET['timeout']) && $_GET['timeout'] == 1;

// Calculate score
$score = 0;
$total_questions = count($attempt['questions']);

if (!$timeout && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['answers'] as $question_id => $answer) {
        // Get correct answer
        $query = "SELECT correct_answer FROM ace_school_system.questions WHERE id = :question_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':question_id' => $question_id]);
        $correct = $stmt->fetch(PDO::FETCH_ASSOC)['correct_answer'];
        
        if ($answer === $correct) {
            $score++;
        }

        // Save student response
        $response_query = "INSERT INTO ace_school_system.student_responses (attempt_id, question_id, selected_answer) 
                          VALUES (:attempt_id, :question_id, :answer)";
        $stmt = $db->prepare($response_query);
        $stmt->execute([
            ':attempt_id' => $attempt['attempt_id'],
            ':question_id' => $question_id,
            ':answer' => $answer
        ]);
    }
}

// Update attempt status and score
$update_query = "UPDATE ace_school_system.exam_attempts 
                SET status = 'completed', 
                    score = :score, 
                    end_time = NOW() 
                WHERE id = :attempt_id";
$stmt = $db->prepare($update_query);
$stmt->execute([
    ':score' => $score,
    ':attempt_id' => $attempt['attempt_id']
]);

// Clear exam session
unset($_SESSION['exam_attempt']);

// Redirect to results page
header("Location: exam-results.php?attempt=" . $attempt['attempt_id']);
exit();
?> 