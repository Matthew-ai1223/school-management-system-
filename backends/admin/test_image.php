<?php
echo "<h1>Image Path Test</h1>";

// Test for a specific registration number
$registration_number = "2425/KID/0014";
$safe_registration = str_replace('/', '_', $registration_number);

// Document root and base URL
$document_root = $_SERVER['DOCUMENT_ROOT'];
$base_url = "http://" . $_SERVER['HTTP_HOST'];
if (substr($base_url, -1) !== '/') {
    $base_url = rtrim($base_url, '/');
}

echo "<p>Safe registration number: " . $safe_registration . "</p>";
echo "<p>Document Root: " . $document_root . "</p>";
echo "<p>Base URL: " . $base_url . "</p>";

// Check various possible paths
$possible_paths = [
    // Path 1: Direct from document root
    $document_root . '/uploads/student_passports/' . $safe_registration . '.jpg',
    // Path 2: From ACE MODEL COLLEGE folder
    $document_root . '/ACE MODEL COLLEGE/uploads/student_passports/' . $safe_registration . '.jpg',
    // Path 3: Relative path
    __DIR__ . '/../../uploads/student_passports/' . $safe_registration . '.jpg'
];

echo "<h2>Path Tests:</h2>";
foreach ($possible_paths as $index => $path) {
    echo "<p>Path " . ($index + 1) . ": " . $path . "<br>";
    echo "Exists: " . (file_exists($path) ? "Yes" : "No") . "<br>";
    if (file_exists($path)) {
        echo "Size: " . filesize($path) . " bytes<br>";
        echo "Image:<br><img src='" . str_replace($document_root, $base_url, $path) . "' style='max-width: 200px;'>";
    }
    echo "</p>";
}

// Try to create a test file to verify write permissions
echo "<h2>Write Test:</h2>";
$test_dir = $document_root . '/uploads/student_passports/';
$test_file = $test_dir . 'test_file.txt';

echo "<p>Test directory: " . $test_dir . "<br>";
echo "Directory exists: " . (file_exists($test_dir) ? "Yes" : "No") . "<br>";
if (file_exists($test_dir)) {
    echo "Directory writable: " . (is_writable($test_dir) ? "Yes" : "No") . "<br>";
    
    $result = @file_put_contents($test_file, 'Test content at ' . date('Y-m-d H:i:s'));
    if ($result !== false) {
        echo "Test file created successfully (" . $result . " bytes)<br>";
        unlink($test_file);
        echo "Test file deleted<br>";
    } else {
        echo "Failed to create test file: " . error_get_last()['message'] . "<br>";
    }
}
echo "</p>";

// Show all image tags for the different possibilities
echo "<h2>Image Tag Tests:</h2>";

$url_paths = [
    $base_url . '/uploads/student_passports/' . $safe_registration . '.jpg',
    $base_url . '/ACE MODEL COLLEGE/uploads/student_passports/' . $safe_registration . '.jpg',
    $base_url . '/backends/uploads/student_passports/' . $safe_registration . '.jpg',
];

foreach ($url_paths as $index => $url) {
    echo "<p>URL " . ($index + 1) . ": " . $url . "<br>";
    echo "<img src='" . $url . "' alt='Test Image " . ($index + 1) . "' style='max-width: 200px; height: 200px; object-fit: cover;' onerror=\"this.style.border='1px solid red'; this.style.padding='10px'; this.alt='Failed to load';\"><br>";
    echo "</p>";
}
?> 