<?php
// Direct script to add class column and form field
require_once 'backends/config.php';
require_once 'backends/database.php';

// Set up error handling
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Results storage
$messages = [];

// STEP 1: Add 'class' column to students table if it doesn't exist
$checkClassColumn = $conn->query("SHOW COLUMNS FROM students LIKE 'class'");
if ($checkClassColumn && $checkClassColumn->num_rows == 0) {
    $alterSql = "ALTER TABLE students ADD COLUMN class VARCHAR(50) NULL AFTER email";
    if ($conn->query($alterSql)) {
        $messages[] = "Added 'class' column to students table.";
    } else {
        $messages[] = "Error adding 'class' column: " . $conn->error;
    }
} else {
    $messages[] = "Class column already exists in students table.";
}

// STEP 2: Add Class/Level field to registration form fields for both kiddies and college
$regTypes = ['kiddies', 'college'];
foreach ($regTypes as $regType) {
    $checkField = $conn->prepare("SELECT id FROM registration_form_fields WHERE is_active = 1 
                                 AND registration_type = ? 
                                 AND (field_label LIKE '%class%' OR field_label LIKE '%level%')");
    $checkField->bind_param("s", $regType);
    $checkField->execute();
    $fieldResult = $checkField->get_result();
    
    if ($fieldResult->num_rows == 0) {
        // Add the field directly with order 10 (mid-level position)
        $insertSql = "INSERT INTO registration_form_fields 
                     (field_label, field_type, field_order, required, options, registration_type, field_category, is_active) 
                     VALUES ('Class/Level', 'text', 10, 1, '', ?, 'student_info', 1)";
        $insertField = $conn->prepare($insertSql);
        $insertField->bind_param("s", $regType);
        
        if ($insertField->execute()) {
            $messages[] = "Added Class/Level field to $regType registration form.";
        } else {
            $messages[] = "Error adding field to $regType form: " . $conn->error;
        }
    } else {
        $messages[] = "Class/Level field already exists for $regType registration form.";
    }
}

// STEP 3: Update an example student record with a class value
$updateSample = $conn->query("SELECT id FROM students ORDER BY id DESC LIMIT 1");
if ($updateSample && $updateSample->num_rows > 0) {
    $studentId = $updateSample->fetch_assoc()['id'];
    $sampleClass = "Sample Class " . date('Y');
    
    $updateSql = "UPDATE students SET class = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $sampleClass, $studentId);
    
    if ($updateStmt->execute()) {
        $messages[] = "Updated student ID $studentId with class value: $sampleClass";
    }
}

// Output simple results
echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Class Column</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        .message { margin-bottom: 10px; padding: 10px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; }
        .links { margin-top: 30px; }
        .btn { 
            display: inline-block; 
            background-color: #007bff; 
            color: white; 
            padding: 8px 16px; 
            text-decoration: none; 
            border-radius: 4px; 
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <h1>Class/Level Field Added</h1>
    
    <h2>Changes Made:</h2>
    <div>";

foreach ($messages as $message) {
    echo "<div class='message success'>✓ $message</div>";
}

echo "</div>
    
    <div class='links'>
        <h2>Next Steps:</h2>
        <p>The Class/Level field has been added to the system. You can now:</p>
        <div>
            <a href='backends/student/registration/reg_form.php' class='btn'>Go to Registration Form</a>
            <a href='backends/admin/student_details.php?id=1' class='btn'>View Student Details</a>
        </div>
    </div>
</body>
</html>"; 