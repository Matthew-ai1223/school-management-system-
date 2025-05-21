<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if required tables exist
$requiredTables = ['users', 'teachers', 'students'];
$missingTables = [];

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "Error: The following required tables are missing: " . implode(', ', $missingTables) . "<br>";
    echo "Please make sure these tables exist before running this script.<br>";
    exit;
}

// Create class_teachers table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS `class_teachers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT,
    `teacher_id` INT,
    `class_name` VARCHAR(100),
    `academic_year` VARCHAR(20),
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    INDEX (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Create class_teacher_activities table for tracking activities
$sql2 = "CREATE TABLE IF NOT EXISTS `class_teacher_activities` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `class_teacher_id` INT,
    `student_id` INT,
    `activity_type` ENUM('attendance', 'behavioral', 'academic', 'health', 'other') NOT NULL,
    `description` TEXT NOT NULL,
    `activity_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`class_teacher_id`),
    INDEX (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Create class_teacher_comments table for student comments
$sql3 = "CREATE TABLE IF NOT EXISTS `class_teacher_comments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `class_teacher_id` INT,
    `student_id` INT,
    `comment_type` ENUM('term_report', 'behavioral', 'academic', 'general') NOT NULL,
    `comment` TEXT NOT NULL,
    `term` VARCHAR(20),
    `session` VARCHAR(20),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`class_teacher_id`),
    INDEX (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    // Execute the create table queries
    if ($conn->query($sql)) {
        echo "Successfully created class_teachers table.<br>";
    } else {
        echo "Error creating class_teachers table: " . $conn->error . "<br>";
    }
    
    if ($conn->query($sql2)) {
        echo "Successfully created class_teacher_activities table.<br>";
    } else {
        echo "Error creating class_teacher_activities table: " . $conn->error . "<br>";
    }
    
    if ($conn->query($sql3)) {
        echo "Successfully created class_teacher_comments table.<br>";
    } else {
        echo "Error creating class_teacher_comments table: " . $conn->error . "<br>";
    }
    
    // Try to add foreign keys individually with error handling
    $foreignKeys = [
        "ALTER TABLE `class_teachers` ADD CONSTRAINT `fk_class_teacher_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE",
        "ALTER TABLE `class_teachers` ADD CONSTRAINT `fk_class_teacher_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE",
        "ALTER TABLE `class_teacher_activities` ADD CONSTRAINT `fk_activity_class_teacher` FOREIGN KEY (`class_teacher_id`) REFERENCES `class_teachers`(`id`) ON DELETE CASCADE",
        "ALTER TABLE `class_teacher_activities` ADD CONSTRAINT `fk_activity_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE",
        "ALTER TABLE `class_teacher_comments` ADD CONSTRAINT `fk_comment_class_teacher` FOREIGN KEY (`class_teacher_id`) REFERENCES `class_teachers`(`id`) ON DELETE CASCADE",
        "ALTER TABLE `class_teacher_comments` ADD CONSTRAINT `fk_comment_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE"
    ];
    
    foreach ($foreignKeys as $fkSql) {
        if ($conn->query($fkSql)) {
            echo "Successfully added foreign key: " . $fkSql . "<br>";
        } else {
            echo "Warning: Failed to add foreign key: " . $fkSql . "<br>";
            echo "Error: " . $conn->error . "<br>";
            // Continue with other foreign keys even if one fails
        }
    }
    
    // Update permissions for teachers who are class teachers
    $updatePermissions = "UPDATE `users` 
                         SET `role` = 'class_teacher' 
                         WHERE `id` IN (
                            SELECT `user_id` FROM `class_teachers` WHERE `is_active` = 1
                         )";
    
    if ($conn->query($updatePermissions)) {
        echo "Successfully updated user permissions for class teachers.<br>";
    } else {
        echo "Error updating user permissions: " . $conn->error . "<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "Class teacher database setup completed.";
?> 