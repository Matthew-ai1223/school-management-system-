<?php
require_once '../confg.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit();
}

$token = $_POST['token'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($token) || empty($new_password) || empty($confirm_password)) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&status=error&message=All fields are required');
    exit();
}

if ($new_password !== $confirm_password) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&status=error&message=Passwords do not match');
    exit();
}

if (strlen($new_password) < 8) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&status=error&message=Password must be at least 8 characters long');
    exit();
}

try {
    // Get token information
    $stmt = $conn->prepare("SELECT email, student_type, expires_at FROM password_reset_tokens WHERE token = ? AND used = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header('Location: forgot_password.php?status=error&message=Invalid or expired reset link');
        exit();
    }

    $token_data = $result->fetch_assoc();
    
    if (strtotime($token_data['expires_at']) < time()) {
        header('Location: forgot_password.php?status=error&message=Reset link has expired');
        exit();
    }

    // Update password in the appropriate table
    $table = $token_data['student_type'] . '_students';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed_password, $token_data['email']);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to update password');
    }

    // Mark token as used
    $stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // Redirect to login with success message
    header('Location: login.php?status=success&message=Password has been reset successfully. Please login with your new password.');

} catch (Exception $e) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&status=error&message=' . urlencode($e->getMessage()));
}

$conn->close(); 