<?php
// Set up test session variables
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['name'] = 'Test Admin';

// Define paths for required files
define('BASE_PATH', dirname(dirname(__FILE__)));
require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

echo "Database connection successful. Now showing exam results:<br>";

// Get exams for admin
$query = "SELECT e.*, s.subject_name, c.class_name 
          FROM cbt_exams e
          JOIN subjects s ON e.subject_id = s.id
          JOIN classes c ON e.class_id = c.id
          ORDER BY e.exam_date DESC LIMIT 5";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "Found exam: " . $row['exam_title'] . " - " . $row['subject_name'] . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn);
}

echo "<br>Test completed successfully.";
?> 