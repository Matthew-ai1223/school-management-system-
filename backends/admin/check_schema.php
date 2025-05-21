<?php
// Simple script to check the database schema
require_once __DIR__ . '/../../backends/database.php';

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Check if students table exists
$tables = $mysqli->query("SHOW TABLES");
if ($tables) {
    $tableNames = [];
    while ($row = $tables->fetch_array()) {
        $tableNames[] = $row[0];
    }
    echo "Tables in database: " . implode(", ", $tableNames) . "\n\n";
}

// Check students table structure
$result = $mysqli->query("SHOW COLUMNS FROM students");
if (!$result) {
    echo "Error: " . $mysqli->error . "\n";
} else {
    echo "Columns in students table:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
}
?> 