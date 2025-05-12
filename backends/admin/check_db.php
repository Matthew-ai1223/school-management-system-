<?php
require_once '../config.php';
require_once '../database.php';

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Check students table structure
$result = $mysqli->query("DESCRIBE students");
echo "Students table structure:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

// Check sample data
$result = $mysqli->query("SELECT * FROM students LIMIT 1");
echo "\nSample student data:\n";
if ($result->num_rows > 0) {
    print_r($result->fetch_assoc());
}

// Check files table structure
$result = $mysqli->query("DESCRIBE files");
echo "\nFiles table structure:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
} 