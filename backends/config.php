<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ace_school_system');

// Application Settings
define('SCHOOL_NAME', 'ACE COLLEGE');
define('SCHOOL_SHORT_NAME', 'ACE');
define('SCHOOL_ADDRESS', 'Beside Agodi Baptist Church Opposite Loyola College Second Gate, Odejayi Ibadan');
define('SCHOOL_PHONE', '+234 803 465 0368');
define('SCHOOL_EMAIL', 'acemodelcollege@gmail.com');

// Application Types
define('APP_TYPE_KIDDIES', 'kiddies');
define('APP_TYPE_COLLEGE', 'college');

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time Zone
date_default_timezone_set('Africa/Lagos');

// FPDF Configuration
define('FPDF_FONTPATH', __DIR__ . '/fpdf_temp/font/');
