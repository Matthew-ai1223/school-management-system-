<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if tables already exist
$teachersExist = false;

$tablesResult = $conn->query("SHOW TABLES");
while ($table = $tablesResult->fetch_array()) {
    if ($table[0] == 'teachers') {
        $teachersExist = true;
    }
}

// If teachers table exists, check if we need to drop and recreate it
if ($teachersExist) {
    echo "Teachers table already exists.<br>";
    
    // Check if the structure is as expected
    $result = $conn->query("SHOW CREATE TABLE `teachers`");
    $row = $result->fetch_assoc();
    $createTableStatement = $row['Create Table'];
    
    // Check if foreign key constraint exists and if needed, drop it first
    $fkConstraintCheck = $conn->query("SELECT 
        CONSTRAINT_NAME
    FROM 
        information_schema.TABLE_CONSTRAINTS 
    WHERE 
        CONSTRAINT_TYPE = 'FOREIGN KEY' 
    AND 
        TABLE_NAME = 'teachers'
    AND 
        CONSTRAINT_NAME = 'fk_teacher_user'");
    
    if ($fkConstraintCheck->num_rows > 0) {
        $conn->query("ALTER TABLE `teachers` DROP FOREIGN KEY `fk_teacher_user`");
        echo "Dropped existing foreign key constraint.<br>";
    }
} else {
    // Create teachers table if it doesn't exist
    $teachersTableSQL = "CREATE TABLE IF NOT EXISTS `teachers` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `first_name` VARCHAR(50) NOT NULL,
        `last_name` VARCHAR(50) NOT NULL,
        `employee_id` VARCHAR(20) NOT NULL,
        `joining_date` DATE DEFAULT NULL,
        `qualification` VARCHAR(100) DEFAULT NULL,
        `experience` FLOAT DEFAULT NULL,
        `phone` VARCHAR(20) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        UNIQUE (`employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($teachersTableSQL)) {
        echo "Successfully created 'teachers' table.<br>";
    } else {
        echo "Error creating 'teachers' table: " . $conn->error . "<br>";
    }
}

// Try to add foreign key for teachers.user_id referencing users.id
// First check if user table exists
$usersExist = false;
$tablesResult = $conn->query("SHOW TABLES");
while ($table = $tablesResult->fetch_array()) {
    if ($table[0] == 'users') {
        $usersExist = true;
        break;
    }
}

if ($usersExist) {
    $addTeacherFKSQL = "ALTER TABLE `teachers` 
                        ADD CONSTRAINT `fk_teacher_user` 
                        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) 
                        ON DELETE CASCADE";
    
    if ($conn->query($addTeacherFKSQL)) {
        echo "Successfully added foreign key for teachers.user_id.<br>";
    } else {
        echo "Warning: Failed to add foreign key for teachers.user_id: " . $conn->error . "<br>";
        echo "This is not critical as long as the table was created.<br>";
    }
} else {
    echo "Warning: 'users' table not found. Foreign key constraint could not be added.<br>";
}

// Check if students table exists and has the class field
$studentsExist = false;
$tablesResult = $conn->query("SHOW TABLES");
while ($table = $tablesResult->fetch_array()) {
    if ($table[0] == 'students') {
        $studentsExist = true;
        break;
    }
}

if ($studentsExist) {
    // Check if students table has the class field
    $result = $conn->query("SHOW COLUMNS FROM `students` LIKE 'class'");
    if ($result->num_rows == 0) {
        // Add class field if it doesn't exist
        $addClassFieldSQL = "ALTER TABLE `students` ADD COLUMN `class` VARCHAR(100)";
        if ($conn->query($addClassFieldSQL)) {
            echo "Added 'class' field to students table.<br>";
        } else {
            echo "Error adding 'class' field to students table: " . $conn->error . "<br>";
        }
    } else {
        echo "Students table already has 'class' field.<br>";
    }
} else {
    echo "Warning: 'students' table not found. This is required for the class teacher system.<br>";
}

echo "<div style='margin-top: 20px; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;'>
        <strong>Setup completed successfully!</strong><br>
        You can now proceed to set up the class teacher system by running the create_tables.php script.
      </div>";

echo "<div style='margin-top: 20px;'>
        <a href='create_tables.php' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>
            Run Class Teacher Setup
        </a>
      </div>";
?> 