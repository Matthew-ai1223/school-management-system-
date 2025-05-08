<?php
// Configuration file for School Management System

// Base URL of the application
define('BASE_URL', 'http://localhost/ACE%20MODEL%20COLLEGE');

// Application name
define('APP_NAME', 'ACE COLLEGE & KIDDIES');

// Session settings
define('SESSION_PREFIX', 'sms_');
define('SESSION_LIFETIME', 86400); // 24 hours in seconds

// File upload settings
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/ACE COLLOEGE & KIDDIES/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt');

// Profile image settings
define('DEFAULT_ADMIN_IMAGE', 'images/default_admin.png');
define('DEFAULT_TEACHER_IMAGE', 'images/default_teacher.png');
define('DEFAULT_STUDENT_IMAGE', 'images/default_student.png');
define('DEFAULT_PARENT_IMAGE', 'images/default_parent.png');

// Academic settings
define('SCHOOL_EMAIL', 'info@acemodel.edu');
define('SCHOOL_PHONE', '+1234567890');
define('SCHOOL_ADDRESS', 'ACE COLLOEGE & KIDDIES, 123 Education Street, City, Country');

// Grading scale
$GLOBALS['GRADING_SCALE'] = [
    ['min' => 90, 'max' => 100, 'grade' => 'A+', 'gpa' => 4.0],
    ['min' => 85, 'max' => 89, 'grade' => 'A', 'gpa' => 3.7],
    ['min' => 80, 'max' => 84, 'grade' => 'A-', 'gpa' => 3.5],
    ['min' => 75, 'max' => 79, 'grade' => 'B+', 'gpa' => 3.3],
    ['min' => 70, 'max' => 74, 'grade' => 'B', 'gpa' => 3.0],
    ['min' => 65, 'max' => 69, 'grade' => 'B-', 'gpa' => 2.7],
    ['min' => 60, 'max' => 64, 'grade' => 'C+', 'gpa' => 2.3],
    ['min' => 55, 'max' => 59, 'grade' => 'C', 'gpa' => 2.0],
    ['min' => 50, 'max' => 54, 'grade' => 'C-', 'gpa' => 1.7],
    ['min' => 45, 'max' => 49, 'grade' => 'D+', 'gpa' => 1.3],
    ['min' => 40, 'max' => 44, 'grade' => 'D', 'gpa' => 1.0],
    ['min' => 0, 'max' => 39, 'grade' => 'F', 'gpa' => 0.0]
];

// System version
define('SYSTEM_VERSION', '1.0.0');

// Error reporting settings (set to 0 in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Timezone setting
date_default_timezone_set('UTC');
?> 