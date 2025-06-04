<?php
// Define paths
define('QR_BASE_PATH', __DIR__);
define('QR_CACHE_PATH', QR_BASE_PATH . DIRECTORY_SEPARATOR . 'cache');

echo "<pre>";
echo "Testing directory permissions and paths:\n\n";

// Test base directory
echo "Base directory: " . QR_BASE_PATH . "\n";
echo "Base directory exists: " . (file_exists(QR_BASE_PATH) ? 'Yes' : 'No') . "\n";
echo "Base directory writable: " . (is_writable(QR_BASE_PATH) ? 'Yes' : 'No') . "\n\n";

// Test cache directory
echo "Cache directory: " . QR_CACHE_PATH . "\n";
if (!file_exists(QR_CACHE_PATH)) {
    echo "Creating cache directory...\n";
    if (mkdir(QR_CACHE_PATH, 0777, true)) {
        echo "Cache directory created successfully.\n";
    } else {
        echo "Failed to create cache directory!\n";
    }
}

echo "Cache directory exists: " . (file_exists(QR_CACHE_PATH) ? 'Yes' : 'No') . "\n";
echo "Cache directory writable: " . (is_writable(QR_CACHE_PATH) ? 'Yes' : 'No') . "\n\n";

// Test file creation
$testFile = QR_CACHE_PATH . DIRECTORY_SEPARATOR . 'test.txt';
echo "Testing file creation in cache directory...\n";
if (file_put_contents($testFile, 'Test content')) {
    echo "Test file created successfully.\n";
    unlink($testFile);
    echo "Test file removed.\n";
} else {
    echo "Failed to create test file!\n";
}

echo "\nCurrent PHP working directory: " . getcwd() . "\n";
echo "PHP process user: " . get_current_user() . "\n";
echo "</pre>";
?> 