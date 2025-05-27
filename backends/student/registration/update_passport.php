<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../utils.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper to detect AJAX requests
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    if (is_ajax_request()) {
        echo "error: You must be logged in to update your passport photo.";
        exit;
    } else {
        $_SESSION['error'] = "You must be logged in to update your passport photo.";
        header('Location: login.php');
        exit;
    }
}

// Get student information
$student_id = $_SESSION['student_id'];
$registration_number = $_SESSION['registration_number'];

// Debug information
$_SESSION['debug_info'] = "Student ID: " . $student_id . ", Registration: " . $registration_number;

// Connect to database
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if connection is successful
if ($conn->connect_error) {
    if (is_ajax_request()) {
        echo "error: Database connection failed: " . $conn->connect_error;
        exit;
    } else {
        $_SESSION['error'] = "Database connection failed: " . $conn->connect_error;
        header('Location: student_dashboard.php');
        exit;
    }
}

// Check if it's a POST request with file upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['passport_photo'])) {
    if (is_ajax_request()) {
        echo "error: Invalid request method";
        exit;
    } else {
        $_SESSION['error'] = "Invalid request method";
        header('Location: student_dashboard.php');
        exit;
    }
}

// Sanitize registration number for file name
$safe_registration = str_replace(['/', '\\', ' '], '_', $registration_number);

// Get file information
$file = $_FILES['passport_photo'];
$fileName = $file['name'];
$fileTmpName = $file['tmp_name'];
$fileError = $file['error'];
$fileSize = $file['size'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Allowed file types
$allowed = ['jpg', 'jpeg', 'png'];

// Validation
try {
    // Check file extension
    if (!in_array($fileExt, $allowed)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, and PNG files are allowed.');
    }

    // Check file size (2MB max)
    if ($fileSize > 2 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum size is 2MB.');
    }

    // Check for upload errors
    if ($fileError !== 0) {
        throw new Exception('Error uploading file. Error code: ' . $fileError);
    }

    // Create upload directory if it doesn't exist
    $uploadDir = '../../../uploads/student_passports/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate file name and path
    $newFileName = $safe_registration . '.' . $fileExt;
    $uploadPath = $uploadDir . $newFileName;
    
    // Store the relative path for database
    $dbPhotoPath = 'uploads/student_passports/' . $newFileName;

    // Remove old photo if exists (with any extension)
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $oldFile = $uploadDir . $safe_registration . '.' . $ext;
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    // Move uploaded file
    if (!move_uploaded_file($fileTmpName, $uploadPath)) {
        throw new Exception('Failed to move uploaded file.');
    }

    // Update all possible photo columns in the database
    $photoColumns = ['photo', 'image', 'profile_picture', 'passport', 'student_photo'];
    $updated = false;

    foreach ($photoColumns as $column) {
        // Check if column exists
        $checkColumnQuery = "SHOW COLUMNS FROM students LIKE '$column'";
        $columnResult = $conn->query($checkColumnQuery);
        
        if ($columnResult && $columnResult->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE students SET $column = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $dbPhotoPath, $student_id);
                if ($stmt->execute()) {
                    $updated = true;
                }
                $stmt->close();
            }
        }
    }

    if (!$updated) {
        // If no existing columns found, try to add a photo column
        $alterQuery = "ALTER TABLE students ADD COLUMN IF NOT EXISTS photo VARCHAR(255)";
        $conn->query($alterQuery);
        
        // Try updating again with the new column
        $stmt = $conn->prepare("UPDATE students SET photo = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $dbPhotoPath, $student_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Update session variables
    $_SESSION['student_photo'] = $dbPhotoPath;

    // Return success
    if (is_ajax_request()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Passport photo updated successfully.',
            'photo_path' => $dbPhotoPath
        ]);
        exit;
    } else {
        $_SESSION['success'] = "Passport photo updated successfully.";
        header('Location: student_dashboard.php');
        exit;
    }

} catch (Exception $e) {
    // Return error message
    if (is_ajax_request()) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    } else {
        $_SESSION['error'] = $e->getMessage();
        header('Location: student_dashboard.php');
        exit;
    }
}
?> 