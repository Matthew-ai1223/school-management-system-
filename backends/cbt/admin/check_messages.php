<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0");
    $unread_count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count
    ]);
} catch (PDOException $e) {
    error_log("Error checking messages: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error checking messages'
    ]);
} 