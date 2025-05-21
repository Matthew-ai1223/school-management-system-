<?php
require_once 'backends/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$sql = "SELECT id, payment_type, amount, payment_method, reference_number FROM payments WHERE payment_type IS NULL OR payment_type = '' LIMIT 20";
$result = $conn->query($sql);

if (!$result) {
    echo "Query failed: " . $conn->error;
    exit;
}

if ($result->num_rows === 0) {
    echo "No payments with empty or NULL payment_type found.";
} else {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n----------------------\n";
    }
} 