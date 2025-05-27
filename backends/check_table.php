<?php
// Database connection settings
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ace_model_college';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Database Table Structure</h1>";

// Get the list of tables
$tables_result = $conn->query("SHOW TABLES");

if ($tables_result) {
    while ($table_row = $tables_result->fetch_row()) {
        $table_name = $table_row[0];
        echo "<h2>Table: $table_name</h2>";
        
        // Get columns for this table
        $columns_result = $conn->query("SHOW COLUMNS FROM $table_name");
        
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
        }
        
        echo "<hr>";
    }
}

$conn->close();
?> 