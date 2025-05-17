<?php
// Script to fix parent data display issues
echo "<h1>Parent Data Fix Script</h1>";

// Step 1: Make sure the table has all required fields
echo "<h2>Step 1: Verifying Database Fields</h2>";

try {
    // Connect directly to MySQL
    $conn = mysqli_connect('localhost', 'root', '', 'ace_school_system');
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    
    echo "<p>Database connection successful</p>";
    
    // Check if parent fields exist
    $sql = "SHOW COLUMNS FROM students WHERE Field IN (
        'father_s_name', 'father_s_occupation', 'father_s_office_address', 'father_s_contact_phone_number_s_', 
        'mother_s_name', 'mother_s_occupation', 'mother_s_office_address', 'mother_s_contact_phone_number_s_'
    )";
    
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($conn));
    }
    
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    
    echo "<p>Found " . count($columns) . " parent fields: " . implode(", ", $columns) . "</p>";
    
    // Create missing fields if needed
    if (count($columns) < 8) {
        echo "<p>Adding missing parent fields...</p>";
        
        $alter_sql = "ALTER TABLE `students`
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
        
        if (mysqli_query($conn, $alter_sql)) {
            echo "<p style='color: green;'>Successfully added missing columns</p>";
        } else {
            throw new Exception("Error adding columns: " . mysqli_error($conn));
        }
    }
    
    // Step 2: Insert test data
    echo "<h2>Step 2: Adding Test Parent Data</h2>";
    
    // Get first student
    $student_sql = "SELECT id, first_name, last_name FROM students LIMIT 1";
    $student_result = mysqli_query($conn, $student_sql);
    
    if (!$student_result || mysqli_num_rows($student_result) == 0) {
        echo "<p style='color: red;'>No students found in the database.</p>";
    } else {
        $student = mysqli_fetch_assoc($student_result);
        
        echo "<p>Adding parent data for student: " . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . " (ID: " . $student['id'] . ")</p>";
        
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
            WHERE id = " . $student['id'];
            
        if (mysqli_query($conn, $update_sql)) {
            echo "<p style='color: green;'>Successfully updated parent data!</p>";
            
            // Verify the data
            $verify_sql = "SELECT * FROM students WHERE id = " . $student['id'];
            $verify_result = mysqli_query($conn, $verify_sql);
            $updated_student = mysqli_fetch_assoc($verify_result);
            
            echo "<h3>Updated Parent Fields:</h3>";
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
            throw new Exception("Error updating parent data: " . mysqli_error($conn));
        }
    }
    
    echo "<h2>Next Steps</h2>";
    echo "<p>1. <a href='backends/admin/students.php'>Go to students.php</a> to verify that parent data appears</p>";
    echo "<p>2. <a href='backends/admin/student_details.php?id=" . ($student['id'] ?? 1) . "'>View Student Details</a> to check if parent data shows correctly</p>";
    
} catch (Exception $e) {
    echo "<div style='color: red; border: 1px solid red; padding: 10px; margin: 10px 0;'>";
    echo "<h3>Error:</h3><p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<!-- Add automatic page reload to admin pages -->
<script>
function openLink(url) {
    window.open(url, '_blank');
}
</script> 