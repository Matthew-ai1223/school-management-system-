<?php
// Include database configuration
require_once 'backends/config.php';
require_once 'backends/database.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Add class column
$sql = "ALTER TABLE students ADD COLUMN class VARCHAR(50) NULL AFTER email";
if ($conn->query($sql)) {
    echo "Class column added successfully!";
} else {
    echo "Error adding class column: " . $conn->error;
}
?>