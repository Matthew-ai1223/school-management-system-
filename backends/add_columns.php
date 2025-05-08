<?php
// Script to add missing columns to existing tables
require_once 'database.php';

// Create a database connection
$database = new Database();
$conn = $database->getConnection();

try {
    // Check if activity_logs table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    if ($stmt->rowCount() == 0) {
        // Create activity_logs table
        $conn->exec("CREATE TABLE activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");
        echo "Created 'activity_logs' table.<br>";
    } else {
        // Check if the action column exists in activity_logs
        $stmt = $conn->query("SHOW COLUMNS FROM activity_logs LIKE 'action'");
        if ($stmt->rowCount() == 0) {
            // Add the action column
            $conn->exec("ALTER TABLE activity_logs ADD COLUMN action VARCHAR(50) NOT NULL AFTER user_id");
            echo "Added 'action' column to activity_logs table.<br>";
        } else {
            echo "'action' column already exists in activity_logs table.<br>";
        }
    }
    
    // Check if the class_type column exists in the students table
    $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'class_type'");
    if ($stmt->rowCount() == 0) {
        // Add the class_type column
        $conn->exec("ALTER TABLE students ADD COLUMN class_type VARCHAR(50) AFTER class_id");
        echo "Added 'class_type' column to students table.<br>";
    } else {
        echo "'class_type' column already exists in students table.<br>";
    }
    
    // Check if the previous_school column exists in the students table
    $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'previous_school'");
    if ($stmt->rowCount() == 0) {
        // Add the previous_school column
        $conn->exec("ALTER TABLE students ADD COLUMN previous_school VARCHAR(100) AFTER parent_address");
        echo "Added 'previous_school' column to students table.<br>";
    } else {
        echo "'previous_school' column already exists in students table.<br>";
    }
    
    // Check if the registration_number column exists in the students table
    $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'registration_number'");
    if ($stmt->rowCount() == 0) {
        // Add the registration_number column
        $conn->exec("ALTER TABLE students ADD COLUMN registration_number VARCHAR(20) AFTER previous_school");
        echo "Added 'registration_number' column to students table.<br>";
    } else {
        echo "'registration_number' column already exists in students table.<br>";
    }
    
    echo "<br>Database update completed successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 