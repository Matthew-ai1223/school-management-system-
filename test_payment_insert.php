<?php
require_once __DIR__ . '/backends/config.php';
require_once __DIR__ . '/backends/database.php';
require_once __DIR__ . '/backends/auth.php';
require_once __DIR__ . '/backends/utils.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Function to check table structure
function checkTableStructure($conn) {
    $result = $conn->query("DESCRIBE payments");
    echo "<h3>Table Structure:</h3>";
    echo "<pre>";
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . "\n";
        $fields[] = $row['Field'];
    }
    echo "</pre>";
    
    return $fields;
}

// Function to test insertion with direct SQL
function testDirectSQL($conn, $studentId) {
    echo "<h3>Testing Direct SQL Insertion:</h3>";
    
    $paymentType = 'tuition_fee';
    $amount = 5000;
    $paymentMethod = 'cash';
    $referenceNumber = 'TEST' . mt_rand(1000, 9999);
    $paymentDate = date('Y-m-d');
    $status = 'completed';
    $notes = 'Test direct SQL insertion';
    $userId = 1; // Test user ID
    
    // Sanitize values
    $sanitizedPaymentType = $conn->real_escape_string($paymentType);
    $sanitizedPaymentMethod = $conn->real_escape_string($paymentMethod);
    $sanitizedReferenceNumber = $conn->real_escape_string($referenceNumber);
    $sanitizedPaymentDate = $conn->real_escape_string($paymentDate);
    $sanitizedStatus = $conn->real_escape_string($status);
    $sanitizedNotes = $conn->real_escape_string($notes);
    
    $sql = "INSERT INTO payments (
            student_id, payment_type, amount, payment_method, 
            reference_number, payment_date, status, notes, created_by, created_at
        ) VALUES (
            $studentId, '$sanitizedPaymentType', $amount, '$sanitizedPaymentMethod',
            '$sanitizedReferenceNumber', '$sanitizedPaymentDate', '$sanitizedStatus', 
            '$sanitizedNotes', $userId, NOW()
        )";
    
    echo "SQL Query: " . $sql . "<br>";
    
    if ($conn->query($sql)) {
        $id = $conn->insert_id;
        echo "Insertion successful. ID: " . $id . "<br>";
        
        // Verify data was saved correctly
        $check = $conn->query("SELECT * FROM payments WHERE id = $id");
        $data = $check->fetch_assoc();
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        
        return $id;
    } else {
        echo "Error: " . $conn->error . "<br>";
        return false;
    }
}

// Function to test insertion with prepared statement
function testPreparedStatement($conn, $studentId) {
    echo "<h3>Testing Prepared Statement Insertion:</h3>";
    
    $paymentType = 'exam_fee';
    $amount = 3000;
    $paymentMethod = 'bank_transfer';
    $referenceNumber = 'TEST' . mt_rand(1000, 9999);
    $paymentDate = date('Y-m-d');
    $status = 'completed';
    $notes = 'Test prepared statement insertion';
    $userId = 1; // Test user ID
    
    $sql = "INSERT INTO payments (
            student_id, payment_type, amount, payment_method, 
            reference_number, payment_date, status, notes, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdssssi", $studentId, $paymentType, $amount, $paymentMethod,
                      $referenceNumber, $paymentDate, $status, $notes, $userId);
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        echo "Insertion successful. ID: " . $id . "<br>";
        
        // Verify data was saved correctly
        $check = $conn->query("SELECT * FROM payments WHERE id = $id");
        $data = $check->fetch_assoc();
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        
        return $id;
    } else {
        echo "Error: " . $stmt->error . "<br>";
        return false;
    }
}

// Function to test updating payment type via direct SQL
function testUpdatePaymentType($conn, $paymentId) {
    echo "<h3>Testing Update Payment Type:</h3>";
    
    $paymentType = 'book_fee';
    $sanitizedPaymentType = $conn->real_escape_string($paymentType);
    
    $sql = "UPDATE payments SET payment_type = '$sanitizedPaymentType' WHERE id = $paymentId";
    
    if ($conn->query($sql)) {
        echo "Update successful.<br>";
        
        // Verify data was updated correctly
        $check = $conn->query("SELECT payment_type FROM payments WHERE id = $paymentId");
        $data = $check->fetch_assoc();
        echo "Payment type after update: " . $data['payment_type'] . "<br>";
        
        return true;
    } else {
        echo "Error: " . $conn->error . "<br>";
        return false;
    }
}

// Function to suggest fixes based on test results
function suggestFixes($conn, $fields) {
    echo "<h3>Suggested Fixes:</h3>";
    
    // Check if payment_type field exists
    if (!in_array('payment_type', $fields)) {
        echo "The payment_type column doesn't exist in the payments table. You need to add it:<br>";
        echo "<code>ALTER TABLE payments ADD COLUMN payment_type VARCHAR(50) AFTER student_id;</code><br><br>";
    }
    
    // Check for column type issues
    $result = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_type'");
    if ($result && $result->num_rows > 0) {
        $column = $result->fetch_assoc();
        if ($column['Type'] != 'varchar(50)') {
            echo "The payment_type column has type " . $column['Type'] . ". Consider changing it to VARCHAR(50):<br>";
            echo "<code>ALTER TABLE payments MODIFY COLUMN payment_type VARCHAR(50);</code><br><br>";
        }
    }
    
    // Check if any triggers might be interfering
    $result = $conn->query("SHOW TRIGGERS LIKE 'payments'");
    if ($result && $result->num_rows > 0) {
        echo "The following triggers exist on the payments table:<br>";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Trigger'] . " (" . $row['Timing'] . " " . $row['Event'] . ")<br>";
        }
        echo "These might be interfering with data insertion. Consider temporarily disabling them.<br><br>";
    }
    
    // Suggest code fixes for update_payment.php
    echo "Suggested code fix for update_payment.php:<br>";
    echo "<pre>
// Replace the current direct SQL insertion with this:
\$stmt = \$conn->prepare(\"INSERT INTO payments (
        student_id, payment_type, amount, payment_method, 
        reference_number, payment_date, status, notes, created_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())\");

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
</pre>";
}

// Get a student ID to test with
$studentResult = $conn->query("SELECT id FROM students LIMIT 1");
if ($studentResult && $studentResult->num_rows > 0) {
    $student = $studentResult->fetch_assoc();
    $studentId = $student['id'];
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<h1>Payment Insertion Test</h1>";
    
    // Check table structure
    $fields = checkTableStructure($conn);
    
    // Test direct SQL insertion
    $directId = testDirectSQL($conn, $studentId);
    
    // Test prepared statement insertion
    $preparedId = testPreparedStatement($conn, $studentId);
    
    // Test update if direct insertion was successful
    if ($directId) {
        testUpdatePaymentType($conn, $directId);
    }
    
    // Suggest fixes
    suggestFixes($conn, $fields);
    
    echo "</div>";
} else {
    echo "No students found in the database.";
}
?> 