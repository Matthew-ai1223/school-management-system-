<?php
// Database Setup Script
require_once 'database.php';

// Create a database connection
$database = new Database();
$conn = $database->getConnection();

try {
    // Drop database if exists to clean setup
    $conn->exec("DROP DATABASE IF EXISTS school_management");
    $conn->exec("CREATE DATABASE school_management");
    $conn->exec("USE school_management");
    
    // Create users table (for authentication)
    $conn->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        role ENUM('admin', 'teacher', 'student', 'parent') NOT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Create admins table
    $conn->exec("CREATE TABLE admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create teachers table
    $conn->exec("CREATE TABLE teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        employee_id VARCHAR(20) UNIQUE NOT NULL,
        joining_date DATE,
        qualification VARCHAR(100),
        experience FLOAT,
        phone VARCHAR(20),
        address TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create classes table
    $conn->exec("CREATE TABLE classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        section VARCHAR(10),
        room_number VARCHAR(20),
        capacity INT,
        class_teacher_id INT,
        academic_year VARCHAR(20),
        FOREIGN KEY (class_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
    )");
    
    // Create students table
    $conn->exec("CREATE TABLE students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        admission_number VARCHAR(20) UNIQUE NOT NULL,
        admission_date DATE DEFAULT CURRENT_DATE,
        date_of_birth DATE NOT NULL,
        gender ENUM('Male', 'Female', 'Other') NOT NULL,
        blood_group VARCHAR(5),
        class_id INT,
        roll_number INT,
        phone VARCHAR(20),
        address TEXT,
        parent_name VARCHAR(100) NOT NULL,
        parent_phone VARCHAR(20) NOT NULL,
        parent_email VARCHAR(100),
        parent_address TEXT,
        previous_school VARCHAR(100),
        registration_number VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
    )");

    // Create parents table
    $conn->exec("CREATE TABLE parents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        phone VARCHAR(20),
        occupation VARCHAR(50),
        address TEXT,
        relation_with_student ENUM('Father', 'Mother', 'Guardian'),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create parent_student relationship table
    $conn->exec("CREATE TABLE parent_student (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT,
        student_id INT,
        FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )");
    
    // Create subjects table
    $conn->exec("CREATE TABLE subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20) UNIQUE NOT NULL,
        description TEXT,
        credit_hours FLOAT
    )");
    
    // Create class_subject table (many-to-many relationship)
    $conn->exec("CREATE TABLE class_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT,
        subject_id INT,
        teacher_id INT,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
    )");
    
    // Create attendance table
    $conn->exec("CREATE TABLE attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        class_id INT,
        subject_id INT,
        attendance_date DATE,
        status ENUM('Present', 'Absent', 'Late', 'Excused'),
        remarks TEXT,
        marked_by INT,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Create exams table
    $conn->exec("CREATE TABLE exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        start_date DATE,
        end_date DATE,
        academic_year VARCHAR(20),
        term ENUM('First', 'Second', 'Third'),
        created_by INT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Create grades table
    $conn->exec("CREATE TABLE grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        exam_id INT,
        subject_id INT,
        class_id INT,
        marks_obtained FLOAT,
        max_marks FLOAT,
        grade VARCHAR(5),
        remarks TEXT,
        entered_by INT,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Create timetable table
    $conn->exec("CREATE TABLE timetable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT,
        subject_id INT,
        teacher_id INT,
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        start_time TIME,
        end_time TIME,
        room_number VARCHAR(20),
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
    )");
    
    // Create fee_categories table
    $conn->exec("CREATE TABLE fee_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        description TEXT
    )");
    
    // Create fees table
    $conn->exec("CREATE TABLE fees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT,
        class_id INT,
        amount DECIMAL(10,2) NOT NULL,
        due_date DATE,
        academic_year VARCHAR(20),
        term ENUM('First', 'Second', 'Third'),
        description TEXT,
        FOREIGN KEY (category_id) REFERENCES fee_categories(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    )");
    
    // Create fee_payments table
    $conn->exec("CREATE TABLE fee_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        fee_id INT,
        amount_paid DECIMAL(10,2) NOT NULL,
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        payment_method ENUM('Cash', 'Bank Transfer', 'Card', 'Online'),
        transaction_id VARCHAR(50),
        receipt_number VARCHAR(50),
        status ENUM('Pending', 'Completed', 'Failed'),
        remarks TEXT,
        received_by INT,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
        FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Create notifications table
    $conn->exec("CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        user_id INT,
        role ENUM('admin', 'teacher', 'student', 'parent', 'all'),
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create announcements table
    $conn->exec("CREATE TABLE announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        content TEXT NOT NULL,
        published_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expiry_date DATE,
        visibility ENUM('Admin', 'Teacher', 'Student', 'Parent', 'All') DEFAULT 'All',
        created_by INT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Create messages table
    $conn->exec("CREATE TABLE messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT,
        receiver_id INT,
        subject VARCHAR(100),
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create cbt_exams table for CBT system
    $conn->exec("CREATE TABLE cbt_exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        subject_id INT,
        class_id INT,
        teacher_id INT,
        total_questions INT,
        passing_score FLOAT,
        time_limit INT, -- in minutes
        start_datetime DATETIME,
        end_datetime DATETIME,
        instructions TEXT,
        is_active BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
    )");
    
    // Create cbt_questions table
    $conn->exec("CREATE TABLE cbt_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT,
        question_text TEXT NOT NULL,
        question_type ENUM('Multiple Choice', 'True/False', 'Short Answer', 'Essay'),
        marks FLOAT DEFAULT 1,
        FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
    )");
    
    // Create cbt_options table for multiple choice questions
    $conn->exec("CREATE TABLE cbt_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT,
        option_text TEXT NOT NULL,
        is_correct BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
    )");
    
    // Create cbt_student_exams table to track student exam attempts
    $conn->exec("CREATE TABLE cbt_student_exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        exam_id INT,
        started_at DATETIME,
        submitted_at DATETIME,
        score FLOAT,
        status ENUM('Pending', 'In Progress', 'Completed', 'Evaluated'),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
    )");
    
    // Create cbt_student_answers table
    $conn->exec("CREATE TABLE cbt_student_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_exam_id INT,
        question_id INT,
        selected_option_id INT,
        text_answer TEXT,
        marks_awarded FLOAT,
        evaluated_by INT,
        FOREIGN KEY (student_exam_id) REFERENCES cbt_student_exams(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE,
        FOREIGN KEY (selected_option_id) REFERENCES cbt_options(id) ON DELETE SET NULL,
        FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Create files table for document uploads
    $conn->exec("CREATE TABLE files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        filepath VARCHAR(255) NOT NULL,
        filetype VARCHAR(50),
        filesize INT,
        description TEXT,
        uploaded_by INT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        related_to ENUM('Student', 'Teacher', 'Admin', 'Class', 'Subject', 'Assignment', 'CBT'),
        related_id INT,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Create academic_sessions table
    $conn->exec("CREATE TABLE academic_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        start_date DATE,
        end_date DATE,
        is_current BOOLEAN DEFAULT FALSE
    )");

    echo "Database setup completed successfully!";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 