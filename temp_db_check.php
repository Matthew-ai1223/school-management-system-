<?php
require_once 'backends/config.php';
require_once 'backends/database.php';

// Connect to database
$db = Database::getInstance();
$conn = $db->getConnection();

// Get table structure
$result = $conn->query("SHOW COLUMNS FROM cbt_student_answers");

echo "<h2>cbt_student_answers Table Structure</h2>";
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}

echo "</table>";
?> 