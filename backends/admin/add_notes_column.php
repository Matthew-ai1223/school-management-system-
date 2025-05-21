<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Check if user is logged in as admin
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'admin') {
    echo "Access denied. Admin login required.";
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Add notes column to payments table if it doesn't exist
$query = "
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'payments' 
    AND COLUMN_NAME = 'notes'
";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    // Column doesn't exist, add it
    $alterQuery = "ALTER TABLE payments ADD COLUMN notes TEXT NULL AFTER status";
    
    if ($conn->query($alterQuery)) {
        echo "Success: Notes column added to payments table.<br>";
    } else {
        echo "Error adding notes column: " . $conn->error . "<br>";
    }
} else {
    echo "Notes column already exists in payments table.<br>";
}

echo "Script completed. <a href='dashboard.php'>Return to Dashboard</a>";
?> 