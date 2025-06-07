<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Check if user is logged in and is a teacher
// session_start();
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
//     header('HTTP/1.1 403 Forbidden');
//     exit('Unauthorized');
// }

if (!isset($_GET['class'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Class parameter is required');
}

$class = $_GET['class'];

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $class = $conn->real_escape_string($class);
    $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM students WHERE class = '$class' ORDER BY first_name, last_name";
    $result = $conn->query($sql);
    
    $students = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($students);
} catch(Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?> 