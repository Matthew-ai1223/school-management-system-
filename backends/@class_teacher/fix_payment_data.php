<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils.php';

// Check if user is logged in and has appropriate role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();
$messages = [];
$errors = [];

// Start output buffering
ob_start();

echo "<h1>Payment Data Fix Utility</h1>";

// Function to execute an SQL command safely
function executeSafely($conn, $query, $params = [], $types = "") {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execution failed: " . $stmt->error);
    }
    
    return $stmt;
}

// Check and update payment_type
if (isset($_GET['fix_payment_type']) && $_GET['fix_payment_type'] == 1) {
    try {
        // First get all records with empty payment_type
        $query = "SELECT id, amount FROM payments WHERE payment_type IS NULL OR payment_type = ''";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $count = 0;
            while ($row = $result->fetch_assoc()) {
                // For simplicity, set all unknown types to 'tuition_fee'
                $defaultType = 'tuition_fee';
                $updateQuery = "UPDATE payments SET payment_type = ? WHERE id = ?";
                executeSafely($conn, $updateQuery, [$defaultType, $row['id']], "si");
                $count++;
            }
            $messages[] = "Fixed payment_type for {$count} records.";
        } else {
            $messages[] = "No records with missing payment_type found.";
        }
    } catch (Exception $e) {
        $errors[] = "Error fixing payment_type: " . $e->getMessage();
    }
}

// Check and update payment_method
if (isset($_GET['fix_payment_method']) && $_GET['fix_payment_method'] == 1) {
    try {
        // First get all records with empty payment_method
        $query = "SELECT id FROM payments WHERE payment_method IS NULL OR payment_method = ''";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $count = 0;
            while ($row = $result->fetch_assoc()) {
                // For simplicity, set all unknown methods to 'cash'
                $defaultMethod = 'cash';
                $updateQuery = "UPDATE payments SET payment_method = ? WHERE id = ?";
                executeSafely($conn, $updateQuery, [$defaultMethod, $row['id']], "si");
                $count++;
            }
            $messages[] = "Fixed payment_method for {$count} records.";
        } else {
            $messages[] = "No records with missing payment_method found.";
        }
    } catch (Exception $e) {
        $errors[] = "Error fixing payment_method: " . $e->getMessage();
    }
}

// Check and update status
if (isset($_GET['fix_status']) && $_GET['fix_status'] == 1) {
    try {
        // First get all records with empty status
        $query = "SELECT id FROM payments WHERE status IS NULL OR status = ''";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $count = 0;
            while ($row = $result->fetch_assoc()) {
                // For simplicity, set all unknown statuses to 'completed'
                $defaultStatus = 'completed';
                $updateQuery = "UPDATE payments SET status = ? WHERE id = ?";
                executeSafely($conn, $updateQuery, [$defaultStatus, $row['id']], "si");
                $count++;
            }
            $messages[] = "Fixed status for {$count} records.";
        } else {
            $messages[] = "No records with missing status found.";
        }
    } catch (Exception $e) {
        $errors[] = "Error fixing status: " . $e->getMessage();
    }
}

// Fix all issues at once
if (isset($_GET['fix_all']) && $_GET['fix_all'] == 1) {
    try {
        $updateQuery = "UPDATE payments 
                     SET payment_type = COALESCE(payment_type, 'tuition_fee'),
                         payment_method = COALESCE(payment_method, 'cash'),
                         status = COALESCE(status, 'completed')
                     WHERE payment_type IS NULL OR payment_method IS NULL OR status IS NULL
                        OR payment_type = '' OR payment_method = '' OR status = ''";
        
        $stmt = $conn->prepare($updateQuery);
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            $messages[] = "Fixed {$affectedRows} records with missing data.";
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        $errors[] = "Error fixing all records: " . $e->getMessage();
    }
}

// Get current payment data statistics
$stats = [];

// Count total records
$result = $conn->query("SELECT COUNT(*) as total FROM payments");
$stats['total'] = $result->fetch_assoc()['total'];

// Count missing payment_type
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE payment_type IS NULL OR payment_type = ''");
$stats['missing_type'] = $result->fetch_assoc()['count'];

// Count missing payment_method
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE payment_method IS NULL OR payment_method = ''");
$stats['missing_method'] = $result->fetch_assoc()['count'];

// Count missing status
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status IS NULL OR status = ''");
$stats['missing_status'] = $result->fetch_assoc()['count'];

// Display messages and errors
if (!empty($messages)) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>";
    echo "<h3>Success Messages</h3>";
    echo "<ul>";
    foreach ($messages as $message) {
        echo "<li>{$message}</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>";
    echo "<h3>Errors</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>{$error}</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// Display statistics
echo "<h2>Payment Data Statistics</h2>";
echo "<div style='background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>";
echo "<p>Total payment records: {$stats['total']}</p>";
echo "<p>Records with missing payment_type: {$stats['missing_type']}</p>";
echo "<p>Records with missing payment_method: {$stats['missing_method']}</p>";
echo "<p>Records with missing status: {$stats['missing_status']}</p>";
echo "</div>";

// Display fix links
echo "<h2>Fix Options</h2>";
echo "<div style='background-color: #e2f3fc; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>";

if ($stats['missing_type'] > 0) {
    echo "<p><a href='?fix_payment_type=1' style='color: #0056b3;'>Fix Missing Payment Types</a> - Will set all missing payment types to 'tuition_fee'</p>";
}

if ($stats['missing_method'] > 0) {
    echo "<p><a href='?fix_payment_method=1' style='color: #0056b3;'>Fix Missing Payment Methods</a> - Will set all missing payment methods to 'cash'</p>";
}

if ($stats['missing_status'] > 0) {
    echo "<p><a href='?fix_status=1' style='color: #0056b3;'>Fix Missing Statuses</a> - Will set all missing statuses to 'completed'</p>";
}

if ($stats['missing_type'] > 0 || $stats['missing_method'] > 0 || $stats['missing_status'] > 0) {
    echo "<p><a href='?fix_all=1' style='color: #0056b3;'><strong>Fix All Issues</strong></a> - Will fix all missing data at once</p>";
}

echo "</div>";

echo "<p><a href='payments.php' style='color: #0056b3;'>Return to Payments</a></p>";

$output = ob_get_clean();

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Payment Data Fix Utility</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="payments.php">Payments</a></li>
                        <li class="breadcrumb-item active">Fix Data</li>
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