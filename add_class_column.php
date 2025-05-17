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

// Start with a success status
$success = true;
$messages = [];

// Check if class-related columns exist
$classColumns = ['class', 'level', 'grade', 'student_class'];
$foundClassColumns = [];

foreach ($classColumns as $column) {
    $checkResult = $conn->query("SHOW COLUMNS FROM students LIKE '$column'");
    if ($checkResult && $checkResult->num_rows > 0) {
        $foundClassColumns[] = $column;
    }
}

if (!empty($foundClassColumns)) {
    $messages[] = "Class-related columns already exist: " . implode(", ", $foundClassColumns);
} else {
    // Add 'class' column to the students table
    $alterSql = "ALTER TABLE students ADD COLUMN class VARCHAR(50) NULL AFTER email";
    if ($conn->query($alterSql)) {
        $messages[] = "Successfully added 'class' column to the students table.";
    } else {
        $success = false;
        $messages[] = "Error adding 'class' column: " . $conn->error;
    }
}

// Make sure Class/Level field exists in registration form fields
$regTypes = ['kiddies', 'college'];

foreach ($regTypes as $regType) {
    // Check if the field exists
    $checkSql = "SELECT id FROM registration_form_fields WHERE is_active = 1 
                AND registration_type = ? 
                AND (field_label LIKE '%class%' OR field_label LIKE '%level%' OR field_label LIKE '%grade%')";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $regType);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $messages[] = "Class/Level field already exists for $regType registration.";
    } else {
        // Get the highest field order for proper positioning
        $maxOrderSql = "SELECT MAX(field_order) as max_order FROM registration_form_fields 
                       WHERE is_active = 1 AND registration_type = ? AND field_category = 'student_info'";
        $maxOrderStmt = $conn->prepare($maxOrderSql);
        $maxOrderStmt->bind_param("s", $regType);
        $maxOrderStmt->execute();
        $maxOrderResult = $maxOrderStmt->get_result();
        $maxOrder = ($maxOrderResult->fetch_assoc())['max_order'] ?? 0;
        
        // Add the Class/Level field
        $insertSql = "INSERT INTO registration_form_fields 
                     (field_label, field_type, field_order, required, options, registration_type, field_category, is_active) 
                     VALUES ('Class/Level', 'text', ?, 1, '', ?, 'student_info', 1)";
        $insertStmt = $conn->prepare($insertSql);
        $newOrder = $maxOrder + 1;
        $insertStmt->bind_param("is", $newOrder, $regType);
        
        if ($insertStmt->execute() && $insertStmt->affected_rows > 0) {
            $messages[] = "Successfully added Class/Level field to $regType registration form.";
        } else {
            $success = false;
            $messages[] = "Error adding Class/Level field to $regType registration: " . $conn->error;
        }
    }
}

// Output the results
header('Content-Type: text/html');
echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Class Column</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Database Update Results</h1>
    <div class=\"" . ($success ? 'success' : 'error') . "\">
        <h2>" . ($success ? 'Success!' : 'Error!') . "</h2>
        <ul>";
        
foreach ($messages as $message) {
    echo "<li>$message</li>";
}

echo "</ul>
    </div>
    <p><a href='check_db_structure.php'>View Database Structure</a></p>
</body>
</html>";
?> 