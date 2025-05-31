<?php

// Error Reporting - Set this first
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
ini_set('error_log', $logDir . '/error.log');

// Only set session settings if no session is active
if (session_status() === PHP_SESSION_NONE) {
    // Set session parameters
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    
    session_start();
}

// Site Configuration
define('SITE_NAME', 'ACE MODEL COLLEGE CBT');
define('SITE_URL', 'http://localhost/ACE%20MODEL%20COLLEGE/backends/cbt');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ace_school_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');

// Session timeout in minutes
define('SESSION_TIMEOUT', 30);

// Upload directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'questions/')) {
    mkdir(UPLOAD_DIR . 'questions/', 0777, true);
}

// Set default timezone
date_default_timezone_set('Africa/Lagos');
?>