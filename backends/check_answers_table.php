<?php
// Database connection settings
require_once 'config.php';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Table: cbt_student_answers</h1>";

// Get columns for this table
$columns_result = $conn->query("SHOW COLUMNS FROM cbt_student_answers");

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
    echo "Error: " . $conn->error;
}

// Show sample data
echo "<h2>Sample Data (10 rows):</h2>";
$sample_data = $conn->query("SELECT * FROM cbt_student_answers LIMIT 10");

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
    echo "No data found or error: " . $conn->error;
}

$conn->close();
?> 