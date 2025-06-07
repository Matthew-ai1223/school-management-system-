<?php
require_once '../../confg.php';
require_once 'functions.php';

// Function to get filtered students
function getFilteredStudents($conn, $filters = []) {
    $where_clauses = ['is_active = 1'];
    $params = [];
    $types = '';
    
    // Handle department filter
    if (!empty($filters['department'])) {
        $where_clauses[] = "department = ?";
        $params[] = $filters['department'];
        $types .= 's';
    }
    
    // Handle status filter
    if (!empty($filters['status'])) {
        switch($filters['status']) {
            case 'active':
                $where_clauses[] = "expiration_date > NOW()";
                break;
            case 'expired':
                $where_clauses[] = "expiration_date < NOW()";
                break;
            case 'expiring_soon':
                $where_clauses[] = "expiration_date > NOW() AND expiration_date < DATE_ADD(NOW(), INTERVAL 7 DAY)";
                break;
        }
    }
    
    // Handle payment type filter
    if (!empty($filters['payment_type'])) {
        $where_clauses[] = "payment_type = ?";
        $params[] = $filters['payment_type'];
        $types .= 's';
    }
    
    // Build the query
    $where_clause = implode(' AND ', $where_clauses);
    $students = [];
    
    // Define the columns we want to select
    $columns = "id, fullname, department, payment_reference, payment_type, payment_amount, 
                registration_date, expiration_date, photo, is_active";
    
    // Query for morning students
    if (!empty($filters['table']) && $filters['table'] == 'afternoon_students') {
        $query = "SELECT $columns, 'afternoon_students' as source_table 
                 FROM afternoon_students 
                 WHERE $where_clause";
    } elseif (!empty($filters['table']) && $filters['table'] == 'morning_students') {
        $query = "SELECT $columns, 'morning_students' as source_table 
                 FROM morning_students 
                 WHERE $where_clause";
    } else {
        $query = "SELECT $columns, 'morning_students' as source_table 
                 FROM morning_students 
                 WHERE $where_clause
                 UNION ALL
                 SELECT $columns, 'afternoon_students' as source_table 
                 FROM afternoon_students 
                 WHERE $where_clause";
    }
    
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    
    return $students;
}

// Get unique departments and payment types for filters
$departments = [];
$payment_types = [];

$query = "SELECT DISTINCT department FROM morning_students WHERE is_active = 1
          UNION SELECT DISTINCT department FROM afternoon_students WHERE is_active = 1";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = $row['department'];
}

$query = "SELECT DISTINCT payment_type FROM morning_students WHERE is_active = 1
          UNION SELECT DISTINCT payment_type FROM afternoon_students WHERE is_active = 1";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $payment_types[] = $row['payment_type'];
}

// Get filters from URL
$filters = [
    'department' => $_GET['department'] ?? '',
    'status' => $_GET['status'] ?? '',
    'payment_type' => $_GET['payment_type'] ?? '',
    'table' => $_GET['table'] ?? ''
];

// Get filtered students
$students = getFilteredStudents($conn, $filters);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Print QR Codes</title>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .qr-card {
                page-break-inside: avoid;
                width: 48%;
                float: left;
                margin: 1%;
            }
            body {
                padding: 0;
                margin: 0;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .controls {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 5px;
            color: #666;
        }
        
        select, button {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .print-btn {
            background: #4CAF50;
            color: white;
        }
        
        .reset-btn {
            background: #f44336;
            color: white;
        }
        
        .back-btn {
            background: #2196F3;
            color: white;
        }
        
        .qr-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px;
        }
        
        .qr-card {
            flex: 0 0 calc(50% - 20px);
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .student-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 10px auto;
            display: block;
        }
        
        .qr-code {
            text-align: center;
            margin: 15px 0;
        }
        
        .qr-code img {
            max-width: 200px;
            height: auto;
        }
        
        .student-info {
            text-align: center;
        }
        
        .student-info h3 {
            margin: 10px 0;
            color: #333;
        }
        
        .student-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .active { background: #4CAF50; color: white; }
        .expired { background: #f44336; color: white; }
        .expiring-soon { background: #ff9800; color: white; }
        
        .student-count {
            text-align: center;
            margin: 20px 0;
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="no-print controls">
        <form method="get" id="filterForm">
            <div class="filters">
                <div class="filter-group">
                    <label for="department">Department:</label>
                    <select name="department" id="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"
                                    <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($dept)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="expired" <?php echo $filters['status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        <option value="expiring_soon" <?php echo $filters['status'] === 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="payment_type">Payment Type:</label>
                    <select name="payment_type" id="payment_type">
                        <option value="">All Payment Types</option>
                        <?php foreach ($payment_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"
                                    <?php echo $filters['payment_type'] === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="table">Student Type:</label>
                    <select name="table" id="table">
                        <option value="">All Students</option>
                        <option value="morning_students" <?php echo $filters['table'] === 'morning_students' ? 'selected' : ''; ?>>Morning Students</option>
                        <option value="afternoon_students" <?php echo $filters['table'] === 'afternoon_students' ? 'selected' : ''; ?>>Afternoon Students</option>
                    </select>
                </div>
            </div>
            
            <div class="buttons">
                <button type="submit" class="btn print-btn">Apply Filters</button>
                <a href="bulk_print.php" class="btn reset-btn">Reset Filters</a>
                <a href="index.php" class="btn back-btn">Back to List</a>
                <button type="button" onclick="window.print()" class="btn print-btn">Print Selected</button>
            </div>
        </form>
    </div>

    <div class="student-count no-print">
        Found <?php echo count($students); ?> students matching your criteria
    </div>

    <div class="qr-container">
        <?php foreach ($students as $student):
            $qrFile = generateStudentQR($student);
            $status = getAccountStatus($student['expiration_date']);
            $statusClass = strtolower(str_replace(' ', '-', $status));
        ?>
            <div class="qr-card">
                <img src="<?php echo htmlspecialchars($student['photo']); ?>" alt="Student Photo" class="student-photo">
                <div class="student-info">
                    <h3><?php echo htmlspecialchars($student['fullname']); ?></h3>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars(ucfirst($student['department'])); ?></p>
                    <p><strong>Payment Type:</strong> <?php echo htmlspecialchars(ucfirst($student['payment_type'])); ?></p>
                    <div class="status <?php echo $statusClass; ?>"><?php echo $status; ?></div>
                </div>
                <div class="qr-code">
                    <img src="<?php echo $qrFile; ?>" alt="QR Code">
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html> 