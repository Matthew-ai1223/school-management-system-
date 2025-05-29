<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

$db = Database::getInstance()->getConnection();

$admin_data = [
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => password_hash('your_chosen_password', PASSWORD_DEFAULT), // Replace 'your_chosen_password' with your desired password
    'role' => 'super_admin',
    'department' => 'Administration',
    'phone' => '1234567890',
    'gender' => 'male'
];

try {
    $query = "INSERT INTO users (name, email, password, role, department, phone, gender) 
              VALUES (:name, :email, :password, :role, :department, :phone, :gender)";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute($admin_data);

    if ($result) {
        echo "Admin user created successfully!";
    }
} catch (PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage();
} 