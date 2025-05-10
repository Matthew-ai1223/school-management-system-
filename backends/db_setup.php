<?php
require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance();

// Create database if not exists
$db->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$db->query("USE " . DB_NAME);

// Students table
$db->query("CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_type ENUM('kiddies', 'college') NOT NULL,
    registration_number VARCHAR(20) UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    address TEXT NOT NULL,
    parent_name VARCHAR(100) NOT NULL,
    parent_phone VARCHAR(20) NOT NULL,
    parent_email VARCHAR(100),
    application_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'registered', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Payments table
$db->query("CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    payment_type ENUM('application', 'school_fees') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('paystack', 'cash') NOT NULL,
    reference_number VARCHAR(100) UNIQUE,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
)");

// Exam results table
$db->query("CREATE TABLE IF NOT EXISTS exam_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    exam_type ENUM('entrance', 'cbt') NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    total_score DECIMAL(5,2) NOT NULL,
    exam_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'passed', 'failed') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(id)
)");

// Users table (for admin, teachers, class teachers)
$db->query("CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'class_teacher') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Notifications table
$db->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    sender_id INT,
    recipient_type ENUM('all', 'student', 'teacher', 'class_teacher') NOT NULL,
    recipient_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id)
)");

// Form fields table for dynamic application form
$db->query("CREATE TABLE IF NOT EXISTS form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_label VARCHAR(255) NOT NULL,
    field_type VARCHAR(50) NOT NULL,
    field_order INT DEFAULT 0,
    required BOOLEAN DEFAULT FALSE,
    options TEXT,
    application_type ENUM('kiddies', 'college') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Applications table for storing submitted applications
$db->query("CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_data JSON NOT NULL,
    application_type ENUM('kiddies', 'college') NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'pending',
    reviewed_by INT,
    review_date TIMESTAMP NULL,
    comments TEXT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
)");

echo "Database setup completed successfully!";
