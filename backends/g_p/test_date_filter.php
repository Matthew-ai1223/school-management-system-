<?php
require_once 'config.php';

echo "<h2>Date Filter Test</h2>";

try {
    $pdo = getDBConnection();
    
    // Test 1: Check table structure
    echo "<h3>1. Table Structure</h3>";
    $sql = "DESCRIBE payments";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test 2: Check sample data
    echo "<h3>2. Sample Data (Last 5 payments)</h3>";
    $sql = "SELECT id, payment_type, student_name, payment_date, created_at, verification_status FROM payments ORDER BY created_at DESC LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($payments)) {
        echo "<p>No payments found in database.</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Type</th><th>Student</th><th>Payment Date</th><th>Created At</th><th>Status</th></tr>";
        foreach ($payments as $payment) {
            echo "<tr>";
            echo "<td>" . $payment['id'] . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "<td>" . $payment['student_name'] . "</td>";
            echo "<td>" . $payment['payment_date'] . "</td>";
            echo "<td>" . $payment['created_at'] . "</td>";
            echo "<td>" . $payment['verification_status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 3: Test date filtering
    echo "<h3>3. Date Filter Test</h3>";
    
    $test_start_date = date('Y-m-d', strtotime('-7 days'));
    $test_end_date = date('Y-m-d');
    
    echo "<p>Testing date range: $test_start_date to $test_end_date</p>";
    
    $sql = "SELECT COUNT(*) as count FROM payments WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$test_start_date, $test_end_date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Payments in last 7 days: " . $result['count'] . "</p>";
    
    // Test 4: Test with payment_date field
    echo "<h3>4. Payment Date Field Test</h3>";
    
    $sql = "SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) >= ? AND DATE(payment_date) <= ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$test_start_date, $test_end_date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Payments with payment_date in last 7 days: " . $result['count'] . "</p>";
    
    // Test 5: Show all unique dates
    echo "<h3>5. All Unique Dates in Database</h3>";
    
    $sql = "SELECT DISTINCT DATE(created_at) as date, COUNT(*) as count FROM payments GROUP BY DATE(created_at) ORDER BY date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Date</th><th>Count</th></tr>";
    foreach ($dates as $date) {
        echo "<tr>";
        echo "<td>" . $date['date'] . "</td>";
        echo "<td>" . $date['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style> 