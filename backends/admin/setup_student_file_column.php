<?php
require_once '../config.php';
require_once '../database.php';

try {
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/sql/alter_students_add_file_id.sql');
    
    if ($conn->multi_query($sql)) {
        do {
            // Consume results to allow next query execution
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        echo "File ID column added to students table successfully!\n";
    } else {
        throw new Exception("Error adding file_id column: " . $conn->error);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 