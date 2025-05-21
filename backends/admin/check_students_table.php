<?php
// Simple script to check students table structure
require_once __DIR__ . '/../../backends/database.php';

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Check if table exists
$table_exists = $mysqli->query("SHOW TABLES LIKE 'students'");
if ($table_exists && $table_exists->num_rows > 0) {
    echo "Students table exists\n";
    
    // Get columns
    $result = $mysqli->query("SHOW COLUMNS FROM students");
    if ($result) {
        echo "Columns:\n";
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['Field']} ({$row['Type']})\n";
            $columns[] = $row['Field'];
        }
        
        // Get sample data
        echo "\nSample data (first 3 rows):\n";
        $data = $mysqli->query("SELECT * FROM students LIMIT 3");
        if ($data && $data->num_rows > 0) {
            while ($row = $data->fetch_assoc()) {
                echo "\nStudent ID: {$row['id']}\n";
                foreach ($columns as $col) {
                    echo "- $col: " . (isset($row[$col]) ? $row[$col] : 'NULL') . "\n";
                }
            }
        } else {
            echo "No data in students table\n";
        }
    } else {
        echo "Error getting columns: " . $mysqli->error;
    }
} else {
    echo "Students table does not exist";
}
?> 