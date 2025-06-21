<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Upload Response</h2>";

// Simulate the form data that would be sent
$_POST['title'] = 'Test Video';
$_POST['youtube_url'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
$_POST['description'] = 'Test description';

echo "<h3>Simulated Form Data:</h3>";
echo "Title: " . $_POST['title'] . "<br>";
echo "URL: " . $_POST['youtube_url'] . "<br>";
echo "Description: " . $_POST['description'] . "<br>";

echo "<h3>Response from upload_video.php:</h3>";
echo "<pre>";

// Capture the output
ob_start();

// Include the upload script
include 'upload_video.php';

$output = ob_get_clean();
echo htmlspecialchars($output);
echo "</pre>";

echo "<h3>Raw Response:</h3>";
echo "<pre>";
echo htmlspecialchars($output);
echo "</pre>";

echo "<p><a href='../dashboard.php'>Return to Dashboard</a></p>";
?> 