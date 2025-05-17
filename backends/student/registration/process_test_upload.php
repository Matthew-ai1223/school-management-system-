<?php
// Set error reporting for maximum visibility
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>File Upload Test Results</h1>";

// Define PHP file upload errors
$phpFileUploadErrors = array(
    0 => 'There is no error, the file uploaded with success',
    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
    3 => 'The uploaded file was only partially uploaded',
    4 => 'No file was uploaded',
    6 => 'Missing a temporary folder',
    7 => 'Failed to write file to disk',
    8 => 'A PHP extension stopped the file upload',
);

// Check if a file was uploaded
if (!isset($_FILES['test_file'])) {
    echo "<div style='color: red;'>No file was submitted. Please try again.</div>";
    echo "<p><a href='test_upload.php'>Back to test form</a></p>";
    exit;
}

// Get file information
$file = $_FILES['test_file'];
$uploads_dir = '../uploads/student_files/';

// Display upload details
echo "<h2>Upload Information</h2>";
echo "<pre>";
echo "File name: " . htmlspecialchars($file['name']) . "\n";
echo "File type: " . htmlspecialchars($file['type']) . "\n";
echo "File size: " . number_format($file['size']) . " bytes\n";
echo "Temporary file: " . htmlspecialchars($file['tmp_name']) . "\n";
echo "Error code: " . $file['error'] . " (" . $phpFileUploadErrors[$file['error']] . ")\n";
echo "</pre>";

// Check for errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo "<div style='color: red; font-weight: bold;'>Upload failed with error: " . $phpFileUploadErrors[$file['error']] . "</div>";
    echo "<p><a href='test_upload.php'>Back to test form</a></p>";
    exit;
}

// Check if the temporary file exists
echo "<h2>Temporary File Check</h2>";
echo "<pre>";
if (file_exists($file['tmp_name'])) {
    echo "Temporary file exists\n";
    echo "Temporary file size: " . filesize($file['tmp_name']) . " bytes\n";
    echo "Temporary file is_uploaded_file: " . (is_uploaded_file($file['tmp_name']) ? 'Yes' : 'No') . "\n";
} else {
    echo "Temporary file does not exist!\n";
}
echo "</pre>";

// Ensure the upload directory exists
if (!file_exists($uploads_dir)) {
    if (!mkdir($uploads_dir, 0777, true)) {
        echo "<div style='color: red;'>Failed to create upload directory!</div>";
        echo "<p><a href='test_upload.php'>Back to test form</a></p>";
        exit;
    }
    chmod($uploads_dir, 0777);
}

// Generate a unique filename
$new_filename = 'test_' . time() . '_' . basename($file['name']);
$destination = $uploads_dir . $new_filename;

echo "<h2>Upload Attempt</h2>";
echo "<pre>";
echo "Destination file: $destination\n";

// Try to move the uploaded file
$success = false;

// First try: move_uploaded_file
echo "Attempt 1: Using move_uploaded_file()...\n";
if (move_uploaded_file($file['tmp_name'], $destination)) {
    echo "Success! File was moved successfully.\n";
    $success = true;
} else {
    echo "Failed. Error: " . error_get_last()['message'] . "\n";
    
    // Second try: copy
    echo "\nAttempt 2: Using copy()...\n";
    if (copy($file['tmp_name'], $destination)) {
        echo "Success! File was copied successfully.\n";
        $success = true;
    } else {
        echo "Failed. Error: " . error_get_last()['message'] . "\n";
    }
}

// Check the result
if ($success) {
    echo "\nVerifying upload result:\n";
    if (file_exists($destination)) {
        echo "File exists in destination\n";
        echo "File size: " . filesize($destination) . " bytes\n";
        echo "File permissions: " . substr(sprintf('%o', fileperms($destination)), -4) . "\n";
        
        echo "\nUpload SUCCESSFUL!\n";
    } else {
        echo "File does NOT exist in destination despite successful operation!\n";
        echo "This suggests a path issue or permissions problem with the directory.\n";
    }
} else {
    echo "\nBoth upload methods failed. This could be due to:\n";
    echo "1. Incorrect directory path\n";
    echo "2. Insufficient permissions\n";
    echo "3. Disk space issues\n";
    echo "4. PHP configuration problems\n";
}

echo "</pre>";

// Server information
echo "<h2>Server Information</h2>";
echo "<pre>";
echo "Operating System: " . PHP_OS . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server Path: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Current Script Path: " . __FILE__ . "\n";
echo "</pre>";

echo "<p><a href='test_upload.php'>Back to test form</a></p>";
?> 