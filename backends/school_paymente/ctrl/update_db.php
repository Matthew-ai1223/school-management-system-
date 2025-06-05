<?php
require_once 'db_config.php';

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

try {
    // Drop existing tables in correct order (child table first)
    if ($conn->query("DROP TABLE IF EXISTS school_payments")) {
        echo "Dropped payments table successfully<br>";
    } else {
        throw new Exception("Error dropping payments table: " . $conn->error);
    }

    if ($conn->query("DROP TABLE IF EXISTS school_payment_types")) {
        echo "Dropped payment types table successfully<br>";
    } else {
        throw new Exception("Error dropping payment types table: " . $conn->error);
    }

    // Create tables with correct structure
    $sql = "CREATE TABLE school_payment_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        min_payment_amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        academic_term VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if ($conn->query($sql)) {
        echo "Payment types table created successfully<br>";
    } else {
        throw new Exception("Error creating payment types table: " . $conn->error);
    }

    $sql = "CREATE TABLE school_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        payment_type_id INT,
        amount DECIMAL(10,2) NOT NULL,
        base_amount DECIMAL(10,2) NOT NULL,
        service_charge DECIMAL(10,2) NOT NULL,
        reference_code VARCHAR(100) UNIQUE,
        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payment_type_id) REFERENCES school_payment_types(id)
    )";

    if ($conn->query($sql)) {
        echo "Payments table created successfully<br>";
    } else {
        throw new Exception("Error creating payments table: " . $conn->error);
    }

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "<div style='color: green; margin-top: 20px;'>Database update completed successfully! You can now return to the <a href='manage_payment_types.php'>payment management page</a>.</div>";

} catch (Exception $e) {
    // Re-enable foreign key checks even if there's an error
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    die("<div style='color: red; margin-top: 20px;'>Error: " . $e->getMessage() . "</div>");
}
?> 