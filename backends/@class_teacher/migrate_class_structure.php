<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

echo "<h2>Class Teachers Table Migration</h2>";

// Step 1: Check if class_teachers table exists and has class_id
$classTeachersTable = false;
$hasClassId = false;
$hasClassName = false;

$tablesResult = $conn->query("SHOW TABLES LIKE 'class_teachers'");
if ($tablesResult->num_rows > 0) {
    $classTeachersTable = true;
    
    // Check column structure
    $columnsResult = $conn->query("SHOW COLUMNS FROM class_teachers");
    while ($column = $columnsResult->fetch_assoc()) {
        if ($column['Field'] == 'class_id') {
            $hasClassId = true;
        }
        if ($column['Field'] == 'class_name') {
            $hasClassName = true;
        }
    }
}

if (!$classTeachersTable) {
    echo "<p>class_teachers table doesn't exist. Will be created by create_tables.php</p>";
} else {
    echo "<p>class_teachers table exists.</p>";
    echo "<p>Has class_id column: " . ($hasClassId ? "Yes" : "No") . "</p>";
    echo "<p>Has class_name column: " . ($hasClassName ? "Yes" : "No") . "</p>";
    
    // Migration needed: Add class_name column and migrate data
    if ($hasClassId && !$hasClassName) {
        echo "<p>Migration needed: Adding class_name column and migrating data from class_id references.</p>";
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Add class_name column
            $addColumnSql = "ALTER TABLE class_teachers ADD COLUMN class_name VARCHAR(100)";
            if ($conn->query($addColumnSql)) {
                echo "<p>Added class_name column to class_teachers table.</p>";
            } else {
                throw new Exception("Failed to add class_name column: " . $conn->error);
            }
            
            // Migrate data from classes table to class_name
            $migrateSql = "UPDATE class_teachers ct 
                         JOIN classes c ON ct.class_id = c.id
                         SET ct.class_name = CONCAT(c.name, ' ', COALESCE(c.section, ''))";
            
            if ($conn->query($migrateSql)) {
                $rows = $conn->affected_rows;
                echo "<p>Migrated data for $rows class teachers: Copied class information from classes table to class_name.</p>";
            } else {
                // If this fails, maybe classes table doesn't exist or has different structure
                echo "<p>Warning: Could not migrate data from classes table: " . $conn->error . "</p>";
                echo "<p>Will attempt to use data from students table instead.</p>";
                
                // Try an alternative approach by looking up class information in the students table
                $getClassTeachers = "SELECT id, class_id FROM class_teachers";
                $result = $conn->query($getClassTeachers);
                
                $updated = 0;
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $ctId = $row['id'];
                        $classId = $row['class_id'];
                        
                        // Try to find a student in this class to get the class name
                        $studentSql = "SELECT DISTINCT class FROM students WHERE class_id = ?";
                        $stmt = $conn->prepare($studentSql);
                        $stmt->bind_param("i", $classId);
                        $stmt->execute();
                        $studentResult = $stmt->get_result();
                        
                        if ($studentResult->num_rows > 0) {
                            $student = $studentResult->fetch_assoc();
                            $className = $student['class'];
                            
                            // Update the class_teachers record
                            $updateSql = "UPDATE class_teachers SET class_name = ? WHERE id = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            $updateStmt->bind_param("si", $className, $ctId);
                            if ($updateStmt->execute()) {
                                $updated++;
                            }
                        }
                    }
                    
                    echo "<p>Updated $updated class teacher records from students table data.</p>";
                }
            }
            
            $conn->commit();
            echo "<p>Migration completed successfully!</p>";
            
        } catch (Exception $e) {
            $conn->rollback();
            echo "<p>Error during migration: " . $e->getMessage() . "</p>";
        }
    }
    else if (!$hasClassId && $hasClassName) {
        echo "<p>Table already has the new structure with class_name column.</p>";
    }
    else if ($hasClassId && $hasClassName) {
        echo "<p>Table has both old and new structures. No migration needed.</p>";
    }
    else {
        echo "<p>Table has an unexpected structure. Please run create_tables.php to set up the correct structure.</p>";
    }
}

// Check if we need to fix the login.php query
echo "<h2>Checking Active Teachers Query in login.php</h2>";

$loginFile = file_get_contents(__DIR__ . '/login.php');
$needsCodeFix = false;

// Check if the file still uses the old query
if (strpos($loginFile, "JOIN classes c ON ct.class_id = c.id") !== false) {
    $needsCodeFix = true;
    echo "<p>login.php still contains references to classes table that need to be updated.</p>";
} else {
    echo "<p>login.php appears to have the correct query structure.</p>";
}

echo "<h2>Next Steps</h2>";
echo "<p>1. <a href='create_tables.php'>Run create_tables.php</a> to ensure all tables have the correct structure</p>";
echo "<p>2. <a href='login.php'>Go back to login page</a></p>";
?> 