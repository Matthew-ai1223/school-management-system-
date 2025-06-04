<?php
require_once('../../confg.php');

header('Content-Type: application/json');

if (!isset($_GET['session'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Session parameter is required']);
    exit;
}

$session = $_GET['session'];
if (!in_array($session, ['morning', 'afternoon'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

$table = ($session === 'morning') ? 'morning_students' : 'afternoon_students';
$query = "SELECT id, fullname, department FROM $table ORDER BY fullname";
$result = $conn->query($query);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

$students = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($students);
?> 