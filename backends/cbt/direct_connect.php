<?php
// Define database constants if they're not already defined
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
if (!defined('DB_NAME')) define('DB_NAME', 'ace_model_college');

// Direct connection without using Database class
try {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        echo "Connection failed: " . $conn->connect_error;
    } else {
        echo "Connection successful!<br>";
        
        // Try a simple query
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            echo "Tables in database:<br>";
            while ($row = $result->fetch_row()) {
                echo "- " . $row[0] . "<br>";
            }
        } else {
            echo "Query failed: " . $conn->error;
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 