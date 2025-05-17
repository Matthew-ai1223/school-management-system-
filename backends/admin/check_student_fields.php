<?php
// Simple script to check student fields in the database
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all columns from students table
echo "<h2>Students Table Structure</h2>";
$columns_query = "SHOW COLUMNS FROM students";
$columns_result = $conn->query($columns_query);

if ($columns_result) {
    echo "<table border='1'><tr><th>Column Name</th><th>Type</th></tr>";
    while ($col = $columns_result->fetch_assoc()) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error fetching columns: " . $conn->error;
}

// Get a sample student record to check field values
echo "<h2>Sample Student Data</h2>";
$sample_query = "SELECT * FROM students LIMIT 1";
$sample_result = $conn->query($sample_query);

if ($sample_result && $sample_result->num_rows > 0) {
    $student = $sample_result->fetch_assoc();
    
    echo "<h3>Parent Data Fields</h3>";
    echo "<table border='1'><tr><th>Field</th><th>Value</th></tr>";
    
    // Parent fields to check
    $parent_fields = [
        'father_s_name', 'father_s_occupation', 'father_s_office_address', 'father_s_contact_phone_number_s_', 
        'mother_s_name', 'mother_s_occupation', 'mother_s_office_address', 'mother_s_contact_phone_number_s_',
        'guardian_name', 'guardian_occupation', 'guardian_office_address', 'guardian_contact_phone_number', 'child_lives_with'
    ];
    
    foreach ($parent_fields as $field) {
        echo "<tr><td>$field</td><td>" . (isset($student[$field]) ? htmlspecialchars($student[$field]) : "NOT SET") . "</td></tr>";
    }
    echo "</table>";
    
    // Show all fields with values for thoroughness
    echo "<h3>All Student Fields</h3>";
    echo "<table border='1'><tr><th>Field</th><th>Value</th></tr>";
    foreach ($student as $field => $value) {
        echo "<tr><td>$field</td><td>" . (is_null($value) ? "NULL" : htmlspecialchars($value)) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "No student records found";
}
?> 