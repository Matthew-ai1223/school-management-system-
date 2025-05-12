<?php
require_once '../config.php';
require_once '../database.php';

try {
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/sql/create_files_table.sql');
    
    if ($conn->multi_query($sql)) {
        do {
            // Consume results to allow next query execution
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        echo "Files table created successfully!\n";
    } else {
        throw new Exception("Error creating files table: " . $conn->error);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 