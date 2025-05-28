<?php
require_once '../confg.php';

// For debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit();
}

$email = $_POST['email'] ?? '';
$student_type = $_POST['student_type'] ?? '';

// Debug output
echo "Submitted email: " . htmlspecialchars($email) . "<br>";
echo "Submitted student type: " . htmlspecialchars($student_type) . "<br>";

if (empty($email) || empty($student_type)) {
    header('Location: forgot_password.php?status=error&message=Please provide both email and student type');
    exit();
}

try {
    // Check the specified table first based on student_type
    $table = $student_type . '_students';
    echo "Checking table: " . htmlspecialchars($table) . "<br>";

    // Debug: Show table contents
    $debug_query = $conn->query("SELECT email FROM $table");
    echo "All emails in $table:<br>";
    while ($row = $debug_query->fetch_assoc()) {
        echo htmlspecialchars($row['email']) . "<br>";
    }

    $stmt = $conn->prepare("SELECT id, email FROM $table WHERE email = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    echo "Number of matching rows found: " . $result->num_rows . "<br>";

    if ($result->num_rows === 0) {
        header('Location: forgot_password.php?status=error&message=No account found with this email address (' . htmlspecialchars($email) . ') in ' . htmlspecialchars($table));
        exit();
    }

    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store token in database
    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (email, token, student_type, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $token, $student_type, $expires_at);
    $stmt->execute();

    // Redirect to reset password page directly
    header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

$conn->close(); 