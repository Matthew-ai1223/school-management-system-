<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();
$errors = [];
$tableInfo = [];

// Start output buffering to capture any errors
ob_start();

echo "<h1>Payment Database Test</h1>";

// Test database connection
echo "<h2>Database Connection</h2>";
if ($conn->connect_error) {
    echo "<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color:green'>Database connection successful</p>";
}

// Check if payments table exists
echo "<h2>Payments Table Structure</h2>";
$columnsQuery = "SHOW COLUMNS FROM payments";
$columnsResult = $conn->query($columnsQuery);

if (!$columnsResult) {
    echo "<p style='color:red'>Error checking table structure: " . $conn->error . "</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($column = $columnsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
        
        $tableInfo[$column['Field']] = $column;
    }
    
    echo "</table>";
}

// Check for essential columns
echo "<h2>Essential Columns Check</h2>";

$requiredColumns = [
    'payment_type' => 'Payment Type',
    'payment_method' => 'Payment Method',
    'status' => 'Status',
    'notes' => 'Notes',
];

echo "<ul>";
foreach ($requiredColumns as $column => $label) {
    if (isset($tableInfo[$column])) {
        echo "<li style='color:green'>{$label} column exists ({$column})</li>";
    } else {
        echo "<li style='color:red'>{$label} column is missing ({$column})</li>";
        $errors[] = "Missing column: {$column}";
    }
}
echo "</ul>";

// Test insert with sample data
echo "<h2>Test Payment Insert</h2>";

$testInsert = false;
if (isset($_GET['test_insert']) && $_GET['test_insert'] == 1) {
    $testInsert = true;
    
    try {
        $studentId = 1; // Use a valid student ID from your database
        $paymentType = 'test_fee';
        $amount = 100.00;
        $paymentMethod = 'cash';
        $referenceNumber = 'TEST-' . mt_rand(1000, 9999);
        $paymentDate = date('Y-m-d');
        $status = 'completed';
        $notes = 'Test payment from troubleshooting script';
        $userId = 1; // Use a valid user ID
        
        // Create payment record - with all columns
        $query = "INSERT INTO payments (
                student_id, payment_type, amount, payment_method, 
                reference_number, payment_date, status, notes, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
        $stmt = $conn->prepare($query);
        
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $bindResult = $stmt->bind_param(
            "isdssssis", 
            $studentId, $paymentType, $amount, $paymentMethod,
            $referenceNumber, $paymentDate, $status, $notes, $userId
        );
        
        if ($bindResult === false) {
            throw new Exception("Binding parameters failed: " . $stmt->error);
        }
        
        $executeResult = $stmt->execute();
        
        if ($executeResult === false) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $paymentId = $conn->insert_id;
        echo "<p style='color:green'>Success! Test payment inserted with ID: " . $paymentId . "</p>";
        
        // Verify the inserted data
        $verifyQuery = "SELECT * FROM payments WHERE id = ?";
        $verifyStmt = $conn->prepare($verifyQuery);
        $verifyStmt->bind_param("i", $paymentId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows > 0) {
            $insertedPayment = $verifyResult->fetch_assoc();
            echo "<h3>Inserted payment data:</h3>";
            echo "<pre>";
            print_r($insertedPayment);
            echo "</pre>";
            
            // Check if values were saved correctly
            echo "<h3>Data Verification:</h3>";
            echo "<ul>";
            if ($insertedPayment['payment_type'] === $paymentType) {
                echo "<li style='color:green'>Payment Type: CORRECT</li>";
            } else {
                echo "<li style='color:red'>Payment Type: ERROR - Expected '{$paymentType}', got '{$insertedPayment['payment_type']}'</li>";
            }
            
            if ($insertedPayment['payment_method'] === $paymentMethod) {
                echo "<li style='color:green'>Payment Method: CORRECT</li>";
            } else {
                echo "<li style='color:red'>Payment Method: ERROR - Expected '{$paymentMethod}', got '{$insertedPayment['payment_method']}'</li>";
            }
            
            if ($insertedPayment['status'] === $status) {
                echo "<li style='color:green'>Status: CORRECT</li>";
            } else {
                echo "<li style='color:red'>Status: ERROR - Expected '{$status}', got '{$insertedPayment['status']}'</li>";
            }
            
            if ($insertedPayment['notes'] === $notes) {
                echo "<li style='color:green'>Notes: CORRECT</li>";
            } else {
                echo "<li style='color:red'>Notes: ERROR - Expected '{$notes}', got '{$insertedPayment['notes']}'</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color:red'>Could not retrieve the inserted payment!</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
}

// Summary and recommendations
echo "<h2>Summary</h2>";
if (count($errors) > 0) {
    echo "<p style='color:red'>Found " . count($errors) . " issues that need to be fixed:</p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>{$error}</li>";
    }
    echo "</ul>";
    
    echo "<p><a href='repair_payments_table.php' style='color:blue'>Run the repair script to fix these issues</a></p>";
} else {
    echo "<p style='color:green'>No database structure issues detected.</p>";
}

if (!$testInsert) {
    echo "<p><a href='?test_insert=1' style='color:blue'>Click here to test a payment insertion</a></p>";
}

echo "<p><a href='payments.php'>Return to payments page</a></p>";

// End output buffering
$output = ob_get_clean();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Database Test - ACE COLLEGE</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th { background-color: #f2f2f2; text-align: left; }
        pre { background-color: #f5f5f5; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <?php echo $output; ?>
</body>
</html> 