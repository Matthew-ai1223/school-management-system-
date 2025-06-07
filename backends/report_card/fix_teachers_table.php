<?php
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // First, drop the report_cards table if it exists
    $conn->query("DROP TABLE IF EXISTS report_cards");
    
    // Then drop the report_card_details table if it exists
    $conn->query("DROP TABLE IF EXISTS report_card_details");
    
    // Now we can safely drop the teachers table
    $conn->query("DROP TABLE IF EXISTS teachers");
    
    // Create teachers table with proper structure
    $sql = "CREATE TABLE teachers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "Teachers table created successfully.\n";
        
        // Insert a default teacher
        $sql = "INSERT INTO teachers (first_name, last_name, email) VALUES ('Default', 'Teacher', 'teacher@school.com')";
        if ($conn->query($sql)) {
            $teacher_id = $conn->insert_id;
            echo "Default teacher created with ID: " . $teacher_id . "\n";
            
            // Update the report_cards table to use this teacher ID
            $sql = "UPDATE report_cards SET created_by = " . $teacher_id . " WHERE created_by IS NULL OR created_by NOT IN (SELECT id FROM teachers)";
            if ($conn->query($sql)) {
                echo "Updated report_cards table to use the default teacher.\n";
            } else {
                echo "Error updating report_cards: " . $conn->error . "\n";
            }
        } else {
            echo "Error creating default teacher: " . $conn->error . "\n";
        }
    } else {
        echo "Error creating teachers table: " . $conn->error . "\n";
    }
    
    // Verify the teacher exists
    $result = $conn->query("SELECT * FROM teachers");
    if ($result && $result->num_rows > 0) {
        echo "\nCurrent teachers in database:\n";
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . ", Name: " . $row['first_name'] . " " . $row['last_name'] . "\n";
        }
    } else {
        echo "No teachers found in database!\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 