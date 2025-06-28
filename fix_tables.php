<?php
require_once 'backends/lesson/confg.php';

echo "<h2>Fixing Table Structure</h2>";

try {
    // Array of tables to update
    $tables = ['morning_students', 'afternoon_students'];
    
    foreach ($tables as $table) {
        echo "<h3>Updating table: $table</h3>";
        
        // Modify columns to allow NULL values
        $alter_queries = [
            "ALTER TABLE $table MODIFY email VARCHAR(255) NULL",
            "ALTER TABLE $table MODIFY phone VARCHAR(20) NULL",
            "ALTER TABLE $table MODIFY password VARCHAR(255) NULL",
            "ALTER TABLE $table MODIFY photo VARCHAR(255) NULL",
            "ALTER TABLE $table MODIFY parent_name VARCHAR(255) NULL",
            "ALTER TABLE $table MODIFY parent_phone VARCHAR(20) NULL",
            "ALTER TABLE $table MODIFY address TEXT NULL"
        ];
        
        // Add class and school for afternoon students
        if ($table === 'afternoon_students') {
            $alter_queries[] = "ALTER TABLE $table MODIFY class VARCHAR(50) NULL";
            $alter_queries[] = "ALTER TABLE $table MODIFY school VARCHAR(255) NULL";
        }
        
        foreach ($alter_queries as $query) {
            if ($conn->query($query)) {
                echo "✓ " . htmlspecialchars($query) . "<br>";
            } else {
                echo "✗ Error executing: " . htmlspecialchars($query) . " - " . $conn->error . "<br>";
            }
        }
        
        echo "<hr>";
    }
    
    echo "<h3>Table structure update completed!</h3>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 