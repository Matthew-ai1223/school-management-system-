<?php
/**
 * This script fixes the database by ensuring both admission_number and registration_number
 * columns exist in the students table.
 */
require_once 'config.php';
require_once 'database.php';
require_once 'utils.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if columns exist
$hasAdmissionNumber = columnExists($conn, 'students', 'admission_number');
$hasRegistrationNumber = columnExists($conn, 'students', 'registration_number');

echo "<h1>Database Column Fix</h1>";
echo "<p>Checking student table columns...</p>";

echo "<ul>";
echo "<li>admission_number column exists: " . ($hasAdmissionNumber ? 'Yes' : 'No') . "</li>";
echo "<li>registration_number column exists: " . ($hasRegistrationNumber ? 'Yes' : 'No') . "</li>";
echo "</ul>";

// Apply fixes if needed
if ($hasAdmissionNumber && !$hasRegistrationNumber) {
    echo "<p>Adding registration_number column...</p>";
    $result = $conn->query("ALTER TABLE `students` ADD `registration_number` VARCHAR(50) NULL AFTER `admission_number`");
    if ($result) {
        echo "<p>Successfully added registration_number column.</p>";
        echo "<p>Copying data from admission_number to registration_number...</p>";
        $result = $conn->query("UPDATE `students` SET `registration_number` = `admission_number`");
        if ($result) {
            echo "<p>Successfully copied data.</p>";
        } else {
            echo "<p>Error copying data: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>Error adding column: " . $conn->error . "</p>";
    }
} 
else if (!$hasAdmissionNumber && $hasRegistrationNumber) {
    echo "<p>Adding admission_number column...</p>";
    $result = $conn->query("ALTER TABLE `students` ADD `admission_number` VARCHAR(50) NULL AFTER `id`");
    if ($result) {
        echo "<p>Successfully added admission_number column.</p>";
        echo "<p>Copying data from registration_number to admission_number...</p>";
        $result = $conn->query("UPDATE `students` SET `admission_number` = `registration_number`");
        if ($result) {
            echo "<p>Successfully copied data.</p>";
        } else {
            echo "<p>Error copying data: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>Error adding column: " . $conn->error . "</p>";
    }
}
else if ($hasAdmissionNumber && $hasRegistrationNumber) {
    echo "<p>Both columns already exist. Ensuring data is consistent...</p>";
    
    // Check for NULL values in either column
    $result = $conn->query("SELECT COUNT(*) as count FROM `students` WHERE `admission_number` IS NULL AND `registration_number` IS NOT NULL");
    $row = $result->fetch_assoc();
    $admissionNulls = $row['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM `students` WHERE `registration_number` IS NULL AND `admission_number` IS NOT NULL");
    $row = $result->fetch_assoc();
    $registrationNulls = $row['count'];
    
    echo "<p>Records with NULL admission_number: $admissionNulls</p>";
    echo "<p>Records with NULL registration_number: $registrationNulls</p>";
    
    if ($admissionNulls > 0) {
        echo "<p>Copying data from registration_number to admission_number where needed...</p>";
        $result = $conn->query("UPDATE `students` SET `admission_number` = `registration_number` WHERE `admission_number` IS NULL AND `registration_number` IS NOT NULL");
        if ($result) {
            echo "<p>Successfully updated admission_number fields.</p>";
        } else {
            echo "<p>Error updating admission_number: " . $conn->error . "</p>";
        }
    }
    
    if ($registrationNulls > 0) {
        echo "<p>Copying data from admission_number to registration_number where needed...</p>";
        $result = $conn->query("UPDATE `students` SET `registration_number` = `admission_number` WHERE `registration_number` IS NULL AND `admission_number` IS NOT NULL");
        if ($result) {
            echo "<p>Successfully updated registration_number fields.</p>";
        } else {
            echo "<p>Error updating registration_number: " . $conn->error . "</p>";
        }
    }
}
else {
    // Neither column exists - this is a serious issue!
    echo "<p>ERROR: Neither admission_number nor registration_number columns exist in the students table!</p>";
    echo "<p>This indicates a serious database issue that needs manual attention.</p>";
}

echo "<h2>Fix Complete</h2>";
echo "<p>Click <a href='admin/dashboard.php'>here</a> to return to the admin dashboard.</p>";
echo "<p>Click <a href='@class_teacher/dashboard.php'>here</a> to return to the class teacher dashboard.</p>";
?> 