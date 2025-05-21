<?php
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if the column exists
$checkColumnQuery = "SHOW COLUMNS FROM users LIKE 'last_login'";
$columnResult = $conn->query($checkColumnQuery);

if ($columnResult && $columnResult->num_rows == 0) {
    // Column doesn't exist, add it
    $alterQuery = "ALTER TABLE users ADD COLUMN last_login DATETIME NULL DEFAULT NULL";
    if ($conn->query($alterQuery)) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>
            <h3>Success!</h3>
            <p>The 'last_login' column has been added to the users table.</p>
            <p><a href='login.php'>Return to login page</a></p>
        </div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>
            <h3>Error!</h3>
            <p>Failed to add the 'last_login' column: " . $conn->error . "</p>
        </div>";
    }
} else {
    echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px;'>
        <h3>Notice</h3>
        <p>The 'last_login' column already exists in the users table.</p>
        <p><a href='login.php'>Return to login page</a></p>
    </div>";
}
?> 