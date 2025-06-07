<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate permissions
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
//     exit();
// }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['report_id']) || !isset($_POST['enable'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

$report_id = $conn->real_escape_string($_POST['report_id']);
$enable = (int)$_POST['enable'];

try {
    $sql = "UPDATE report_cards SET allow_download = $enable WHERE id = '$report_id'";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 