<?php
require_once '../config.php';
require_once '../database.php';

$db = Database::getInstance();
$mysqli = $db->getConnection();

try {
    // Create users table if it doesn't exist
    $query = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        role ENUM('admin', 'staff') NOT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$mysqli->query($query)) {
        throw new Exception("Error creating users table: " . $mysqli->error);
    }
    
    // Check if admin user already exists
    $result = $mysqli->query("SELECT id FROM users WHERE username = 'ace'");
    if ($result && $result->num_rows > 0) {
        echo "Admin user already exists!";
        exit;
    }
    
    // Create default admin account
    // Default credentials:
    // Username: ace
    // Password: ace
    $username = 'ace';
    $password = password_hash('ace', PASSWORD_DEFAULT);
    $first_name = 'System';
    $last_name = 'Administrator';
    $role = 'admin';
    
    $query = "INSERT INTO users (username, password, first_name, last_name, role) 
              VALUES (?, ?, ?, ?, ?)";
              
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('sssss', $username, $password, $first_name, $last_name, $role);
    
    if ($stmt->execute()) {
        echo "Success! Admin account has been created.<br><br>";
        echo "You can now login with:<br>";
        echo "Username: ace<br>";
        echo "Password: ace<br><br>";
        echo "<strong>Please change your password after first login!</strong><br><br>";
        echo "<a href='login.php'>Go to Login Page</a>";
    } else {
        throw new Exception("Error creating admin account: " . $stmt->error);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 