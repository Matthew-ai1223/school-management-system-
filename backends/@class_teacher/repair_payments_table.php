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

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$messages = [];
$errors = [];
$tableExists = false;
$tableStructure = [];

// Check if the payments table exists
$tableCheckQuery = "SHOW TABLES LIKE 'payments'";
$tableResult = $conn->query($tableCheckQuery);

if ($tableResult && $tableResult->num_rows > 0) {
    $tableExists = true;
    $messages[] = "Payments table exists in the database.";

    // Get the current table structure
    $columnsQuery = "SHOW COLUMNS FROM payments";
    $columnsResult = $conn->query($columnsQuery);
    
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $tableStructure[$column['Field']] = $column;
        }
        $messages[] = "Retrieved current table structure.";
    } else {
        $errors[] = "Failed to retrieve table structure: " . $conn->error;
    }
} else {
    $errors[] = "The payments table does not exist in the database.";
    
    // Offer to create the table
    if (isset($_GET['create_table']) && $_GET['create_table'] == 1) {
        $createTableQuery = "CREATE TABLE payments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            student_id INT(11) NOT NULL,
            payment_type VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            reference_number VARCHAR(100) NULL,
            payment_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            created_by INT(11) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id)
        )";
        
        if ($conn->query($createTableQuery)) {
            $messages[] = "Successfully created the payments table.";
            $tableExists = true;
        } else {
            $errors[] = "Failed to create payments table: " . $conn->error;
        }
    }
}

// Define required columns
$requiredColumns = [
    'id' => 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY',
    'student_id' => 'INT(11) NOT NULL',
    'payment_type' => 'VARCHAR(50) NOT NULL',
    'amount' => 'DECIMAL(10,2) NOT NULL',
    'payment_method' => 'VARCHAR(50) NOT NULL',
    'reference_number' => 'VARCHAR(100) NULL',
    'payment_date' => 'DATE NOT NULL',
    'status' => "VARCHAR(20) NOT NULL DEFAULT 'pending'",
    'notes' => 'TEXT NULL',
    'created_by' => 'INT(11) NULL',
    'created_at' => 'DATETIME NOT NULL',
    'updated_at' => 'DATETIME NULL'
];

// Check for missing columns and add them if requested
if ($tableExists && isset($_GET['fix_columns']) && $_GET['fix_columns'] == 1) {
    foreach ($requiredColumns as $columnName => $columnDef) {
        if (!isset($tableStructure[$columnName])) {
            $alterQuery = "ALTER TABLE payments ADD COLUMN $columnName $columnDef";
            if ($conn->query($alterQuery)) {
                $messages[] = "Added missing column: $columnName";
            } else {
                $errors[] = "Failed to add column $columnName: " . $conn->error;
            }
        }
    }
}

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Payments Table Repair Tool</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="payments.php">Payments</a></li>
                        <li class="breadcrumb-item active">Repair Table</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <?php if (!empty($messages)): ?>
                        <div class="alert alert-success">
                            <h5><i class="icon fas fa-check"></i> Success!</h5>
                            <ul>
                                <?php foreach ($messages as $message): ?>
                                    <li><?php echo $message; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Payments Table Status</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!$tableExists): ?>
                                <div class="alert alert-warning">
                                    <h5><i class="icon fas fa-exclamation-triangle"></i> Warning!</h5>
                                    <p>The payments table does not exist in the database.</p>
                                    <a href="?create_table=1" class="btn btn-primary">Create Payments Table</a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> Table Structure</h5>
                                    <p>The payments table exists. Checking for required columns...</p>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Column Name</th>
                                                <th>Current Status</th>
                                                <th>Required Definition</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requiredColumns as $columnName => $columnDef): ?>
                                                <tr>
                                                    <td><?php echo $columnName; ?></td>
                                                    <td>
                                                        <?php if (isset($tableStructure[$columnName])): ?>
                                                            <span class="badge badge-success">Exists</span>
                                                            <small><?php echo $tableStructure[$columnName]['Type']; ?></small>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Missing</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $columnDef; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php
                                $missingColumns = array_diff(array_keys($requiredColumns), array_keys($tableStructure));
                                if (!empty($missingColumns)):
                                ?>
                                    <div class="alert alert-warning mt-3">
                                        <h5><i class="icon fas fa-exclamation-triangle"></i> Missing Columns</h5>
                                        <p>The following columns are missing from the payments table:</p>
                                        <ul>
                                            <?php foreach ($missingColumns as $column): ?>
                                                <li><?php echo $column; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <a href="?fix_columns=1" class="btn btn-primary">Add Missing Columns</a>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success mt-3">
                                        <h5><i class="icon fas fa-check"></i> All Columns Present</h5>
                                        <p>All required columns exist in the payments table.</p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Test Data</h3>
                        </div>
                        <div class="card-body">
                            <p>You can test the payments table by adding a sample payment record:</p>
                            <a href="debug_payment.php" class="btn btn-primary">Run Payment Debug Test</a>
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