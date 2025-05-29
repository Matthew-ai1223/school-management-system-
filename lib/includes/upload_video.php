<?php
require_once 'config.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Get and validate input
$title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
$youtube_url = isset($_POST['youtube_url']) ? sanitize_input($_POST['youtube_url']) : '';
$description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';

if (empty($title) || empty($youtube_url)) {
    json_response(['success' => false, 'message' => 'Title and YouTube URL are required'], 400);
}

// Validate YouTube URL and get video ID
$video_id = get_youtube_video_id($youtube_url);
if (!$video_id) {
    json_response(['success' => false, 'message' => 'Invalid YouTube URL'], 400);
}

// Get video thumbnail
$thumbnail_url = get_youtube_thumbnail($video_id);

// Check if video already exists
$stmt = $conn->prepare("SELECT id FROM videos WHERE video_id = ?");
$stmt->bind_param("s", $video_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    json_response(['success' => false, 'message' => 'This video has already been added'], 400);
}

// Insert video into database
$stmt = $conn->prepare("INSERT INTO videos (title, youtube_url, video_id, description, thumbnail_url) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $title, $youtube_url, $video_id, $description, $thumbnail_url);

if ($stmt->execute()) {
    // Verify YouTube API access
    $api_url = "https://www.googleapis.com/youtube/v3/videos?id={$video_id}&key=" . YOUTUBE_API_KEY . "&part=status";
    $response = @file_get_contents($api_url);
    
    if ($response) {
        $data = json_decode($response);
        if (!$data || !isset($data->items) || empty($data->items)) {
            // Video exists in our database but might be private/deleted on YouTube
            json_response([
                'success' => true,
                'message' => 'Video added successfully, but please verify it is publicly accessible on YouTube',
                'warning' => true
            ]);
        }
    }
    
    json_response(['success' => true, 'message' => 'Video added successfully']);
} else {
    json_response(['success' => false, 'message' => 'Failed to add video: ' . $conn->error], 500);
}
?> 