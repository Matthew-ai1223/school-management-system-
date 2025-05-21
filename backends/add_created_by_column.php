<?php
// Database connection details (these should match your database configuration)
$host = 'localhost';
$username = 'root';  // Default XAMPP username
$password = '';      // Default XAMPP password
$dbname = 'ace_school_system';  // The correct database name as defined in config.php

echo "<h2>Database Update Tool</h2>";

try {
    // Create connection
    $conn = new mysqli($host, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<p>Connected to database successfully.</p>";
    
    // First, check if the 'payments' table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'payments'";
    $tableResult = $conn->query($tableCheckQuery);
    
    if ($tableResult->num_rows == 0) {
        echo "<p style='color: red;'>Error: The payments table does not exist in the database.</p>";
    } else {
        echo "<p>The payments table exists in the database.</p>";
        
        // Check if the 'created_by' column already exists
        $columnCheckQuery = "SHOW COLUMNS FROM payments LIKE 'created_by'";
        $columnResult = $conn->query($columnCheckQuery);
        
        if ($columnResult->num_rows > 0) {
            echo "<p>The 'created_by' column already exists in the payments table.</p>";
        } else {
            // Add the 'created_by' column
            $alterQuery = "ALTER TABLE payments ADD COLUMN created_by INT NULL AFTER status";
            
            if ($conn->query($alterQuery) === TRUE) {
                echo "<p style='color: green;'>Success: The 'created_by' column has been added to the payments table.</p>";
            } else {
                echo "<p style='color: red;'>Error adding column: " . $conn->error . "</p>";
            }
        }
    }

    // Close the connection
    $conn->close();
    echo "<p>Database connection closed.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/dashboard.php'>Return to Dashboard</a></p>";
?> 