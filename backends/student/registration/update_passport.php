<?php
require_once '../../config.php';
require_once '../../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_POST['update_passport'])) {
    $student_id = $_POST['student_id'];
    $registration_number = $_POST['registration_number'];
    
    // Multiple methods to determine the correct path
    // Method 1: Using document root (if running through web server)
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $base_dir = $_SERVER['DOCUMENT_ROOT'];
        if (strpos($base_dir, 'xampp') !== false) {
            // XAMPP specific path
            $upload_dir = $base_dir . '/ACE MODEL COLLEGE/uploads/student_passports/';
        } else {
            $upload_dir = $base_dir . '/uploads/student_passports/';
        }
    }
    // Method 2: Using relative path from script
    else {
        $upload_dir = __DIR__ . '/../../../uploads/student_passports/';
    }
    
    // Normalize path
    $upload_dir = str_replace('\\', '/', $upload_dir);
    
    // Save path in session for debugging
    $_SESSION['debug_path'] = "Upload directory: " . $upload_dir;
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Check directory is writable
    if (!is_writable($upload_dir)) {
        $_SESSION['error'] = "Upload directory is not writable. Please contact administrator.";
        header('Location: student_dashboard.php#profile');
        exit;
    }
    
    // Check if file was uploaded
    if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] == 0) {
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png');
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file_type = $_FILES['passport_photo']['type'];
        $file_size = $_FILES['passport_photo']['size'];
        $temp_file = $_FILES['passport_photo']['tmp_name'];
        
        // Debug info
        $_SESSION['debug_info'] = "File type: " . $file_type . ", Size: " . $file_size . ", Temp file: " . $temp_file;
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Only JPG, JPEG, and PNG files are allowed.";
            header('Location: student_dashboard.php#profile');
            exit;
        }
        
        // Validate file size
        if ($file_size > $max_size) {
            $_SESSION['error'] = "File size should not exceed 2MB.";
            header('Location: student_dashboard.php#profile');
            exit;
        }
        
        // Generate file name based on registration number
        // Sanitize registration number by replacing slashes with underscores
        $safe_registration = str_replace('/', '_', $registration_number);
        $file_name = $safe_registration . '.jpg';
        $upload_path = $upload_dir . $file_name;
        
        // Debug the final path
        $_SESSION['debug_path'] .= " | Final path: " . $upload_path;
        
        // Check if temp file exists
        if (!file_exists($temp_file)) {
            $_SESSION['error'] = "Temporary file does not exist.";
            header('Location: student_dashboard.php#profile');
            exit;
        }
        
        // Try to copy the file if move_uploaded_file doesn't work
        if (move_uploaded_file($temp_file, $upload_path)) {
            // Success with move_uploaded_file
            $_SESSION['success'] = "Passport photo updated successfully with move_uploaded_file.";
            
            // Skip database update for now until we know the correct column name
            $_SESSION['debug_info'] .= " | DB update skipped to focus on file upload.";
        } 
        elseif (copy($temp_file, $upload_path)) {
            // Try with copy instead
            $_SESSION['success'] = "Passport photo updated successfully with copy function.";
            
            // Skip database update for now until we know the correct column name
            $_SESSION['debug_info'] .= " | DB update skipped to focus on file upload.";
        }
        else {
            $_SESSION['error'] = "Failed to upload passport photo. Error: " . error_get_last()['message'];
        }
    } else {
        $upload_error = $_FILES['passport_photo']['error'];
        $error_message = "";
        
        switch($upload_error) {
            case UPLOAD_ERR_INI_SIZE:
                $error_message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = "A PHP extension stopped the file upload";
                break;
            default:
                $error_message = "Unknown upload error";
        }
        
        $_SESSION['error'] = "Please select a file to upload. Error: " . $error_message;
    }
    
    header('Location: student_dashboard.php#profile');
    exit;
} else {
    header('Location: student_dashboard.php');
    exit;
}
?> 