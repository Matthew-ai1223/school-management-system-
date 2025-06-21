<?php
require_once 'config.php';
require_once 'database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // First check if the column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM teachers LIKE 'subjects'");
    
    if ($checkColumn->num_rows == 0) {
        // Column doesn't exist, so add it
        $alterQuery = "ALTER TABLE teachers ADD COLUMN subjects TEXT DEFAULT NULL AFTER qualification";
        
        if ($conn->query($alterQuery)) {
            echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>
                <h3>Success!</h3>
                <p>The subjects column has been added to the teachers table.</p>
            </div>";
        } else {
            throw new Exception("Failed to add subjects column: " . $conn->error);
        }
    } else {
        echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px;'>
            <h3>Notice</h3>
            <p>The subjects column already exists in the teachers table.</p>
        </div>";
    }
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>
        <h3>Error!</h3>
        <p>" . $e->getMessage() . "</p>
    </div>";
}
?> 