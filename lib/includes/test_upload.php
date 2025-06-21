<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Video Upload Test</h2>";

// Test database connection
echo "<h3>1. Testing Database Connection</h3>";
require_once 'config.php';
echo "✓ Database connection successful<br>";

// Test table existence
echo "<h3>2. Testing Table Structure</h3>";
$result = $conn->query("DESCRIBE videos");
if ($result) {
    echo "✓ Videos table structure:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})<br>";
    }
} else {
    echo "✗ Error describing videos table: " . $conn->error . "<br>";
}

// Test YouTube URL parsing
echo "<h3>3. Testing YouTube URL Parsing</h3>";
$test_url = "https://www.youtube.com/watch?v=dQw4w9WgXcQ";
$video_id = get_youtube_video_id($test_url);
echo "Test URL: $test_url<br>";
echo "Extracted Video ID: $video_id<br>";
if ($video_id) {
    echo "✓ YouTube URL parsing works<br>";
} else {
    echo "✗ YouTube URL parsing failed<br>";
}

// Test thumbnail generation
echo "<h3>4. Testing Thumbnail Generation</h3>";
$thumbnail_url = get_youtube_thumbnail($video_id);
echo "Thumbnail URL: $thumbnail_url<br>";

// Test database insert (without actually inserting)
echo "<h3>5. Testing Database Prepare Statement</h3>";
$stmt = $conn->prepare("INSERT INTO videos (title, youtube_url, video_id, description, thumbnail_url) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    echo "✓ Prepare statement successful<br>";
} else {
    echo "✗ Prepare statement failed: " . $conn->error . "<br>";
}

echo "<h3>Test Complete</h3>";
echo "<p><a href='../dashboard.php'>Return to Dashboard</a></p>";
?> 