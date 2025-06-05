<?php
require_once 'db_config.php';

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

try {
    // Create payment types table
    $sql = "CREATE TABLE IF NOT EXISTS school_payment_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        min_payment_amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        academic_term VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "Payment types table created successfully<br>";
    } else {
        throw new Exception("Error creating payment types table: " . $conn->error);
    }

    // Create payments table with base_amount and service_charge
    $sql = "CREATE TABLE IF NOT EXISTS school_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        payment_type_id INT,
        amount DECIMAL(10,2) NOT NULL,
        base_amount DECIMAL(10,2) NOT NULL,
        service_charge DECIMAL(10,2) NOT NULL,
        reference_code VARCHAR(100) UNIQUE,
        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(registration_number),
        FOREIGN KEY (payment_type_id) REFERENCES school_payment_types(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "Payments table created successfully<br>";
    } else {
        throw new Exception("Error creating payments table: " . $conn->error);
    }

    // Create payment receipts table
    $sql = "CREATE TABLE IF NOT EXISTS school_payment_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT,
        receipt_number VARCHAR(50) UNIQUE NOT NULL,
        generated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payment_id) REFERENCES school_payments(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "Payment receipts table created successfully<br>";
    } else {
        throw new Exception("Error creating payment receipts table: " . $conn->error);
    }

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "<div style='color: green; margin-top: 20px;'>Database setup completed successfully!</div>";

} catch (Exception $e) {
    // Re-enable foreign key checks even if there's an error
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    die("<div style='color: red; margin-top: 20px;'>Error: " . $e->getMessage() . "</div>");
}
?> 