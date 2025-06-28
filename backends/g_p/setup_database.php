<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);
    
    // Create payments table
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_type ENUM('school', 'tutorial') NOT NULL,
        payment_category VARCHAR(100) NOT NULL,
        depositor_name VARCHAR(255) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        student_class VARCHAR(50) NULL,
        registration_number VARCHAR(50) NULL,
        amount DECIMAL(10,2) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        account_name VARCHAR(255) NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        receipt_image VARCHAR(255) NOT NULL,
        payment_date DATE NOT NULL,
        verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    
    // Add new columns if they don't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE payments ADD COLUMN student_class VARCHAR(50) NULL AFTER student_name");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
    }
    
    try {
        $pdo->exec("ALTER TABLE payments ADD COLUMN registration_number VARCHAR(50) NULL AFTER student_class");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
    }
    
    try {
        $pdo->exec("ALTER TABLE payments ADD COLUMN payment_category VARCHAR(100) NOT NULL DEFAULT 'Other' AFTER payment_type");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
    }
    
    // Create payment_history table for tracking status changes
    $sql = "CREATE TABLE IF NOT EXISTS payment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        status ENUM('pending', 'verified', 'rejected') NOT NULL,
        notes TEXT,
        admin_user VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    
    // Create admin_users table
    $sql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        role ENUM('admin', 'super_admin') DEFAULT 'admin',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    
    // Insert default admin user (password: admin123)
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT IGNORE INTO admin_users (username, password, full_name, email, role) 
            VALUES ('admin', ?, 'System Administrator', 'admin@acemodelcollege.com', 'super_admin')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$defaultPassword]);
    
    // Create payment_categories table for predefined categories
    $sql = "CREATE TABLE IF NOT EXISTS payment_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_type ENUM('school', 'tutorial') NOT NULL,
        category_name VARCHAR(100) NOT NULL,
        default_amount DECIMAL(10,2) DEFAULT 0.00,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    
    // Insert school payment categories
    $school_categories = [
        ['school', 'Tuition Fee', 0.00],
        ['school', 'Book Library', 0.00],
        ['school', 'Uniform', 0.00],
        ['school', 'Cardigan', 0.00],
        ['school', 'Sport Wares', 0.00],
        ['school', 'Examination Fee', 0.00],
        ['school', 'Development Levy', 0.00],
        ['school', 'Other', 0.00]
    ];
    
    foreach ($school_categories as $category) {
        $sql = "INSERT IGNORE INTO payment_categories (payment_type, category_name, default_amount) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($category);
    }
    
    // Insert tutorial payment categories
    $tutorial_categories = [
        ['tutorial', 'Morning Class', 10000.00],
        ['tutorial', 'Evening Class', 3000.00],
        ['tutorial', 'Other', 0.00]
    ];
    
    foreach ($tutorial_categories as $category) {
        $sql = "INSERT IGNORE INTO payment_categories (payment_type, category_name, default_amount) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($category);
    }
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f8f9fa; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";
    echo "<h2 style='color: #28a745; text-align: center;'>✅ Database Setup Complete!</h2>";
    echo "<p style='color: #6c757d; text-align: center;'>All tables have been created successfully.</p>";
    echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>Created Tables:</h4>";
    echo "<ul>";
    echo "<li><strong>payments</strong> - Main payment records (updated with student class & registration number)</li>";
    echo "<li><strong>payment_history</strong> - Payment status tracking</li>";
    echo "<li><strong>admin_users</strong> - Admin user accounts</li>";
    echo "</ul>";
    echo "</div>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>";
    echo "<h4>New Features Added:</h4>";
    echo "<ul>";
    echo "<li><strong>Student Class</strong> - Required for school payments</li>";
    echo "<li><strong>Registration Number</strong> - Required for school payments</li>";
    echo "<li><strong>Payment Category</strong> - Required for all payments</li>";
    echo "</ul>";
    echo "</div>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>";
    echo "<h4>School Payment Categories:</h4>";
    echo "<ul>";
    echo "<li>Tuition Fee</li>";
    echo "<li>Book Library</li>";
    echo "<li>Uniform</li>";
    echo "<li>Cardigan</li>";
    echo "<li>Sport Wares</li>";
    echo "<li>Examination Fee</li>";
    echo "<li>Development Levy</li>";
    echo "<li>Other</li>";
    echo "</ul>";
    echo "</div>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>";
    echo "<h4>Tutorial Payment Categories:</h4>";
    echo "<ul>";
    echo "<li><strong>Morning Class</strong> - ₦10,000 (default)</li>";
    echo "<li><strong>Evening Class</strong> - ₦3,000 (default)</li>";
    echo "<li>Other</li>";
    echo "</ul>";
    echo "</div>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>";
    echo "<h4>Default Admin Login:</h4>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p style='font-size: 12px; color: #6c757d;'>Please change this password after first login!</p>";
    echo "</div>";
    echo "<div style='text-align: center; margin-top: 20px;'>";
    echo "<a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Payment System</a>";
    echo "</div>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f8d7da; border-radius: 10px; border: 1px solid #f5c6cb;'>";
    echo "<h2 style='color: #721c24; text-align: center;'>❌ Database Setup Failed!</h2>";
    echo "<p style='color: #721c24;'>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?> 