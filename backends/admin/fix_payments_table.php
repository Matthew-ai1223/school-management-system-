<?php
// Database connection details
$host = 'localhost';
$dbname = 'ace_school_system';
$username = 'root';
$password = '';

// Create a direct database connection
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Database connection successful.<br>";
    
    // Add notes column to payments table if it doesn't exist
    $query = "
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '$dbname' 
        AND TABLE_NAME = 'payments' 
        AND COLUMN_NAME = 'notes'
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        echo "Error checking column: " . $conn->error . "<br>";
    } else {
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
    }
    
    // Check for created_by column
    $createdByQuery = "
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '$dbname' 
        AND TABLE_NAME = 'payments' 
        AND COLUMN_NAME = 'created_by'
    ";
    
    $createdByResult = $conn->query($createdByQuery);
    
    if (!$createdByResult) {
        echo "Error checking column: " . $conn->error . "<br>";
    } else {
        if ($createdByResult->num_rows === 0) {
            // Column doesn't exist, add it
            $alterCreatedByQuery = "ALTER TABLE payments ADD COLUMN created_by INT NULL AFTER notes";
            
            if ($conn->query($alterCreatedByQuery)) {
                echo "Success: created_by column added to payments table.<br>";
            } else {
                echo "Error adding created_by column: " . $conn->error . "<br>";
            }
        } else {
            echo "created_by column already exists in payments table.<br>";
        }
    }
    
    $conn->close();
    echo "Script completed. <a href='dashboard.php'>Return to Dashboard</a>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 