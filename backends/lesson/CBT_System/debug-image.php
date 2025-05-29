<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

$db = Database::getInstance()->getConnection();

// Get a question with an image
$query = "SELECT * FROM questions WHERE image_url IS NOT NULL LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if ($question) {
    echo "<h3>Image Path Debug Information:</h3>";
    echo "<pre>";
    echo "Image URL from database: " . $question['image_url'] . "\n";
    echo "Full server path: " . UPLOAD_DIR . $question['image_url'] . "\n";
    echo "Web accessible URL: " . UPLOAD_URL . $question['image_url'] . "\n";
    echo "File exists: " . (file_exists(UPLOAD_DIR . $question['image_url']) ? 'Yes' : 'No') . "\n";
    echo "</pre>";

    echo "<h3>Test Image Display:</h3>";
    echo '<img src="' . UPLOAD_URL . $question['image_url'] . '" style="max-width: 300px;">';
} else {
    echo "No questions with images found.";
} 