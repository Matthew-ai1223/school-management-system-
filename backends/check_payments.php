<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

echo "<h1>Payments Table Check</h1>";

// Check table structure
echo "<h2>Table Structure</h2>";
$structureQuery = "DESCRIBE payments";
$structureResult = $conn->query($structureQuery);

if ($structureResult) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structureResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error getting table structure: " . $conn->error;
}

// Check sample data
echo "<h2>Sample Data (Last 5 Records)</h2>";
$dataQuery = "SELECT id, student_id, payment_type, amount, payment_method, status, created_at FROM payments ORDER BY id DESC LIMIT 5";
$dataResult = $conn->query($dataQuery);

if ($dataResult) {
    if ($dataResult->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Student ID</th><th>Payment Type</th><th>Amount</th><th>Method</th><th>Status</th><th>Created At</th></tr>";
        while ($row = $dataResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['student_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['payment_type']) . "</td>";
            echo "<td>" . number_format($row['amount'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($row['payment_method']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No payment records found in the database.</p>";
    }
} else {
    echo "Error getting sample data: " . $conn->error;
}

// Check for any records with empty payment_type
echo "<h2>Records with Empty Payment Type</h2>";
$emptyQuery = "SELECT COUNT(*) as count FROM payments WHERE payment_type IS NULL OR payment_type = ''";
$emptyResult = $conn->query($emptyQuery);

if ($emptyResult) {
    $count = $emptyResult->fetch_assoc()['count'];
    echo "<p>Found {$count} records with empty payment type.</p>";
    
    if ($count > 0) {
        echo "<p><a href='fix_payment_data.php?fix_payment_type=1'>Fix Empty Payment Types</a></p>";
    }
} else {
    echo "Error checking for empty payment types: " . $conn->error;
}

// Close connection
$conn->close();
?> 