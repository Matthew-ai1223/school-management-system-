<?php
require_once '../config.php';
require_once 'class_teacher_auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine which page to redirect to based on the referring page
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$redirect = 'login.php';

if (strpos($referer, 'create_cbt_exam.php') !== false || 
    strpos($referer, 'manage_cbt_questions.php') !== false || 
    strpos($referer, 'cbt_login.php') !== false) {
    $redirect = 'cbt_login.php';
}

// Use the authentication class for logout
$auth = new ClassTeacherAuth();
$auth->logout();

// Redirect to the appropriate login page
header("Location: $redirect");
exit;
?> 