<?php
require_once '../config.php';
require_once '../database.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

echo "Checking if column exists...\n";
$result = $conn->query("SHOW COLUMNS FROM class_teachers LIKE 'can_manage_cbt'");

if($result->num_rows == 0) {
    echo "Adding column can_manage_cbt to class_teachers table...\n";
    if($conn->query("ALTER TABLE class_teachers ADD COLUMN can_manage_cbt TINYINT(1) NOT NULL DEFAULT 0")) {
        echo "Column added successfully.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column already exists.\n";
}

// Update some teachers to have CBT access for demonstration
echo "Granting CBT access to some teachers...\n";
if($conn->query("UPDATE class_teachers SET can_manage_cbt = 1 WHERE id IN (SELECT id FROM class_teachers LIMIT 3)")) {
    echo "Teachers updated successfully.\n";
} else {
    echo "Error updating teachers: " . $conn->error . "\n";
}

echo "Done.\n";
?> 