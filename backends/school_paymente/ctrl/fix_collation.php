<?php
require_once 'db_config.php';

try {
    // Set the connection charset
    $conn->set_charset("utf8mb4");
    
    // First, alter the database itself
    $sql = "ALTER DATABASE `ace_school_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "Database collation updated successfully<br>";
    } else {
        throw new Exception("Error updating database collation: " . $conn->error);
    }

    // Ensure proper character set is being used
    $conn->query("SET NAMES utf8mb4");
    $conn->query("SET collation_connection = utf8mb4_unicode_ci");

    // Update students table and its columns
    $sql = "ALTER TABLE students 
            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "Students table collation updated successfully<br>";
    } else {
        throw new Exception("Error updating students table collation: " . $conn->error);
    }

    // Update registration_number column specifically
    $sql = "ALTER TABLE students 
            MODIFY registration_number VARCHAR(50) 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "Registration number column updated successfully<br>";
    } else {
        throw new Exception("Error updating registration number column: " . $conn->error);
    }

    // Drop and recreate payment tables with correct collation
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // Recreate payment_types table
    $sql = "DROP TABLE IF EXISTS school_payment_types";
    $conn->query($sql);

    $sql = "CREATE TABLE school_payment_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        min_payment_amount DECIMAL(10,2) NOT NULL,
        description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        academic_term VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "Payment types table recreated successfully<br>";
    } else {
        throw new Exception("Error recreating payment types table: " . $conn->error);
    }

    // Recreate payments table
    $sql = "DROP TABLE IF EXISTS school_payments";
    $conn->query($sql);

    $sql = "CREATE TABLE school_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        payment_type_id INT,
        amount DECIMAL(10,2) NOT NULL,
        base_amount DECIMAL(10,2) NOT NULL,
        service_charge DECIMAL(10,2) NOT NULL,
        reference_code VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE,
        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(registration_number),
        FOREIGN KEY (payment_type_id) REFERENCES school_payment_types(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "Payments table recreated successfully<br>";
    } else {
        throw new Exception("Error recreating payments table: " . $conn->error);
    }

    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "<div style='color: green; margin-top: 20px;'>All tables have been updated successfully!</div>";
    echo "<div style='margin-top: 10px;'><a href='../payment_interface.php' class='btn btn-primary'>Return to Payment Interface</a></div>";

} catch (Exception $e) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    die("<div style='color: red; margin-top: 20px;'>Error: " . $e->getMessage() . "</div>");
}
?> 