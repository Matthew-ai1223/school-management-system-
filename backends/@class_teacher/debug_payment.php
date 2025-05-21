<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils.php';

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get class teacher information
$userId = $_SESSION['user_id'];

// Get current table structure
$tableStructure = [];
$showColumnsQuery = "SHOW COLUMNS FROM payments";
$columnsResult = $conn->query($showColumnsQuery);

if ($columnsResult) {
    while ($column = $columnsResult->fetch_assoc()) {
        $tableStructure[] = $column;
    }
}

// Test data for insertion
$studentId = 1; // Replace with a valid student ID from your database
$paymentType = 'tuition_fee';
$amount = 10000.00;
$paymentMethod = 'cash';
$referenceNumber = 'TEST-' . rand(1000, 9999);
$paymentDate = date('Y-m-d');
$status = 'completed';
$notes = 'Debug test payment';

// Start output buffering to capture any errors
ob_start();

// Create payment record - test with simpler query first
$query = "INSERT INTO payments (
        student_id, payment_type, amount, payment_method, 
        reference_number, payment_date, status, notes, created_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
$stmt = $conn->prepare($query);

if ($stmt === false) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
} else {
    // Bind parameters
    $bindResult = $stmt->bind_param(
        "isdssssis", 
        $studentId, $paymentType, $amount, $paymentMethod,
        $referenceNumber, $paymentDate, $status, $notes, $userId
    );
    
    if ($bindResult === false) {
        echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    } else {
        // Execute the statement
        $executeResult = $stmt->execute();
        
        if ($executeResult === false) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        } else {
            $paymentId = $conn->insert_id;
            echo "Success! Payment inserted with ID: " . $paymentId;
            
            // Retrieve the inserted payment to verify
            $verifyQuery = "SELECT * FROM payments WHERE id = ?";
            $verifyStmt = $conn->prepare($verifyQuery);
            $verifyStmt->bind_param("i", $paymentId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows > 0) {
                $insertedPayment = $verifyResult->fetch_assoc();
                echo "<br><br>Inserted payment details:<br><pre>";
                print_r($insertedPayment);
                echo "</pre>";
            }
        }
    }
}

// Capture output
$output = ob_get_clean();

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Payment Debug</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Debug</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Database Connection Test</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($conn->connect_error): ?>
                                <div class="alert alert-danger">
                                    <h5><i class="icon fas fa-ban"></i> Connection Error!</h5>
                                    <?php echo $conn->connect_error; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <h5><i class="icon fas fa-check"></i> Connection Successful!</h5>
                                    Connected to database: <?php echo $conn->host_info; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">Payments Table Structure</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($tableStructure)): ?>
                                <div class="alert alert-danger">
                                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                                    Could not retrieve table structure. The payments table may not exist.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Field</th>
                                                <th>Type</th>
                                                <th>Null</th>
                                                <th>Key</th>
                                                <th>Default</th>
                                                <th>Extra</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tableStructure as $column): ?>
                                                <tr>
                                                    <td><?php echo $column['Field']; ?></td>
                                                    <td><?php echo $column['Type']; ?></td>
                                                    <td><?php echo $column['Null']; ?></td>
                                                    <td><?php echo $column['Key']; ?></td>
                                                    <td><?php echo $column['Default']; ?></td>
                                                    <td><?php echo $column['Extra']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Test Payment Insertion</h3>
                        </div>
                        <div class="card-body">
                            <h4>Attempting to insert test payment with the following data:</h4>
                            <pre>
Student ID: <?php echo $studentId; ?>
Payment Type: <?php echo $paymentType; ?>
Amount: <?php echo $amount; ?>
Payment Method: <?php echo $paymentMethod; ?>
Reference Number: <?php echo $referenceNumber; ?>
Date: <?php echo $paymentDate; ?>
Status: <?php echo $status; ?>
Notes: <?php echo $notes; ?>
Created By: <?php echo $userId; ?>
                            </pre>
                            
                            <div class="alert <?php echo strpos($output, 'Success') !== false ? 'alert-success' : 'alert-danger'; ?>">
                                <h5><i class="icon fas <?php echo strpos($output, 'Success') !== false ? 'fa-check' : 'fa-ban'; ?>"></i> 
                                    <?php echo strpos($output, 'Success') !== false ? 'Success!' : 'Error!'; ?>
                                </h5>
                                <?php echo $output; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <a href="payments.php" class="btn btn-primary">Return to Payments</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../admin/include/footer.php'; ?> 