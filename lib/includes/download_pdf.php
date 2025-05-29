<?php
require_once 'config.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid request');
}

$pdf_id = (int)$_GET['id'];

// Get PDF information
$stmt = $conn->prepare("SELECT * FROM pdfs WHERE id = ?");
$stmt->bind_param("i", $pdf_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die('PDF not found');
}

$pdf = $result->fetch_assoc();
$file_path = __DIR__ . '/../' . $pdf['file_path'];

// Check if file exists
if (!file_exists($file_path)) {
    die('File not found');
}

// Log download
$stmt = $conn->prepare("INSERT INTO downloads (pdf_id, ip_address, user_agent) VALUES (?, ?, ?)");
$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$stmt->bind_param("iss", $pdf_id, $ip, $user_agent);
$stmt->execute();

// Update download count
$stmt = $conn->prepare("UPDATE pdfs SET downloads = downloads + 1 WHERE id = ?");
$stmt->bind_param("i", $pdf_id);
$stmt->execute();

// Send file
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $pdf['filename'] . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file in chunks to handle large files
$chunk_size = 1024 * 1024; // 1MB chunks
$handle = fopen($file_path, 'rb');

while (!feof($handle)) {
    echo fread($handle, $chunk_size);
    ob_flush();
    flush();
}

fclose($handle);
exit;
?> 