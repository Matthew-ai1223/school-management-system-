<?php
// Simple script to run database updates
echo "Starting database update process...<br>";

// Read the SQL file
$sqlFile = file_get_contents('update_database.sql');
if (!$sqlFile) {
    die("Unable to read update_database.sql file");
}

// Connect to the database
$conn = new mysqli('localhost', 'root', '', 'ace_school_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully!<br>";

// Split the SQL file into individual statements
$sqlStatements = explode(';', $sqlFile);

// Execute each SQL statement
$successCount = 0;
$errorCount = 0;

foreach ($sqlStatements as $sql) {
    $sql = trim($sql);
    if (empty($sql)) continue;
    
    echo "<div style='margin: 5px; padding: 5px; border-bottom: 1px solid #ccc;'>";
    echo "<strong>Executing:</strong> " . htmlspecialchars(substr($sql, 0, 100)) . "...<br>";
    
    try {
        if ($conn->query($sql)) {
            echo "<span style='color: green;'>Success!</span>";
            $successCount++;
        } else {
            echo "<span style='color: red;'>Error: " . $conn->error . "</span>";
            $errorCount++;
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>Exception: " . $e->getMessage() . "</span>";
        $errorCount++;
    }
    
    echo "</div>";
}

echo "<h2>Update Summary</h2>";
echo "Total statements: " . count($sqlStatements) . "<br>";
echo "Successful: $successCount<br>";
echo "Errors: $errorCount<br>";

// Now let's verify that the parent fields exist
echo "<h2>Verifying Parent Fields</h2>";
$parentFields = [
    "father_s_name", "father_s_occupation", "father_s_office_address", "father_s_contact_phone_number_s_",
    "mother_s_name", "mother_s_occupation", "mother_s_office_address", "mother_s_contact_phone_number_s_",
    "guardian_name", "guardian_occupation", "guardian_office_address", "guardian_contact_phone_number", "child_lives_with"
];

echo "<table border='1'><tr><th>Field</th><th>Status</th></tr>";
$allFieldsExist = true;

foreach ($parentFields as $field) {
    $result = $conn->query("SHOW COLUMNS FROM students LIKE '$field'");
    $exists = ($result && $result->num_rows > 0) ? "Exists" : "Missing";
    
    if ($exists == "Missing") $allFieldsExist = false;
    
    echo "<tr><td>$field</td><td style='color: " . ($exists == "Exists" ? "green" : "red") . ";'>$exists</td></tr>";
}
echo "</table>";

if ($allFieldsExist) {
    echo "<p style='color: green; font-weight: bold;'>All parent/guardian fields exist in the database!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Some parent/guardian fields are missing.</p>";
}

$conn->close();
?> 