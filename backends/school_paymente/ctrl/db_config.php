<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ace_school_system');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the same collation for all operations
$conn->set_charset("utf8mb4");
$conn->query("SET collation_connection = utf8mb4_unicode_ci");
?> 