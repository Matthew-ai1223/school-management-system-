<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Check if script is being reached
error_log("upload_video.php script started");

// Use absolute path to ensure config.php is found
require_once __DIR__ . '/config.php';

// Debug: Check if config.php was loaded
error_log("config.php loaded successfully");

// Simple test - if this is a GET request, show debug info
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "upload_video.php is working!<br>";
    echo "Database connection: " . ($conn ? "OK" : "FAILED") . "<br>";
    echo "Videos table exists: " . ($conn->query("SHOW TABLES LIKE 'videos'")->num_rows > 0 ? "YES" : "NO") . "<br>";
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

error_log("POST request received");

// Get and validate input
$title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
$youtube_url = isset($_POST['youtube_url']) ? sanitize_input($_POST['youtube_url']) : '';
$description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';

error_log("Input received - Title: $title, URL: $youtube_url");

if (empty($title) || empty($youtube_url)) {
    error_log("Missing required fields");
    json_response(['success' => false, 'message' => 'Title and YouTube URL are required'], 400);
}

// Validate YouTube URL and get video ID
$video_id = get_youtube_video_id($youtube_url);
error_log("Video ID extracted: $video_id");

if (!$video_id) {
    error_log("Invalid YouTube URL");
    json_response(['success' => false, 'message' => 'Invalid YouTube URL'], 400);
}

// Get video thumbnail
$thumbnail_url = get_youtube_thumbnail($video_id);
error_log("Thumbnail URL: $thumbnail_url");

// Check if video already exists
error_log("Checking if video already exists...");
$stmt = $conn->prepare("SELECT id FROM videos WHERE video_id = ?");
if (!$stmt) {
    error_log("Prepare statement failed: " . $conn->error);
    json_response(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error], 500);
}

$stmt->bind_param("s", $video_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    error_log("Video already exists");
    json_response(['success' => false, 'message' => 'This video has already been added'], 400);
}

// Insert video into database
error_log("Inserting video into database...");
$stmt = $conn->prepare("INSERT INTO videos (title, youtube_url, video_id, description, thumbnail_url) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    error_log("Insert prepare statement failed: " . $conn->error);
    json_response(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error], 500);
}

$stmt->bind_param("sssss", $title, $youtube_url, $video_id, $description, $thumbnail_url);

if ($stmt->execute()) {
    error_log("Video inserted successfully");
    json_response(['success' => true, 'message' => 'Video added successfully']);
} else {
    error_log("Insert failed: " . $conn->error);
    json_response(['success' => false, 'message' => 'Failed to add video: ' . $conn->error], 500);
}
?> 