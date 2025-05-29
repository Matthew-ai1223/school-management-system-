<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Add required columns if they don't exist
    $columns = [
        'registration_number' => 'VARCHAR(50)',
        'class' => 'VARCHAR(50)',
        'status' => "ENUM('pending', 'active', 'registered', 'inactive') DEFAULT 'pending'",
        'first_name' => 'VARCHAR(50)',
        'last_name' => 'VARCHAR(50)',
        'email' => 'VARCHAR(100)',
        'is_active' => 'BOOLEAN DEFAULT TRUE',
        'expiration_date' => 'DATE NULL'
    ];
    
    foreach ($columns as $column => $definition) {
        try {
            $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS $column $definition");
            echo "Column $column added or already exists.<br>";
        } catch (PDOException $e) {
            echo "Error adding column $column: " . $e->getMessage() . "<br>";
        }
    }
    
    // Create indexes for better performance
    $indexes = [
        'registration_number' => 'registration_number',
        'class' => 'class',
        'status' => 'status'
    ];
    
    foreach ($indexes as $name => $column) {
        try {
            $conn->query("CREATE INDEX IF NOT EXISTS idx_$name ON students ($column)");
            echo "Index idx_$name created or already exists.<br>";
        } catch (PDOException $e) {
            echo "Error creating index idx_$name: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "Database setup completed successfully!";
    
} catch (Exception $e) {
    echo "Error during database setup: " . $e->getMessage();
}
?> 