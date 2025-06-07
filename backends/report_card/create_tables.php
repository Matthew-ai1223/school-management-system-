<?php
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Create subjects table
    $sql = "CREATE TABLE IF NOT EXISTS report_subjects (
        id INT PRIMARY KEY AUTO_INCREMENT,
        subject_name VARCHAR(100) NOT NULL,
        subject_code VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_subject_code (subject_code)
    )";
    $conn->query($sql);

    // Create report_cards table
    $sql = "CREATE TABLE IF NOT EXISTS report_cards (
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
    )";
    $conn->query($sql);

    // Create report_card_details table
    $sql = "CREATE TABLE IF NOT EXISTS report_card_details (
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
    )";
    $conn->query($sql);

    echo "Report card tables created successfully";
} catch(Exception $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?> 