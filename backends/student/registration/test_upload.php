<?php
// Set error reporting for maximum visibility
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>File Upload Test</h1>";

// Check PHP configuration
echo "<h2>PHP Upload Configuration</h2>";
echo "<pre>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "</pre>";

// Check upload directory
$uploads_dir = '../uploads/student_files/';
echo "<h2>Upload Directory Check</h2>";
echo "<pre>";

// Try to create directory if it doesn't exist
if (!file_exists($uploads_dir)) {
    echo "Upload directory does not exist, attempting to create...\n";
    if (mkdir($uploads_dir, 0777, true)) {
        echo "Successfully created directory: $uploads_dir\n";
        chmod($uploads_dir, 0777);
        echo "Set permissions to 0777\n";
    } else {
        echo "FAILED to create directory: $uploads_dir\n";
        echo "Error: " . error_get_last()['message'] . "\n";
    }
} else {
    echo "Upload directory exists: $uploads_dir\n";
}

// Check if directory is writable
if (is_writable($uploads_dir)) {
    echo "Directory is writable\n";
} else {
    echo "Directory is NOT writable - attempting to fix permissions...\n";
    chmod($uploads_dir, 0777);
    if (is_writable($uploads_dir)) {
        echo "Successfully made directory writable\n";
    } else {
        echo "FAILED to make directory writable\n";
    }
}

// Get absolute path
echo "Absolute path: " . realpath($uploads_dir) . "\n";

// Directory permissions
echo "Directory permissions: " . substr(sprintf('%o', fileperms($uploads_dir)), -4) . "\n";

// Parent directory permissions
$parent_dir = dirname($uploads_dir);
echo "Parent directory permissions: " . substr(sprintf('%o', fileperms($parent_dir)), -4) . "\n";

echo "</pre>";

// Test file creation
echo "<h2>File Creation Test</h2>";
echo "<pre>";

$test_file = $uploads_dir . 'test_' . time() . '.txt';
$content = 'This is a test file to check write permissions. Created at ' . date('Y-m-d H:i:s');

echo "Attempting to create test file: $test_file\n";

if (file_put_contents($test_file, $content)) {
    echo "Successfully created test file\n";
    if (file_exists($test_file)) {
        echo "File exists after creation\n";
        echo "File size: " . filesize($test_file) . " bytes\n";
        echo "File content: " . file_get_contents($test_file) . "\n";
        
        // Try to delete the test file
        if (unlink($test_file)) {
            echo "Successfully deleted test file\n";
        } else {
            echo "Failed to delete test file\n";
        }
    } else {
        echo "File does NOT exist after creation attempt!\n";
    }
} else {
    echo "FAILED to create test file\n";
    echo "Error: " . error_get_last()['message'] . "\n";
}

echo "</pre>";

// Form for testing uploads
echo "<h2>Upload Test Form</h2>";
echo '<form action="process_test_upload.php" method="POST" enctype="multipart/form-data">';
echo '<input type="file" name="test_file" class="form-control">';
echo '<br><br>';
echo '<input type="submit" value="Test Upload" class="btn btn-primary">';
echo '</form>';
?> 