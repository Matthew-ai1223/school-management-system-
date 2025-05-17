<?php
// Script to create necessary directories for storing student files

// Define the directories we need
$directories = [
    'uploads/student_files',
];

// Base directory
$base_dir = __DIR__ . '/';

// Create each directory if it doesn't exist
foreach ($directories as $dir) {
    $path = $base_dir . $dir;
    if (!file_exists($path)) {
        if (mkdir($path, 0777, true)) {
            echo "Created directory: $path<br>";
        } else {
            echo "ERROR: Failed to create directory: $path<br>";
        }
    } else {
        echo "Directory already exists: $path<br>";
    }
    
    // Ensure directory is writable
    if (!is_writable($path)) {
        if (chmod($path, 0777)) {
            echo "Made directory writable: $path<br>";
        } else {
            echo "ERROR: Failed to make directory writable: $path<br>";
        }
    } else {
        echo "Directory is writable: $path<br>";
    }
}

echo "<p>Directory setup complete.</p>";
echo "<p><a href='../admin/students.php'>Return to Admin Dashboard</a></p>"; 