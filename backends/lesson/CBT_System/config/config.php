<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lesson_db');

define('SITE_URL', 'http://localhost/CBT_System');
define('SITE_NAME', 'ACE Tutorial CBT System');

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');

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
  </rewritten_file>