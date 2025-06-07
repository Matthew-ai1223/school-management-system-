<?php
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Drop the existing report_cards table if it exists
    $conn->query("DROP TABLE IF EXISTS report_cards");
    
    // Create report_cards table with proper structure
    $sql = "CREATE TABLE report_cards (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        term VARCHAR(20) NOT NULL,
        class VARCHAR(50) NOT NULL,
        total_score DECIMAL(5,2),
        average_score DECIMAL(5,2),
        position_in_class INT,
        total_students INT,
        teacher_comment TEXT,
        principal_comment TEXT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id),
        FOREIGN KEY (created_by) REFERENCES teachers(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "Report cards table created successfully.\n";
    } else {
        echo "Error creating report_cards table: " . $conn->error . "\n";
    }
    
    // Verify the table structure
    $result = $conn->query("DESCRIBE report_cards");
    if ($result) {
        echo "\nReport cards table structure:\n";
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 