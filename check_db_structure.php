<?php
// Include database configuration
require_once 'backends/config.php';
require_once 'backends/database.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check the structure of the students table
echo "<h2>Students Table Structure</h2>";
$columnsResult = $conn->query("SHOW COLUMNS FROM students");
if ($columnsResult) {
    echo "<pre>";
    echo "Column Name | Type | Null | Key | Default | Extra\n";
    echo "--------------------------------------------------------------\n";
    while ($column = $columnsResult->fetch_assoc()) {
        echo "{$column['Field']} | {$column['Type']} | {$column['Null']} | {$column['Key']} | {$column['Default']} | {$column['Extra']}\n";
    }
    echo "</pre>";
} else {
    echo "Error getting table structure: " . $conn->error;
}

// Check if class-related columns exist
$classColumns = ['class', 'level', 'grade', 'student_class'];
$foundClassColumns = [];

foreach ($classColumns as $column) {
    $checkResult = $conn->query("SHOW COLUMNS FROM students LIKE '$column'");
    if ($checkResult && $checkResult->num_rows > 0) {
        $foundClassColumns[] = $column;
    }
}

echo "<h2>Class-Related Columns</h2>";
if (!empty($foundClassColumns)) {
    echo "<p>Found columns: " . implode(", ", $foundClassColumns) . "</p>";
} else {
    echo "<p>No class-related columns found in the students table.</p>";
    
    // If no class column exists, suggest an SQL command to add it
    echo "<h3>SQL to Add Class Column:</h3>";
    echo "<pre>";
    echo "ALTER TABLE students ADD COLUMN class VARCHAR(50) NULL AFTER email;";
    echo "</pre>";
}

// Check registration form fields for Class/Level field
echo "<h2>Registration Form Fields for Class/Level</h2>";
$regTypes = ['kiddies', 'college'];

foreach ($regTypes as $regType) {
    echo "<h3>$regType Registration Type</h3>";
    
    $sql = "SELECT * FROM registration_form_fields WHERE is_active = 1 AND registration_type = ? AND (field_label LIKE '%class%' OR field_label LIKE '%level%' OR field_label LIKE '%grade%')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $regType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<pre>";
        echo "ID | Label | Type | Category | Required\n";
        echo "----------------------------------------------\n";
        while ($field = $result->fetch_assoc()) {
            echo "{$field['id']} | {$field['field_label']} | {$field['field_type']} | {$field['field_category']} | {$field['required']}\n";
        }
        echo "</pre>";
    } else {
        echo "<p>No Class/Level field found for $regType registration type.</p>";
    }
}
?> 