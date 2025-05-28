<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Connect to MySQL without database
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected successfully\n";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS ace_model_college");
    $pdo->exec("USE ace_model_college");
    
    echo "Database created/selected successfully\n";
    
    // Create cbt_exams table
    $pdo->exec("
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
    
    echo "Created cbt_exams table\n";
    
    // Create cbt_student_attempts table
    $pdo->exec("
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
    
    echo "Created cbt_student_attempts table\n";
    
    // Create cbt_questions table
    $pdo->exec("
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
    
    echo "Created cbt_questions table\n";
    
    // Create cbt_question_options table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cbt_question_options (
            id INT PRIMARY KEY AUTO_INCREMENT,
            question_id INT NOT NULL,
            option_text TEXT NOT NULL,
            is_correct TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "Created cbt_question_options table\n";
    
    // Create cbt_student_answers table
    $pdo->exec("
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
    
    echo "Created cbt_student_answers table\n";
    
    // Add foreign key constraints
    $pdo->exec("
        ALTER TABLE cbt_student_attempts 
        ADD CONSTRAINT fk_attempts_exam 
        FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
    ");
    
    $pdo->exec("
        ALTER TABLE cbt_questions 
        ADD CONSTRAINT fk_questions_exam 
        FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
    ");
    
    $pdo->exec("
        ALTER TABLE cbt_question_options 
        ADD CONSTRAINT fk_options_question 
        FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
    ");
    
    $pdo->exec("
        ALTER TABLE cbt_student_answers 
        ADD CONSTRAINT fk_answers_attempt 
        FOREIGN KEY (attempt_id) REFERENCES cbt_student_attempts(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_answers_question 
        FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
    ");
    
    echo "Added foreign key constraints\n";
    
    // Add indexes for performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_student_attempts_status ON cbt_student_attempts(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_student_attempts_exam ON cbt_student_attempts(exam_id, student_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_student_answers_attempt ON cbt_student_answers(attempt_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_student_answers_correct ON cbt_student_answers(is_correct)");
    
    echo "Added indexes\n";
    
    // Verify tables were created
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTables in database:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
        
        // Show table structure
        $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        echo "  Columns:\n";
        foreach ($columns as $column) {
            echo "    {$column['Field']} ({$column['Type']})\n";
        }
        echo "\n";
    }
    
    echo "All tables created and verified successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 