<?php
require_once 'config.php';

// Get video ID from URL parameter
$video_id = isset($_GET['id']) ? sanitize_input($_GET['id']) : '';

if (empty($video_id)) {
    die('No video ID provided');
}

// Get video information from database
$stmt = $conn->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->bind_param("i", $video_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die('Video not found');
}

$video = $result->fetch_assoc();

// Ensure we have a valid YouTube video ID
if (empty($video['video_id'])) {
    die('Invalid YouTube video ID');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['title']); ?></title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            background: #000;
            display: flex;
            flex-direction: column;
        }
        .video-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        iframe {
            max-width: 100%;
            max-height: 100%;
            width: 1280px;
            height: 720px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
        }
        .video-info {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 15px;
            font-family: Arial, sans-serif;
        }
        .video-info h2 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        .video-info p {
            margin: 0;
            font-size: 14px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="video-container">
        <iframe
            width="1280"
            height="720"
            src="https://www.youtube.com/embed/<?php echo htmlspecialchars($video['video_id']); ?>?autoplay=1"
            title="<?php echo htmlspecialchars($video['title']); ?>"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen>
        </iframe>
    </div>
    <div class="video-info">
        <h2><?php echo htmlspecialchars($video['title']); ?></h2>
        <?php if ($video['description']): ?>
            <p><?php echo htmlspecialchars($video['description']); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
