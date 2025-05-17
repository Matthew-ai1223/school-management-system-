<?php
// Simple test script to verify upload paths

// Directory paths to check
$paths = [
    '../uploads/student_files/',
    '../../uploads/student_files/',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/student_files/',
    $_SERVER['DOCUMENT_ROOT'] . '/backends/uploads/student_files/',
    dirname(__FILE__) . '/../uploads/student_files/'
];

echo "<h1>Upload Path Test</h1>";
echo "<p>Current script path: " . __FILE__ . "</p>";
echo "<p>Document root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

echo "<h2>Path Checks</h2>";
echo "<ul>";
foreach ($paths as $path) {
    echo "<li>Testing path: " . $path;
    echo "<br>Absolute path: " . realpath($path);
    echo "<br>Directory exists: " . (file_exists($path) ? 'Yes' : 'No');
    echo "<br>Is directory: " . (is_dir($path) ? 'Yes' : 'No');
    echo "<br>Is writable: " . (is_writable($path) ? 'Yes' : 'No');
    echo "</li>";
}
echo "</ul>";

// Create test file in each path that exists
echo "<h2>File Creation Test</h2>";
echo "<ul>";
foreach ($paths as $path) {
    if (file_exists($path) && is_dir($path) && is_writable($path)) {
        $testFile = $path . 'test_' . time() . '.txt';
        $content = 'Test content created at ' . date('Y-m-d H:i:s');
        
        echo "<li>Creating test file: " . $testFile;
        if (file_put_contents($testFile, $content)) {
            echo "<br>File created successfully";
            echo "<br>File exists: " . (file_exists($testFile) ? 'Yes' : 'No');
            echo "<br>File size: " . filesize($testFile) . " bytes";
        } else {
            echo "<br>Failed to create file";
        }
        echo "</li>";
    }
}
echo "</ul>";

// Display phpinfo in development
echo "<h2>PHP Info</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "error_reporting: " . ini_get('error_reporting') . "\n";
echo "</pre>";
?> 