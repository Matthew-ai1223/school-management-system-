<?php
// Force error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database credentials - using direct connection to avoid dependency issues
$dbHost = 'localhost';
$dbUser = 'root';  // Default XAMPP username
$dbPass = '';      // Default XAMPP password
$dbName = 'ace_model_college';  // Your database name

// Create connection
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Payment Column Fix Utility</h1>";

// Check if payment_type column exists
$result = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_type'");
$paymentTypeExists = ($result && $result->num_rows > 0);

if (!$paymentTypeExists) {
    echo "<p>Adding payment_type column...</p>";
    if ($conn->query("ALTER TABLE payments ADD COLUMN payment_type VARCHAR(50) AFTER student_id")) {
        echo "<p class='success'>payment_type column added successfully!</p>";
    } else {
        echo "<p class='error'>Error adding payment_type column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>payment_type column already exists.</p>";
    
    // Check column type
    $columnInfo = $result->fetch_assoc();
    echo "<p>Current type: " . $columnInfo['Type'] . "</p>";
    
    // Ensure it's VARCHAR(50)
    if ($columnInfo['Type'] !== 'varchar(50)') {
        echo "<p>Updating payment_type column type to VARCHAR(50)...</p>";
        if ($conn->query("ALTER TABLE payments MODIFY COLUMN payment_type VARCHAR(50)")) {
            echo "<p class='success'>payment_type column type updated successfully!</p>";
        } else {
            echo "<p class='error'>Error updating payment_type column type: " . $conn->error . "</p>";
        }
    }
}

// Do the same for payment_method
$result = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_method'");
$paymentMethodExists = ($result && $result->num_rows > 0);

if (!$paymentMethodExists) {
    echo "<p>Adding payment_method column...</p>";
    if ($conn->query("ALTER TABLE payments ADD COLUMN payment_method VARCHAR(50) AFTER amount")) {
        echo "<p class='success'>payment_method column added successfully!</p>";
    } else {
        echo "<p class='error'>Error adding payment_method column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>payment_method column already exists.</p>";
    
    // Check column type
    $columnInfo = $result->fetch_assoc();
    echo "<p>Current type: " . $columnInfo['Type'] . "</p>";
    
    // Ensure it's VARCHAR(50)
    if ($columnInfo['Type'] !== 'varchar(50)') {
        echo "<p>Updating payment_method column type to VARCHAR(50)...</p>";
        if ($conn->query("ALTER TABLE payments MODIFY COLUMN payment_method VARCHAR(50)")) {
            echo "<p class='success'>payment_method column type updated successfully!</p>";
        } else {
            echo "<p class='error'>Error updating payment_method column type: " . $conn->error . "</p>";
        }
    }
}

// Check status column
$result = $conn->query("SHOW COLUMNS FROM payments LIKE 'status'");
$statusExists = ($result && $result->num_rows > 0);

if (!$statusExists) {
    echo "<p>Adding status column...</p>";
    if ($conn->query("ALTER TABLE payments ADD COLUMN status VARCHAR(20) DEFAULT 'pending'")) {
        echo "<p class='success'>status column added successfully!</p>";
    } else {
        echo "<p class='error'>Error adding status column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>status column already exists.</p>";
}

// Check notes column
$result = $conn->query("SHOW COLUMNS FROM payments LIKE 'notes'");
$notesExists = ($result && $result->num_rows > 0);

if (!$notesExists) {
    echo "<p>Adding notes column...</p>";
    if ($conn->query("ALTER TABLE payments ADD COLUMN notes TEXT")) {
        echo "<p class='success'>notes column added successfully!</p>";
    } else {
        echo "<p class='error'>Error adding notes column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>notes column already exists.</p>";
}

// Test a direct insertion to see if it works
echo "<h2>Testing Direct Insertion</h2>";

// Get a student ID to test with
$studentResult = $conn->query("SELECT id FROM students LIMIT 1");
if ($studentResult && $studentResult->num_rows > 0) {
    $student = $studentResult->fetch_assoc();
    $studentId = $student['id'];
    
    // Insert test payment
    $testPaymentType = 'test_payment';
    $testAmount = 100;
    $testMethod = 'test_method';
    $testRef = 'TEST' . rand(1000, 9999);
    $testDate = date('Y-m-d');
    $testStatus = 'completed';
    $testNotes = 'Test insertion from fix script';
    
    $insertSql = "INSERT INTO payments (
            student_id, payment_type, amount, payment_method, 
            reference_number, payment_date, status, notes, created_at
        ) VALUES (
            $studentId, '$testPaymentType', $testAmount, '$testMethod',
            '$testRef', '$testDate', '$testStatus', '$testNotes', NOW()
        )";
    
    if ($conn->query($insertSql)) {
        $id = $conn->insert_id;
        echo "<p class='success'>Test payment inserted successfully with ID: $id</p>";
        
        // Verify it was saved correctly
        $verifyResult = $conn->query("SELECT * FROM payments WHERE id = $id");
        if ($verifyResult && $verifyResult->num_rows > 0) {
            $payment = $verifyResult->fetch_assoc();
            echo "<h3>Inserted Payment Data:</h3>";
            echo "<ul>";
            echo "<li>payment_type: <strong>" . ($payment['payment_type'] ?? 'NULL') . "</strong></li>";
            echo "<li>payment_method: <strong>" . ($payment['payment_method'] ?? 'NULL') . "</strong></li>";
            echo "<li>status: <strong>" . ($payment['status'] ?? 'NULL') . "</strong></li>";
            echo "<li>notes: <strong>" . ($payment['notes'] ?? 'NULL') . "</strong></li>";
            echo "</ul>";
            
            // If values were not saved correctly, update them
            if (empty($payment['payment_type']) || $payment['payment_type'] !== $testPaymentType) {
                echo "<p>Attempting to fix payment_type with direct update...</p>";
                $updateSql = "UPDATE payments SET payment_type = '$testPaymentType' WHERE id = $id";
                if ($conn->query($updateSql)) {
                    echo "<p class='success'>payment_type updated successfully!</p>";
                } else {
                    echo "<p class='error'>Error updating payment_type: " . $conn->error . "</p>";
                }
            }
        }
    } else {
        echo "<p class='error'>Error inserting test payment: " . $conn->error . "</p>";
    }
} else {
    echo "<p class='error'>No students found in the database. Cannot test payment insertion.</p>";
}

// Suggest fixes for update_payment.php
echo "<h2>Recommended Fixes for update_payment.php</h2>";
echo "<p>Based on the tests, here are the recommended changes to update_payment.php:</p>";

echo "<pre style='background-color: #f5f5f5; padding: 15px; border: 1px solid #ddd;'>
// Replace the current direct SQL insertion with this:
\$sql = \"INSERT INTO payments (
        student_id, payment_type, amount, payment_method, 
        reference_number, payment_date, status, notes, created_by, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
    )\";

\$stmt = \$conn->prepare(\$sql);
\$stmt->bind_param(\"isdssssis\", 
    \$studentId, 
    \$paymentType,
    \$amount, 
    \$paymentMethod,
    \$referenceNumber, 
    \$paymentDate, 
    \$status, 
    \$notes, 
    \$userId
);

if (!\$stmt->execute()) {
    throw new Exception(\"SQL Error: \" . \$stmt->error);
}

\$paymentId = \$stmt->insert_id;

// Verify the data was saved correctly
\$verifyQuery = \"SELECT payment_type, payment_method, status FROM payments WHERE id = \$paymentId\";
\$verifyResult = \$conn->query(\$verifyQuery);

if (\$verifyResult && \$verifyResult->num_rows > 0) {
    \$savedData = \$verifyResult->fetch_assoc();
    
    // If data is missing, update it directly
    if (empty(\$savedData['payment_type']) || empty(\$savedData['payment_method']) || empty(\$savedData['status'])) {
        \$updateSql = \"UPDATE payments SET 
            payment_type = '\$paymentType',
            payment_method = '\$paymentMethod',
            status = '\$status',
            notes = '\$notes'
            WHERE id = \$paymentId\";
        \$conn->query(\$updateSql);
    }
}
</pre>";

// Add some basic styling
echo "
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #333; }
    h2 { color: #555; margin-top: 30px; }
    h3 { color: #777; }
    p { margin: 10px 0; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    pre { overflow-x: auto; }
    ul { background-color: #f8f8f8; padding: 15px 15px 15px 35px; border-radius: 5px; }
</style>
";

// Close the connection
$conn->close();
?> 