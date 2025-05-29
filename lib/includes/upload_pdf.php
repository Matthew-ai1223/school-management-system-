<?php
require_once 'config.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Get and validate input
$title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
$description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';

if (empty($title) || !isset($_FILES['pdf_file'])) {
    json_response(['success' => false, 'message' => 'Title and PDF file are required'], 400);
}

$file = $_FILES['pdf_file'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_message = match($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        default => 'Unknown upload error'
    };
    json_response(['success' => false, 'message' => $error_message], 400);
}

// Validate file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

if (!in_array($mime_type, ALLOWED_PDF_TYPES)) {
    json_response(['success' => false, 'message' => 'Only PDF files are allowed'], 400);
}

// Validate file size
if ($file['size'] > MAX_UPLOAD_SIZE) {
    json_response(['success' => false, 'message' => 'File is too large. Maximum size is ' . format_file_size(MAX_UPLOAD_SIZE)], 400);
}

// Create upload directory if it doesn't exist
$upload_dir = UPLOAD_PATH . 'pdfs/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
$file_path = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    json_response(['success' => false, 'message' => 'Failed to save file'], 500);
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO pdfs (title, filename, file_path, description, file_size) VALUES (?, ?, ?, ?, ?)");
$relative_path = 'uploads/pdfs/' . $filename;
$file_size = $file['size'];
$stmt->bind_param("ssssi", $title, $filename, $relative_path, $description, $file_size);

if ($stmt->execute()) {
    json_response(['success' => true, 'message' => 'PDF uploaded successfully']);
} else {
    // Remove uploaded file if database insert fails
    @unlink($file_path);
    json_response(['success' => false, 'message' => 'Failed to save PDF information: ' . $conn->error], 500);
}
?> 