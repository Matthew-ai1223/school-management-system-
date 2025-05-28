<?php
// Define database constants if they're not already defined
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
if (!defined('DB_NAME')) define('DB_NAME', 'ace_school_system');

// Direct connection without using Database class
try {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        echo "Connection failed: " . $conn->connect_error;
    } else {
        echo "Connection successful!<br>";
        
        // Create cbt_exams table
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
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create cbt_questions table
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create cbt_question_options table
        $conn->query("
            CREATE TABLE IF NOT EXISTS cbt_question_options (
                id INT PRIMARY KEY AUTO_INCREMENT,
                question_id INT NOT NULL,
                option_text TEXT NOT NULL,
                is_correct TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
                UNIQUE KEY unique_attempt_question (attempt_id, question_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Add foreign key constraints
        $conn->query("
            ALTER TABLE cbt_student_attempts 
            ADD CONSTRAINT fk_attempts_exam 
            FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
        ");

        $conn->query("
            ALTER TABLE cbt_questions 
            ADD CONSTRAINT fk_questions_exam 
            FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
        ");

        $conn->query("
            ALTER TABLE cbt_question_options 
            ADD CONSTRAINT fk_options_question 
            FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
        ");

        $conn->query("
            ALTER TABLE cbt_student_answers 
            ADD CONSTRAINT fk_answers_attempt 
            FOREIGN KEY (attempt_id) REFERENCES cbt_student_attempts(id) ON DELETE CASCADE,
            ADD CONSTRAINT fk_answers_question 
            FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
        ");

        // Add indexes for performance
        $conn->query("CREATE INDEX IF NOT EXISTS idx_student_attempts_status ON cbt_student_attempts(status)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_student_attempts_exam ON cbt_student_attempts(exam_id, student_id)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_student_answers_attempt ON cbt_student_answers(attempt_id)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_student_answers_correct ON cbt_student_answers(is_correct)");

        echo "All tables created successfully!<br>";
        
        // Try a simple query
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            echo "Tables in database:<br>";
            while ($row = $result->fetch_row()) {
                echo "- " . $row[0] . "<br>";
            }
        } else {
            echo "Query failed: " . $conn->error;
        }
        
        // Add total_questions column if it doesn't exist
        $result = $conn->query("SHOW COLUMNS FROM cbt_exams LIKE 'total_questions'");
        if ($result->num_rows === 0) {
            $conn->query("ALTER TABLE cbt_exams ADD COLUMN total_questions INT DEFAULT 0 AFTER instructions");
            echo "Added total_questions column to cbt_exams table<br>";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 