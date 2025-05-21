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

// Start output buffering
ob_start();

echo "<h1>Payment Insertion Debug</h1>";

// Debug variables
$debugOutput = [];
$insertResult = null;
$insertID = null;
$retrievedPayment = null;
$debugError = null;

// Only process the form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Explicitly use form data
    $studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $paymentType = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $referenceNumber = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
    $paymentDate = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d');
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'completed';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $userId = $_SESSION['user_id'];

    // Log form data for debugging
    $debugOutput[] = [
        'section' => 'Form Data',
        'data' => [
            'studentId' => $studentId . ' (Type: ' . gettype($studentId) . ')',
            'paymentType' => $paymentType . ' (Type: ' . gettype($paymentType) . ', Length: ' . strlen($paymentType) . ')',
            'amount' => $amount . ' (Type: ' . gettype($amount) . ')',
            'paymentMethod' => $paymentMethod . ' (Type: ' . gettype($paymentMethod) . ', Length: ' . strlen($paymentMethod) . ')',
            'referenceNumber' => $referenceNumber . ' (Type: ' . gettype($referenceNumber) . ')',
            'paymentDate' => $paymentDate . ' (Type: ' . gettype($paymentDate) . ')',
            'status' => $status . ' (Type: ' . gettype($status) . ', Length: ' . strlen($status) . ')',
            'notes' => $notes . ' (Type: ' . gettype($notes) . ', Length: ' . strlen($notes) . ')',
            'userId' => $userId . ' (Type: ' . gettype($userId) . ')'
        ]
    ];

    // Try direct SQL insertion first (no prepared statement)
    try {
        $directSQL = "INSERT INTO payments (
                student_id, payment_type, amount, payment_method, 
                reference_number, payment_date, status, notes, created_by, created_at
            ) VALUES (
                $studentId, '$paymentType', $amount, '$paymentMethod',
                '$referenceNumber', '$paymentDate', '$status', '" . $conn->real_escape_string($notes) . "', $userId, NOW()
            )";
        
        $debugOutput[] = [
            'section' => 'Direct SQL Query',
            'data' => $directSQL
        ];
        
        $directResult = $conn->query($directSQL);
        
        if ($directResult) {
            $insertID = $conn->insert_id;
            $debugOutput[] = [
                'section' => 'Direct SQL Result',
                'data' => "Success! Payment inserted with ID: $insertID"
            ];
        } else {
            $debugOutput[] = [
                'section' => 'Direct SQL Error',
                'data' => $conn->error
            ];
        }
    } catch (Exception $e) {
        $debugOutput[] = [
            'section' => 'Direct SQL Exception',
            'data' => $e->getMessage()
        ];
    }
    
    // Verify the data was inserted correctly
    if ($insertID) {
        try {
            $checkSQL = "SELECT * FROM payments WHERE id = $insertID";
            $result = $conn->query($checkSQL);
            
            if ($result && $result->num_rows > 0) {
                $retrievedPayment = $result->fetch_assoc();
                $debugOutput[] = [
                    'section' => 'Retrieved Payment',
                    'data' => $retrievedPayment
                ];
                
                // Compare with original values
                $debugOutput[] = [
                    'section' => 'Data Comparison',
                    'data' => [
                        'payment_type' => [
                            'sent' => $paymentType,
                            'saved' => $retrievedPayment['payment_type'],
                            'match' => ($paymentType === $retrievedPayment['payment_type']) ? 'YES' : 'NO'
                        ],
                        'payment_method' => [
                            'sent' => $paymentMethod,
                            'saved' => $retrievedPayment['payment_method'],
                            'match' => ($paymentMethod === $retrievedPayment['payment_method']) ? 'YES' : 'NO'
                        ],
                        'status' => [
                            'sent' => $status,
                            'saved' => $retrievedPayment['status'],
                            'match' => ($status === $retrievedPayment['status']) ? 'YES' : 'NO'
                        ],
                        'notes' => [
                            'sent' => $notes,
                            'saved' => $retrievedPayment['notes'],
                            'match' => ($notes === $retrievedPayment['notes']) ? 'YES' : 'NO'
                        ]
                    ]
                ];
            } else {
                $debugOutput[] = [
                    'section' => 'Verification Error',
                    'data' => "Could not retrieve the inserted payment"
                ];
            }
        } catch (Exception $e) {
            $debugOutput[] = [
                'section' => 'Verification Exception',
                'data' => $e->getMessage()
            ];
        }
    }
}

// Check table structure
try {
    $tableSQL = "SHOW CREATE TABLE payments";
    $result = $conn->query($tableSQL);
    
    if ($result && $result->num_rows > 0) {
        $tableDefinition = $result->fetch_assoc();
        $debugOutput[] = [
            'section' => 'Table Structure',
            'data' => $tableDefinition['Create Table']
        ];
    } else {
        $debugOutput[] = [
            'section' => 'Table Structure Error',
            'data' => "Could not retrieve table structure"
        ];
    }
} catch (Exception $e) {
    $debugOutput[] = [
        'section' => 'Table Structure Exception',
        'data' => $e->getMessage()
    ];
}

// Check for triggers on the payments table
try {
    $triggerSQL = "SHOW TRIGGERS LIKE 'payments'";
    $result = $conn->query($triggerSQL);
    
    if ($result && $result->num_rows > 0) {
        $triggers = [];
        while ($row = $result->fetch_assoc()) {
            $triggers[] = $row;
        }
        $debugOutput[] = [
            'section' => 'Database Triggers',
            'data' => $triggers
        ];
    } else {
        $debugOutput[] = [
            'section' => 'Database Triggers',
            'data' => "No triggers found"
        ];
    }
} catch (Exception $e) {
    $debugOutput[] = [
        'section' => 'Database Triggers Exception',
        'data' => $e->getMessage()
    ];
}

// Output debug information
echo "<div style='margin-bottom: 20px;'>";
if ($insertID) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>";
    echo "<h3>Payment Created Successfully</h3>";
    echo "<p>Payment ID: {$insertID}</p>";
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>";
    echo "<h3>No Payment Created Yet</h3>";
    echo "<p>Fill out the form below to test payment creation</p>";
    echo "</div>";
}
echo "</div>";

foreach ($debugOutput as $section) {
    echo "<div style='background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>";
    echo "<h3>{$section['section']}</h3>";
    
    if (is_array($section['data'])) {
        echo "<pre style='background-color: #e9ecef; padding: 10px; border-radius: 4px;'>";
        print_r($section['data']);
        echo "</pre>";
    } else {
        echo "<div style='background-color: #e9ecef; padding: 10px; border-radius: 4px;'>";
        echo "<code style='white-space: pre-wrap;'>" . htmlspecialchars($section['data']) . "</code>";
        echo "</div>";
    }
    
    echo "</div>";
}

// Test payment form
echo "<div style='background-color: #e2f3fc; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>";
echo "<h2>Test Payment Form</h2>";
echo "<form method='POST' action=''>";

// Get students for dropdown
$studentsQuery = "SELECT s.id, s.first_name, s.last_name, s.registration_number 
                 FROM students s
                 JOIN class_teachers ct ON s.class = ct.class_name
                 WHERE ct.user_id = ? AND ct.is_active = 1
                 ORDER BY s.first_name, s.last_name
                 LIMIT 10";

$stmt = $conn->prepare($studentsQuery);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$studentsResult = $stmt->get_result();
$students = [];

while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>Student</label>";
echo "<select name='student_id' style='width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ced4da;'>";
foreach ($students as $student) {
    echo "<option value='{$student['id']}'>{$student['first_name']} {$student['last_name']} ({$student['registration_number']})</option>";
}
echo "</select>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>Payment Type</label>";
echo "<select name='payment_type' style='width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ced4da;'>";
echo "<option value='tuition_fee'>Tuition Fee</option>";
echo "<option value='registration_fee'>Registration Fee</option>";
echo "<option value='development_levy'>Development Levy</option>";
echo "<option value='book_fee'>Book Fee</option>";
echo "<option value='uniform_fee'>Uniform Fee</option>";
echo "<option value='exam_fee'>Examination Fee</option>";
echo "<option value='transportation_fee'>Transportation Fee</option>";
echo "<option value='other'>Other</option>";
echo "</select>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>Amount</label>";
echo "<input type='number' name='amount' value='100' style='width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ced4da;'>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>Payment Method</label>";
echo "<select name='payment_method' style='width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ced4da;'>";
echo "<option value='cash'>Cash</option>";
echo "<option value='bank_transfer'>Bank Transfer</option>";
echo "<option value='card'>Card Payment</option>";
echo "<option value='cheque'>Cheque</option>";
echo "<option value='online'>Online Payment</option>";
echo "</select>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>Reference Number</label>";
echo "<input type='text' name='reference_number' value='TEST-" . rand(1000, 9999) . "' style='width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ced4da;'>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>Payment Date</label>";
echo "<input type='date' name='payment_date' value='" . date('Y-m-d') . "' style='width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ced4da;'>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>Status</label>";
echo "<select name='status' style='width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ced4da;'>";
echo "<option value='completed'>Completed</option>";
echo "<option value='pending'>Pending</option>";
echo "<option value='failed'>Failed</option>";
echo "</select>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label style='display: block; margin-bottom: 5px;'>Notes</label>";
echo "<textarea name='notes' style='width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ced4da;'>Test payment note</textarea>";
echo "</div>";

echo "<button type='submit' style='padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px;'>Test Payment Insertion</button>";
echo "</form>";
echo "</div>";

echo "<p><a href='payments.php' style='color: #0056b3;'>Return to Payments</a> | <a href='fix_payment_data.php' style='color: #0056b3;'>Fix Existing Data</a></p>";

// Get output
$output = ob_get_clean();

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Payment Debug Test</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="payments.php">Payments</a></li>
                        <li class="breadcrumb-item active">Debug Test</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <?php echo $output; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../admin/include/footer.php'; ?> 