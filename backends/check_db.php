<?php
// Database credentials
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ace_model_college');

// Connect to MySQL database
$mysqli = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get column names for the table
$result = $mysqli->query("SHOW COLUMNS FROM cbt_student_answers");

echo "cbt_student_answers Table Structure:\n\n";
echo "Field\t\tType\t\tNull\tKey\tDefault\tExtra\n";
echo "--------------------------------------------------------------\n";

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\t\t" . $row['Type'] . "\t\t" . $row['Null'] . "\t" . $row['Key'] . "\t" . $row['Default'] . "\t" . $row['Extra'] . "\n";
    }
} else {
    echo "Error: " . $mysqli->error;
}

$mysqli->close();
?> 