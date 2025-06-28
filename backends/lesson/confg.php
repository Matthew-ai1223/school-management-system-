<?php
// Database connection parameters
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'lesson_db';

try {
    // Create connection without selecting database
    $conn = new mysqli($db_host, $db_user, $db_pass);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS $db_name";
    if ($conn->query($sql) === TRUE) {
        // Select the database after creating it
        $conn->select_db($db_name);
    } else {
        throw new Exception("Error creating database: " . $conn->error);
    }
} catch (Exception $e) {
    // Log the error but don't output HTML
    error_log("Database Error: " . $e->getMessage());
    throw $e; // Re-throw to be handled by the calling script
}
?>