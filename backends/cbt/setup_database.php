<?php
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';  // Added utils.php which contains columnExists()

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Function to setup CBT-specific database tables
function setupCBTTables($conn) {
    try {
        // Start transaction
        $conn->begin_transaction();

        // Drop existing tables if they have incorrect foreign keys
        $conn->query("DROP TABLE IF EXISTS cbt_student_answers");
        $conn->query("DROP TABLE IF EXISTS cbt_student_attempts");
        $conn->query("DROP TABLE IF EXISTS cbt_exam_attempts");
        
        // Create cbt_exams table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS cbt_exams (
                id INT PRIMARY KEY AUTO_INCREMENT,
                teacher_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                subject VARCHAR(100) NOT NULL,
                class VARCHAR(50) NOT NULL,
                time_limit INT NOT NULL DEFAULT 60,
                description TEXT,
                instructions TEXT,
                total_questions INT DEFAULT 0,
                passing_score INT DEFAULT 40,
                random_questions TINYINT(1) DEFAULT 1,
                show_result TINYINT(1) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 0,
                allow_retake TINYINT(1) DEFAULT 0,
                max_attempts INT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create cbt_student_attempts table
        $conn->query("
            CREATE TABLE IF NOT EXISTS cbt_student_attempts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exam_id INT NOT NULL,
                student_id INT NOT NULL,
                start_time DATETIME NOT NULL,
                end_time DATETIME,
                status ENUM('In Progress', 'completed', 'passed', 'failed') DEFAULT 'In Progress',
                total_marks DECIMAL(10,2) DEFAULT 0,
                marks_obtained DECIMAL(10,2) DEFAULT 0,
                score DECIMAL(5,2) DEFAULT 0,
                show_result TINYINT(1) DEFAULT 0,
                attempt_number INT DEFAULT 1,
                time_spent INT DEFAULT 0,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create cbt_questions table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS cbt_questions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exam_id INT NOT NULL,
                question_text TEXT NOT NULL,
                question_type ENUM('Multiple Choice', 'True/False', 'Short Answer') NOT NULL,
                marks INT DEFAULT 1,
                correct_answer TEXT,
                explanation TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create cbt_question_options table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS cbt_question_options (
                id INT PRIMARY KEY AUTO_INCREMENT,
                question_id INT NOT NULL,
                option_text TEXT NOT NULL,
                is_correct TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create cbt_student_answers table
        $conn->query("
            CREATE TABLE IF NOT EXISTS cbt_student_answers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                attempt_id INT NOT NULL,
                question_id INT NOT NULL,
                selected_answer TEXT,
                is_correct TINYINT(1) DEFAULT 0,
                marks_awarded DECIMAL(10,2) DEFAULT 0,
                answer_time DATETIME DEFAULT NULL,
                review_flag TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_attempt_question (attempt_id, question_id),
                FOREIGN KEY (attempt_id) REFERENCES cbt_student_attempts(id) ON DELETE CASCADE,
                FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Add indexes for performance
        $conn->query("CREATE INDEX IF NOT EXISTS idx_student_attempts_status ON cbt_student_attempts(status)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_student_attempts_exam ON cbt_student_attempts(exam_id, student_id)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_student_answers_attempt ON cbt_student_answers(attempt_id)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_student_answers_correct ON cbt_student_answers(is_correct)");

        // Commit transaction
        $conn->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback on error
        if ($conn) {
            $conn->rollback();
        }
        error_log("Database setup error: " . $e->getMessage());
        return false;
    }
}

// Run database setup
if (!setupCBTTables($conn)) {
    die("Failed to setup CBT tables structure. Please contact administrator.");
}

echo "CBT tables setup completed successfully!"; 