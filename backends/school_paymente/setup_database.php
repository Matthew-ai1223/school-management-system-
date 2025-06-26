<?php
/**
 * Payment System Database Setup Script
 * This script creates all necessary tables for the payment system
 */

require_once 'ctrl/db_config.php';

echo "<h2>Payment System Database Setup</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto;'>";

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
        echo "<div style='color: green; margin: 10px 0;'>✓ Payment types table created successfully</div>";
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
        FOREIGN KEY (payment_type_id) REFERENCES school_payment_types(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "<div style='color: green; margin: 10px 0;'>✓ Payments table created successfully</div>";
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
        echo "<div style='color: green; margin: 10px 0;'>✓ Payment receipts table created successfully</div>";
    } else {
        throw new Exception("Error creating payment receipts table: " . $conn->error);
    }

    // Create students table if it doesn't exist (for foreign key reference)
    $sql = "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        registration_number VARCHAR(50) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        class VARCHAR(50),
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "<div style='color: green; margin: 10px 0;'>✓ Students table created successfully</div>";
    } else {
        throw new Exception("Error creating students table: " . $conn->error);
    }

    // Insert sample payment types if they don't exist
    $sample_payment_types = [
        ['School Fees', 50000.00, 10000.00, 'Complete school fees for the academic term', '2024/2025'],
        ['Library Fee', 5000.00, 5000.00, 'Library access and book borrowing fee', '2024/2025'],
        ['Laboratory Fee', 3000.00, 3000.00, 'Science laboratory usage fee', '2024/2025'],
        ['Sports Fee', 2000.00, 2000.00, 'Sports facilities and equipment fee', '2024/2025'],
        ['Examination Fee', 1500.00, 1500.00, 'End of term examination fee', '2024/2025']
    ];

    $inserted_count = 0;
    foreach ($sample_payment_types as $type) {
        $check_sql = "SELECT id FROM school_payment_types WHERE name = ? AND academic_term = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $type[0], $type[4]);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            $insert_sql = "INSERT INTO school_payment_types (name, amount, min_payment_amount, description, academic_term) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sddss", $type[0], $type[1], $type[2], $type[3], $type[4]);
            if ($insert_stmt->execute()) {
                $inserted_count++;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }

    if ($inserted_count > 0) {
        echo "<div style='color: blue; margin: 10px 0;'>✓ {$inserted_count} sample payment types inserted</div>";
    } else {
        echo "<div style='color: blue; margin: 10px 0;'>✓ Sample payment types already exist</div>";
    }

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "<div style='color: green; font-weight: bold; margin-top: 20px; padding: 15px; background-color: #d4edda; border-radius: 5px;'>";
    echo "🎉 Database setup completed successfully!";
    echo "</div>";

    echo "<div style='margin-top: 20px;'>";
    echo "<h3>Created Tables:</h3>";
    echo "<ul>";
    echo "<li><strong>school_payment_types</strong> - Stores different types of payments</li>";
    echo "<li><strong>school_payments</strong> - Stores all payment transactions</li>";
    echo "<li><strong>school_payment_receipts</strong> - Stores payment receipts</li>";
    echo "<li><strong>students</strong> - Stores student information (if not exists)</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div style='margin-top: 20px;'>";
    echo "<a href='student_payment_history.php' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Payment History</a>";
    echo "</div>";

} catch (Exception $e) {
    // Re-enable foreign key checks even if there's an error
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div style='color: red; font-weight: bold; margin-top: 20px; padding: 15px; background-color: #f8d7da; border-radius: 5px;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}

echo "</div>";
?> 