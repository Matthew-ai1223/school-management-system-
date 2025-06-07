<?php
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop all related tables
    $tables = [
        'report_card_details',
        'report_cards',
        'report_subjects',
        'teachers'
    ];
    
    foreach ($tables as $table) {
        $conn->query("DROP TABLE IF EXISTS $table");
        echo "Dropped table $table\n";
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Create teachers table
    $sql = "CREATE TABLE teachers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "Teachers table created successfully.\n";
        
        // Insert default teacher
        $sql = "INSERT INTO teachers (first_name, last_name, email) VALUES ('Default', 'Teacher', 'teacher@school.com')";
        if ($conn->query($sql)) {
            $teacher_id = $conn->insert_id;
            echo "Default teacher created with ID: " . $teacher_id . "\n";
        }
    }
    
    // Create subjects table
    $sql = "CREATE TABLE report_subjects (
        id INT PRIMARY KEY AUTO_INCREMENT,
        subject_name VARCHAR(100) NOT NULL,
        subject_code VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_subject_code (subject_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "Subjects table created successfully.\n";
    }
    
    // Create report_cards table
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
    }
    
    // Create report_card_details table
    $sql = "CREATE TABLE report_card_details (
        id INT PRIMARY KEY AUTO_INCREMENT,
        report_card_id INT NOT NULL,
        subject_id INT NOT NULL,
        test_score DECIMAL(5,2),
        exam_score DECIMAL(5,2),
        total_score DECIMAL(5,2),
        grade VARCHAR(2),
        remark VARCHAR(100),
        teacher_comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (report_card_id) REFERENCES report_cards(id),
        FOREIGN KEY (subject_id) REFERENCES report_subjects(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "Report card details table created successfully.\n";
    }
    
    // Verify tables were created
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "\nCreated tables:\n";
        while ($row = $result->fetch_array()) {
            echo $row[0] . "\n";
        }
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Make sure foreign key checks are re-enabled
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}
?> 