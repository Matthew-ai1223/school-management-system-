<?php

// Session Configuration - MUST be set before any session_start() calls
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

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

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Session timeout in minutes
define('SESSION_TIMEOUT', 30);

// Upload directory
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/CBT_System/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'questions/')) {
    mkdir(UPLOAD_DIR . 'questions/', 0777, true);
}
?>