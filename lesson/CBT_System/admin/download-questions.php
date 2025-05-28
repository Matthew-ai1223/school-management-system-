<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// Check if user is logged in and is an admin
// if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
//     header('Location: login.php');
//     exit();
// }

$db = Database::getInstance()->getConnection();

// Adjust the query to match your actual table structure
$query = "SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, e.title as exam_title
          FROM questions q
          JOIN exams e ON q.exam_id = e.id";
$stmt = $db->query($query);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="questions.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, ['ID', 'Question Text', 'Option A', 'Option B', 'Option C', 'Option D', 'Exam Title']);

// Write question data to CSV
foreach ($questions as $question) {
    fputcsv($output, $question);
}

// Close output stream
fclose($output);
exit();