<?php
// Database connection parameters
include 'confg.php';

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($db_name);

// Drop existing tables if they exist
$sql = "DROP TABLE IF EXISTS morning_students";
if ($conn->query($sql) === TRUE) {
    echo "Morning students table dropped successfully<br>";
} else {
    echo "Error dropping morning students table: " . $conn->error . "<br>";
}

$sql = "DROP TABLE IF EXISTS afternoon_students";
if ($conn->query($sql) === TRUE) {
    echo "Afternoon students table dropped successfully<br>";
} else {
    echo "Error dropping afternoon students table: " . $conn->error . "<br>";
}

// Create morning students table
$sql = "CREATE TABLE IF NOT EXISTS morning_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    photo VARCHAR(255) NOT NULL,
    department ENUM('sciences', 'commercial', 'art') NOT NULL,
    parent_name VARCHAR(255) NOT NULL,
    parent_phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    payment_reference VARCHAR(255) NOT NULL UNIQUE,
    payment_type ENUM('full', 'half') NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiration_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Morning students table created successfully or already exists<br>";
} else {
    echo "Error creating morning students table: " . $conn->error . "<br>";
}

// Create afternoon students table
$sql = "CREATE TABLE IF NOT EXISTS afternoon_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    photo VARCHAR(255) NOT NULL,
    department ENUM('sciences', 'commercial', 'art') NOT NULL,
    class VARCHAR(50) NOT NULL,
    school VARCHAR(255) NOT NULL,
    parent_name VARCHAR(255) NOT NULL,
    parent_phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    payment_reference VARCHAR(255) NOT NULL UNIQUE,
    payment_type ENUM('full', 'half') NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiration_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Afternoon students table created successfully or already exists<br>";
} else {
    echo "Error creating afternoon students table: " . $conn->error . "<br>";
}

// Create password reset tokens table
$sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    student_type ENUM('morning', 'afternoon') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Password reset tokens table created successfully or already exists<br>";
} else {
    echo "Error creating password reset tokens table: " . $conn->error . "<br>";
}

// Create reference numbers table
$sql = "CREATE TABLE IF NOT EXISTS reference_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(255) NOT NULL UNIQUE,
    session_type ENUM('morning', 'afternoon') NOT NULL,
    payment_type ENUM('full', 'half') NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    created_by VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Reference numbers table created successfully or already exists<br>";
} else {
    echo "Error creating reference numbers table: " . $conn->error . "<br>";
}

// Create cash payments table
$sql = "CREATE TABLE IF NOT EXISTS cash_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(255) NOT NULL UNIQUE,
    fullname VARCHAR(255) NOT NULL,
    session_type ENUM('morning', 'afternoon') NOT NULL,
    department ENUM('sciences', 'commercial', 'art') NOT NULL,
    payment_type ENUM('full', 'half') NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    class VARCHAR(50) NULL,
    school VARCHAR(255) NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiration_date DATE NOT NULL,
    is_processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    processed_by VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Cash payments table created successfully or already exists<br>";
} else {
    echo "Error creating cash payments table: " . $conn->error . "<br>";
}

// Create uploads directory if it doesn't exist
$uploads_dir = 'uploads';
if (!file_exists($uploads_dir)) {
    if (mkdir($uploads_dir, 0777, true)) {
        echo "Uploads directory created successfully<br>";
    } else {
        echo "Error creating uploads directory<br>";
    }
}

// Close connection
$conn->close();

echo "Database setup completed!";
?> 