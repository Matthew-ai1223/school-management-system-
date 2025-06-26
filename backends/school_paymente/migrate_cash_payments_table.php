<?php
require_once 'ctrl/db_config.php';

echo "<h2>Cash Payments Table Migration</h2>";

try {
    // Check if columns exist
    $check_columns = [
        'approval_status' => "SHOW COLUMNS FROM cash_payments LIKE 'approval_status'",
        'approver_id' => "SHOW COLUMNS FROM cash_payments LIKE 'approver_id'",
        'approver_name' => "SHOW COLUMNS FROM cash_payments LIKE 'approver_name'",
        'approval_date' => "SHOW COLUMNS FROM cash_payments LIKE 'approval_date'"
    ];

    $missing_columns = [];

    foreach ($check_columns as $column => $query) {
        $result = $conn->query($query);
        if ($result->num_rows === 0) {
            $missing_columns[] = $column;
        }
    }

    if (empty($missing_columns)) {
        echo "<p style='color: green;'>✅ All required columns already exist in cash_payments table.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Missing columns: " . implode(', ', $missing_columns) . "</p>";
        
        // Add missing columns
        $alter_queries = [
            'approval_status' => "ALTER TABLE cash_payments ADD COLUMN approval_status ENUM('under_review', 'approved', 'rejected') DEFAULT 'under_review' AFTER payment_status",
            'approver_id' => "ALTER TABLE cash_payments ADD COLUMN approver_id VARCHAR(50) NULL AFTER approval_status",
            'approver_name' => "ALTER TABLE cash_payments ADD COLUMN approver_name VARCHAR(100) NULL AFTER approver_id",
            'approval_date' => "ALTER TABLE cash_payments ADD COLUMN approval_date TIMESTAMP NULL AFTER approver_name"
        ];

        foreach ($missing_columns as $column) {
            if (isset($alter_queries[$column])) {
                $query = $alter_queries[$column];
                echo "<p>Executing: $query</p>";
                
                if ($conn->query($query)) {
                    echo "<p style='color: green;'>✅ Successfully added column: $column</p>";
                } else {
                    echo "<p style='color: red;'>❌ Error adding column $column: " . $conn->error . "</p>";
                }
            }
        }

        // Update existing records to have default approval status
        $update_query = "UPDATE cash_payments SET approval_status = 'under_review' WHERE approval_status IS NULL";
        if ($conn->query($update_query)) {
            echo "<p style='color: green;'>✅ Updated existing records with default approval status</p>";
        } else {
            echo "<p style='color: red;'>❌ Error updating existing records: " . $conn->error . "</p>";
        }
    }

    // Show table structure
    echo "<h3>Current Table Structure:</h3>";
    $result = $conn->query("DESCRIBE cash_payments");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<p style='color: green;'>✅ Migration completed successfully!</p>";
    echo "<p><a href='admin_payment_history.php'>Go to Admin Payment History</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?> 