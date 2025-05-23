<?php
require_once 'config.php';
require_once 'database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// SQL to create application_payments table
$sql = "CREATE TABLE IF NOT EXISTS `application_payments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `reference` VARCHAR(100) UNIQUE NOT NULL,
    `application_type` ENUM('kiddies', 'college') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `payment_date` DATETIME DEFAULT NULL,
    `transaction_reference` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    if ($conn->query($sql)) {
        echo "Successfully created application_payments table.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 