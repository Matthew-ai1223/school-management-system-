<?php
echo "<h2>PHP File Upload Configuration</h2>";

echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'On' : 'Off') . "</p>";
echo "<p>upload_tmp_dir: " . ini_get('upload_tmp_dir') . "</p>";

echo "<h2>File Upload Debug Info</h2>";

echo "<p>Server software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Server OS: " . PHP_OS . "</p>";
echo "<p>PHP version: " . PHP_VERSION . "</p>";

echo "<h2>Upload Directory Info</h2>";

$upload_dir = '../../../uploads/student_passports/';
echo "<p>Upload directory: " . realpath($upload_dir) . "</p>";
echo "<p>Upload directory exists: " . (file_exists($upload_dir) ? 'Yes' : 'No') . "</p>";

if(file_exists($upload_dir)) {
    echo "<p>Is writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";
    
    // Try to create a test file
    $test_file = $upload_dir . 'test_file.txt';
    $result = file_put_contents($test_file, 'This is a test');
    echo "<p>Test write result: " . ($result !== false ? 'Success' : 'Failed') . "</p>";
    
    if($result !== false) {
        echo "<p>Test file path: " . realpath($test_file) . "</p>";
        echo "<p>Test file size: " . filesize($test_file) . " bytes</p>";
        
        // Clean up
        unlink($test_file);
    }
}

// Debug form properties
echo "<h2>HTML Form Requirements</h2>";
echo "<p>Form must include enctype=\"multipart/form-data\" attribute</p>";
echo "<p>Form method must be POST</p>";

// Debug temporary directory
echo "<h2>Temporary Directory Info</h2>";
$temp_dir = sys_get_temp_dir();
echo "<p>System temp directory: " . $temp_dir . "</p>";
echo "<p>Is writable: " . (is_writable($temp_dir) ? 'Yes' : 'No') . "</p>";

// Check permissions
echo "<h2>Current Script Permissions</h2>";
echo "<p>Current script user: " . get_current_user() . "</p>";
echo "<p>Current script group: " . (function_exists('posix_getgrgid') ? posix_getgrgid(posix_getgid())['name'] : 'N/A (Windows)') . "</p>";

echo "<h2>Folder Permissions</h2>";
if(function_exists('posix_getpwuid')) {
    $folder_owner = posix_getpwuid(fileowner($upload_dir));
    echo "<p>Upload folder owner: " . $folder_owner['name'] . "</p>";
} else {
    echo "<p>Upload folder permissions (Windows): Cannot display detailed permissions</p>";
}
?> 