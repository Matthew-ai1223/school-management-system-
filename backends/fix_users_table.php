<?php
require_once 'config.php';
require_once 'database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Modify the status column to accept 'pending' status
    $alterQuery = "ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'pending') DEFAULT 'active'";
    
    if ($conn->query($alterQuery)) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>
            <h3>Success!</h3>
            <p>The users table has been updated to accept 'pending' status.</p>
        </div>";
    } else {
        throw new Exception("Failed to modify status column: " . $conn->error);
    }
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>
        <h3>Error!</h3>
        <p>" . $e->getMessage() . "</p>
    </div>";
}
?> 