<?php
// Include required files
require_once '../config.php';
require_once '../database.php';

// Initialize database connection using the existing Database class
$db = Database::getInstance();
$conn = $db->getConnection();

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully<br>";

// Add column if it doesn't exist
$alterSql = "ALTER TABLE cbt_questions ADD COLUMN question_image VARCHAR(255) DEFAULT NULL";
if ($conn->query($alterSql) === TRUE) {
    echo "Column 'question_image' added successfully<br>";
} else {
    // If error is about column already exists, that's fine
    if (strpos($conn->error, "Duplicate column name") !== false) {
        echo "Column already exists<br>";
    } else {
        echo "Error: " . $conn->error . "<br>";
    }
}

// Create the upload directory
$uploadDir = '../uploads/question_images/';
if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0777, true)) {
        echo "Directory created successfully<br>";
    } else {
        echo "Failed to create directory<br>";
    }
} else {
    echo "Directory already exists<br>";
}

// No need to close - it's managed by the Database class
?> 