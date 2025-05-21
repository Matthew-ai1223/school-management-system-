<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check teachers table structure
echo "Checking teachers table structure...\n";
$result = $conn->query("DESCRIBE teachers");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Column: " . $row['Field'] . " - Type: " . $row['Type'] . "\n";
    }
} else {
    echo "Error checking table structure: " . $conn->error . "\n";
}

// Get sample teacher data
echo "\nSample teacher data:\n";
$result = $conn->query("SELECT id, first_name, last_name, employee_id FROM teachers LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . " - Name: " . $row['first_name'] . " " . $row['last_name'] . " - Employee ID: " . ($row['employee_id'] ?? 'NULL') . "\n";
        }
    } else {
        echo "No teachers found.\n";
    }
} else {
    echo "Error querying teacher data: " . $conn->error . "\n";
}

// Check class_teachers table
echo "\nChecking class_teachers table structure...\n";
$result = $conn->query("DESCRIBE class_teachers");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Column: " . $row['Field'] . " - Type: " . $row['Type'] . "\n";
    }
} else {
    echo "Error checking table structure: " . $conn->error . "\n";
}

// Get sample class_teachers data
echo "\nSample class_teachers data:\n";
$result = $conn->query("SELECT ct.id, t.first_name, t.last_name, t.employee_id, c.name as class_name, ct.can_manage_cbt 
                       FROM class_teachers ct 
                       LEFT JOIN teachers t ON ct.teacher_id = t.id 
                       LEFT JOIN classes c ON ct.class_id = c.id 
                       LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . " - Name: " . $row['first_name'] . " " . $row['last_name'] . 
                 " - Employee ID: " . ($row['employee_id'] ?? 'NULL') . 
                 " - Class: " . ($row['class_name'] ?? 'NULL') . 
                 " - Can Manage CBT: " . ($row['can_manage_cbt'] ?? 'NULL') . "\n";
        }
    } else {
        echo "No class teachers found.\n";
    }
} else {
    echo "Error querying class_teachers data: " . $conn->error . "\n";
}

?> 