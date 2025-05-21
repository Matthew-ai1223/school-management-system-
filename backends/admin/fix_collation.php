<?php
require_once '../config.php';
require_once '../database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Function to execute a query and show result
function executeQuery($conn, $query, $description) {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>";
    echo "<h3>$description</h3>";
    echo "<pre>$query</pre>";
    
    if ($conn->query($query)) {
        echo "<div style='color: green; font-weight: bold;'>Success!</div>";
    } else {
        echo "<div style='color: red; font-weight: bold;'>Error: " . $conn->error . "</div>";
    }
    
    echo "</div>";
}

// First, check database character set and collation
$dbInfoQuery = "SELECT @@character_set_database, @@collation_database";
$result = $conn->query($dbInfoQuery);
$dbInfo = $result->fetch_row();
echo "<h2>Database Information</h2>";
echo "<p>Database Character Set: {$dbInfo[0]}</p>";
echo "<p>Database Collation: {$dbInfo[1]}</p>";

// Get list of tables
$tablesQuery = "SHOW TABLES";
$tablesResult = $conn->query($tablesQuery);
$tables = [];

echo "<h2>Collation Fixes</h2>";

// We'll standardize on utf8mb4_unicode_ci
$targetCollation = "utf8mb4_unicode_ci";

// 1. First, get table structure
while ($table = $tablesResult->fetch_array(MYSQLI_NUM)) {
    $tableName = $table[0];
    
    // Get table collation
    $tableInfoQuery = "SHOW TABLE STATUS WHERE Name = '$tableName'";
    $tableInfoResult = $conn->query($tableInfoQuery);
    $tableInfo = $tableInfoResult->fetch_assoc();
    $tableCollation = $tableInfo['Collation'];
    
    echo "<h3>Table: $tableName (Collation: $tableCollation)</h3>";
    
    // Get column information
    $columnsQuery = "SHOW FULL COLUMNS FROM `$tableName`";
    $columnsResult = $conn->query($columnsQuery);
    
    echo "<ul>";
    while ($column = $columnsResult->fetch_assoc()) {
        $columnName = $column['Field'];
        $columnCollation = $column['Collation'];
        $columnType = $column['Type'];
        
        echo "<li>$columnName - $columnType";
        if ($columnCollation) {
            echo " (Collation: $columnCollation)";
            if ($columnCollation != $targetCollation && $columnCollation !== null) {
                // String column with different collation
                $alterColumnQuery = "ALTER TABLE `$tableName` MODIFY `$columnName` $columnType CHARACTER SET utf8mb4 COLLATE $targetCollation";
                executeQuery($conn, $alterColumnQuery, "Converting column collation");
            }
        }
        echo "</li>";
    }
    echo "</ul>";
    
    // If table collation doesn't match target, convert it
    if ($tableCollation != $targetCollation) {
        $alterTableQuery = "ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE $targetCollation";
        executeQuery($conn, $alterTableQuery, "Converting table collation");
    }
}

// Special fix for the specific query in class_teachers.php line 174
echo "<h2>Direct Fix for Query on Line 174</h2>";
$specificFix = "
-- Make sure the students.class and class_teachers.class_name use the same collation
ALTER TABLE students MODIFY class VARCHAR(50) CHARACTER SET utf8mb4 COLLATE $targetCollation;
ALTER TABLE class_teachers MODIFY class_name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE $targetCollation;
";

echo "<pre>$specificFix</pre>";
$statements = explode(";", $specificFix);
foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement) || strpos($statement, '--') === 0) continue;
    
    if ($conn->query($statement)) {
        echo "<div style='color: green; font-weight: bold;'>Success: $statement</div>";
    } else {
        echo "<div style='color: red; font-weight: bold;'>Error: " . $conn->error . "</div>";
    }
}

echo "<div style='margin-top: 20px; padding: 10px; background-color: #d4edda; color: #155724;'>
    <h3>Collation Fix Complete!</h3>
    <p>All tables and columns should now be using the $targetCollation collation.</p>
    <p><a href='class_teachers.php' class='btn btn-primary'>Return to Class Teachers Page</a></p>
</div>";
?> 