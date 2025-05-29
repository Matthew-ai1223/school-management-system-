<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['exam_attempt'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// Verify user's active status
$db = Database::getInstance()->getConnection();
$table = $_SESSION['user_table'];
$stmt = $db->prepare("SELECT is_active, expiration_date FROM $table WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if account is still active and not expired
$is_expired = strtotime($user['expiration_date']) < strtotime('today');
if (!$user['is_active'] || $is_expired) {
    http_response_code(403);
    exit('Account inactive or expired');
}

$data = json_decode(file_get_contents('php://input'), true);

$query = "INSERT INTO activity_logs (user_id, attempt_id, activity_type, details) 
          VALUES (:user_id, :attempt_id, :activity_type, :details)";
$stmt = $db->prepare($query);
$stmt->execute([
    ':user_id' => $_SESSION['user_id'],
    ':attempt_id' => $data['attempt_id'],
    ':activity_type' => $data['type'],
    ':details' => json_encode($data)
]);

http_response_code(200);
echo json_encode(['status' => 'success']); 