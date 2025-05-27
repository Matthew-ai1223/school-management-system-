<?php
require_once '../config.php';
require_once '../database.php';

// Connect to database
$db = Database::getInstance();
$conn = $db->getConnection();

function showTableStructure($conn, $tableName) {
    echo "<h2>Table: $tableName</h2>";
    
    // Get columns
    $columns_result = $conn->query("SHOW COLUMNS FROM $tableName");
    
    if ($columns_result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($column_row = $columns_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$column_row['Field']}</td>";
            echo "<td>{$column_row['Type']}</td>";
            echo "<td>{$column_row['Null']}</td>";
            echo "<td>{$column_row['Key']}</td>";
            echo "<td>{$column_row['Default']}</td>";
            echo "<td>{$column_row['Extra']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Error getting columns for $tableName: " . $conn->error . "</p>";
    }
    
    // Show sample data
    echo "<h3>Sample Data (5 rows):</h3>";
    $sample_data = $conn->query("SELECT * FROM $tableName LIMIT 5");
    
    if ($sample_data && $sample_data->num_rows > 0) {
        echo "<table border='1'>";
        
        // Header row with column names
        $first_row = $sample_data->fetch_assoc();
        echo "<tr>";
        foreach ($first_row as $column_name => $value) {
            echo "<th>$column_name</th>";
        }
        echo "</tr>";
        
        // Output first row
        echo "<tr>";
        foreach ($first_row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
        
        // Output remaining rows
        while ($row = $sample_data->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No data found or error: " . $conn->error . "</p>";
    }
}

// Check the tables related to the exam system
$tables = ['cbt_student_exams', 'cbt_exam_attempts', 'cbt_student_answers', 'cbt_questions'];

echo "<h1>Database Structure Debugging</h1>";

foreach ($tables as $table) {
    showTableStructure($conn, $table);
    echo "<hr>";
}
?> 