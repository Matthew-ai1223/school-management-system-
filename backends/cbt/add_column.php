<?php
require_once '../config.php';
require_once '../database.php';

// Initialize log file
$logFile = fopen("column_add_log.txt", "w") or die("Unable to open log file!");

try {
    // Initialize database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    fwrite($logFile, "Database connection established\n");

    // SQL to add question_image column if it doesn't exist
    $sql = "SHOW COLUMNS FROM `cbt_questions` LIKE 'question_image'";
    $result = $conn->query($sql);
    
    fwrite($logFile, "Column check executed\n");

    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $alterSql = "ALTER TABLE `cbt_questions` ADD COLUMN `question_image` VARCHAR(255) DEFAULT NULL";
        if ($conn->query($alterSql) === TRUE) {
            $message = "Column 'question_image' added successfully\n";
            fwrite($logFile, $message);
            echo $message;
        } else {
            $error = "Error adding column: " . $conn->error . "\n";
            fwrite($logFile, $error);
            echo $error;
        }
    } else {
        $message = "Column 'question_image' already exists\n";
        fwrite($logFile, $message);
        echo $message;
    }

    // Create upload directory if it doesn't exist
    $uploadDir = '../uploads/question_images/';
    if (!file_exists($uploadDir)) {
        if (mkdir($uploadDir, 0777, true)) {
            $message = "Upload directory created successfully\n";
            fwrite($logFile, $message);
            echo $message;
        } else {
            $error = "Failed to create upload directory\n";
            fwrite($logFile, $error);
            echo $error;
        }
    } else {
        $message = "Upload directory already exists\n";
        fwrite($logFile, $message);
        echo $message;
    }

} catch (Exception $e) {
    $error = "Exception: " . $e->getMessage() . "\n";
    fwrite($logFile, $error);
    echo $error;
}

fclose($logFile);
?> 