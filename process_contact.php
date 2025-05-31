<?php
// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'backends/cbt/config/config.php';
require_once 'backends/cbt/includes/Database.php';

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Function to send JSON response
function sendJsonResponse($success, $message) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data and sanitize
    $fname = sanitize_input($_POST['fname'] ?? '');
    $lname = sanitize_input($_POST['lname'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $subject = sanitize_input($_POST['subject'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');

    // Validate required fields
    if (!$fname || !$lname || !$email || !$subject || !$message) {
        sendJsonResponse(false, 'All fields are required');
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Please enter a valid email address');
    }

    try {
        $db = Database::getInstance()->getConnection();

        // Insert message into database
        $stmt = $db->prepare("INSERT INTO contact_messages (first_name, last_name, email, subject, message, created_at) 
                            VALUES (:fname, :lname, :email, :subject, :message, NOW())");
        
        $stmt->execute([
            ':fname' => $fname,
            ':lname' => $lname,
            ':email' => $email,
            ':subject' => $subject,
            ':message' => $message
        ]);

        sendJsonResponse(true, 'Thank you for your message. We will get back to you soon!');
    } catch (PDOException $e) {
        error_log("Contact form error: " . $e->getMessage());
        sendJsonResponse(false, 'Sorry, there was an error sending your message. Please try again later.');
    }
} else {
    sendJsonResponse(false, 'Invalid request method');
}