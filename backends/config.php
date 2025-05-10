<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ace_school_system');

// Application Settings
define('SCHOOL_NAME', 'ACE MODEL COLLEGE');
define('SCHOOL_ADDRESS', 'Your School Address');
define('SCHOOL_PHONE', 'Your School Phone');
define('SCHOOL_EMAIL', 'your@email.com');

// Application Types
define('APP_TYPE_KIDDIES', 'kiddies');
define('APP_TYPE_COLLEGE', 'college');

// Session Configuration
session_start();

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time Zone
date_default_timezone_set('Africa/Lagos');

// FPDF Configuration
define('FPDF_FONTPATH', __DIR__ . '/fpdf_temp/font/');
