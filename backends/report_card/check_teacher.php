<?php
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Check if teachers table exists
    $result = $conn->query("SHOW TABLES LIKE 'teachers'");
    if ($result->num_rows == 0) {
        echo "Teachers table does not exist. Creating it...\n";
        
        // Create teachers table
        $sql = "CREATE TABLE IF NOT EXISTS teachers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql)) {
            echo "Teachers table created successfully.\n";
            
            // Insert a default teacher
            $sql = "INSERT INTO teachers (first_name, last_name, email) VALUES ('Default', 'Teacher', 'teacher@school.com')";
            if ($conn->query($sql)) {
                echo "Default teacher created with ID: " . $conn->insert_id . "\n";
            } else {
                echo "Error creating default teacher: " . $conn->error . "\n";
            }
        } else {
            echo "Error creating teachers table: " . $conn->error . "\n";
        }
    } else {
        // Check if there are any teachers
        $result = $conn->query("SELECT * FROM teachers");
        if ($result->num_rows == 0) {
            echo "No teachers found. Creating a default teacher...\n";
            
            // Insert a default teacher
            $sql = "INSERT INTO teachers (first_name, last_name, email) VALUES ('Default', 'Teacher', 'teacher@school.com')";
            if ($conn->query($sql)) {
                echo "Default teacher created with ID: " . $conn->insert_id . "\n";
            } else {
                echo "Error creating default teacher: " . $conn->error . "\n";
            }
        } else {
            echo "Found " . $result->num_rows . " teachers:\n";
            while ($row = $result->fetch_assoc()) {
                echo "ID: " . $row['id'] . ", Name: " . $row['first_name'] . " " . $row['last_name'] . "\n";
            }
        }
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 