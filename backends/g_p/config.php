<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ace_model_college');

// Payment system configuration
define('SCHOOL_ACCOUNT_NUMBER', '0509948202');
define('SCHOOL_ACCOUNT_NAME', 'OSENIS ACE SCHOOLS LIMITED');
define('SCHOOL_BANK', 'Sterling Bank');

define('TUTORIAL_ACCOUNT_NUMBER', '0509948202');
define('TUTORIAL_ACCOUNT_NAME', 'OSENIS ACE SCHOOLS LIMITED');
define('TUTORIAL_BANK', 'Sterling Bank');

// Upload directory
define('UPLOAD_DIR', 'uploads/receipts/');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate unique filename
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

// Create upload directory if it doesn't exist
function createUploadDirectory() {
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
}

// Validate image file
function validateImageFile($file) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return "Invalid file type. Only JPG, PNG, and GIF files are allowed.";
    }
    
    if ($file['size'] > $maxSize) {
        return "File size too large. Maximum size is 5MB.";
    }
    
    return true;
}
?> 