<?php
// Get database connection using the existing system
require_once 'backends/config.php';
require_once 'backends/database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

echo "<h1>Parent Data Update Script</h1>";

// First, let's check if the parent columns exist
$columns_query = "SHOW COLUMNS FROM students WHERE Field IN (
    'father_s_name', 'father_s_occupation', 'father_s_office_address', 'father_s_contact_phone_number_s_', 
    'mother_s_name', 'mother_s_occupation', 'mother_s_office_address', 'mother_s_contact_phone_number_s_'
)";
$columns_result = $conn->query($columns_query);
$existing_columns = [];

if ($columns_result) {
    while ($column = $columns_result->fetch_assoc()) {
        $existing_columns[] = $column['Field'];
    }
}

echo "<h2>Checking if Parent Fields Exist</h2>";
echo "<p>Found " . count($existing_columns) . " parent columns: " . implode(", ", $existing_columns) . "</p>";

// If we don't have enough parent columns, create them
if (count($existing_columns) < 8) {
    echo "<h3>Adding Missing Parent Fields</h3>";
    
    $add_columns_sql = "ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `father_s_name` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `father_s_occupation` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `father_s_office_address` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `father_s_contact_phone_number_s_` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `mother_s_name` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `mother_s_occupation` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `mother_s_office_address` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `mother_s_contact_phone_number_s_` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `guardian_name` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `guardian_occupation` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `guardian_office_address` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `guardian_contact_phone_number` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `child_lives_with` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `allergies` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `blood_group` VARCHAR(10) NULL,
    ADD COLUMN IF NOT EXISTS `genotype` VARCHAR(10) NULL";
    
    if ($conn->query($add_columns_sql)) {
        echo "<p style='color: green;'>Successfully added missing parent columns</p>";
    } else {
        echo "<p style='color: red;'>Error adding columns: " . $conn->error . "</p>";
    }
}

// Get a sample student to update
$get_student_sql = "SELECT id, first_name, last_name FROM students LIMIT 1";
$student_result = $conn->query($get_student_sql);

if ($student_result && $student_result->num_rows > 0) {
    $student = $student_result->fetch_assoc();
    
    echo "<h2>Updating Parent Data for Student: " . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</h2>";
    
    // Set parent data for this student
    $update_sql = "UPDATE students SET 
        father_s_name = 'John Doe',
        father_s_occupation = 'Software Engineer',
        father_s_office_address = '123 Tech Street, Silicon Valley',
        father_s_contact_phone_number_s_ = '+234 123 456 7890',
        mother_s_name = 'Jane Doe',
        mother_s_occupation = 'Medical Doctor',
        mother_s_office_address = '456 Health Avenue, Medical District',
        mother_s_contact_phone_number_s_ = '+234 987 654 3210',
        guardian_name = 'Robert Smith',
        guardian_occupation = 'Teacher',
        guardian_office_address = '789 Education Road, School Zone',
        guardian_contact_phone_number = '+234 555 555 5555',
        child_lives_with = 'Both Parents'
        WHERE id = ?";
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('i', $student['id']);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>Successfully updated parent data!</p>";
    } else {
        echo "<p style='color: red;'>Error updating parent data: " . $stmt->error . "</p>";
    }
    
    // Now fetch the student again to see if the update worked
    $check_sql = "SELECT * FROM students WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $student['id']);
    $check_stmt->execute();
    $updated_student = $check_stmt->get_result()->fetch_assoc();
    
    echo "<h3>Verification of Parent Fields</h3>";
    echo "<table border='1'><tr><th>Field</th><th>Value</th></tr>";
    $parent_fields = [
        'father_s_name', 'father_s_occupation', 'father_s_office_address', 'father_s_contact_phone_number_s_',
        'mother_s_name', 'mother_s_occupation', 'mother_s_office_address', 'mother_s_contact_phone_number_s_',
        'guardian_name', 'guardian_occupation', 'guardian_office_address', 'guardian_contact_phone_number', 'child_lives_with'
    ];
    
    foreach ($parent_fields as $field) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($field) . "</td>";
        echo "<td>" . htmlspecialchars($updated_student[$field] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    echo "<p style='color: red;'>No students found in the database.</p>";
}

echo "<h2>Next Steps</h2>";
echo "<p>1. <a href='backends/admin/students.php'>Go to students.php</a> to verify that parent data appears</p>";
echo "<p>2. <a href='backends/admin/student_details.php?id=1'>Go to student_details.php</a> to verify parent details</p>";
?> 