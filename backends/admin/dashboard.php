<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();
$user = $auth->getCurrentUser();

// Get statistics
$stats = [
    'total_applications' => 0,
    'pending_applications' => 0,
    'total_payments' => 0,
    'approved_applications' => 0
];

// Get total applications
$result = $mysqli->query("SELECT COUNT(*) as count FROM applications");
if ($result) {
    $stats['total_applications'] = $result->fetch_assoc()['count'];
}

// Get pending applications
$result = $mysqli->query("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
if ($result) {
    $stats['pending_applications'] = $result->fetch_assoc()['count'];
}

// Get approved applications
$result = $mysqli->query("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
if ($result) {
    $stats['approved_applications'] = $result->fetch_assoc()['count'];
}

// Get total payments
$result = $mysqli->query("SELECT COUNT(*) as count FROM application_payments WHERE status = 'completed'");
if ($result) {
    $stats['total_payments'] = $result->fetch_assoc()['count'];
}

// Get total amount from payments
$result = $mysqli->query("SELECT SUM(amount) as total FROM application_payments WHERE status = 'completed'");
$total_amount = 0;
if ($result) {
    $total_amount = $result->fetch_assoc()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
        }
        .stat-card {
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .stat-card p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .stat-card .icon {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            right: 20px;
            top: 20px;
        }
        .activity-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: background-color 0.3s ease;
        }
        .activity-item:hover {
            background-color: rgba(0,0,0,0.02);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Dashboard Overview</h2>
                    <div class="text-muted">
                        <?php echo date('l, F j, Y'); ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white position-relative">
                            <i class="bi bi-file-text icon"></i>
                            <h3><?php echo number_format($stats['total_applications']); ?></h3>
                            <p class="mb-0">Total Applications</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white position-relative">
                            <i class="bi bi-clock icon"></i>
                            <h3><?php echo number_format($stats['pending_applications']); ?></h3>
                            <p class="mb-0">Pending Applications</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white position-relative">
                            <i class="bi bi-check-circle icon"></i>
                            <h3><?php echo number_format($stats['approved_applications']); ?></h3>
                            <p class="mb-0">Approved Applications</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white position-relative">
                            <i class="bi bi-cash icon"></i>
                            <h3>₦<?php echo number_format($total_amount); ?></h3>
                            <p class="mb-0">Total Payments</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Applications</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $result = $mysqli->query("
                                    SELECT a.*, 
                                           JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.field_1')) as first_name,
                                           JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.field_2')) as last_name 
                                    FROM applications a 
                                    ORDER BY submission_date DESC LIMIT 5
                                ");
                                if ($result && $result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                        $name = trim($row['first_name'] . ' ' . $row['last_name']);
                                        if (empty($name)) $name = "Applicant #" . $row['id'];
                                ?>
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($name); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo ucfirst($row['application_type']); ?> Application
                                                    <span class="ms-2">•</span>
                                                    <span class="ms-2"><?php echo date('M j, Y', strtotime($row['submission_date'])); ?></span>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo $row['status'] === 'pending' ? 'warning' : 
                                                    ($row['status'] === 'approved' ? 'success' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <p class="text-muted mb-0">No recent applications</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Payments</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $result = $mysqli->query("
                                    SELECT p.*, 
                                           a.applicant_data,
                                           JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.field_1')) as first_name,
                                           JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.field_2')) as last_name
                                    FROM application_payments p
                                    JOIN applications a ON p.reference = JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.payment_reference'))
                                    WHERE p.status = 'completed'
                                    ORDER BY p.payment_date DESC LIMIT 5
                                ");
                                if ($result && $result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                        $name = trim($row['first_name'] . ' ' . $row['last_name']);
                                        if (empty($name)) $name = "Applicant #" . $row['id'];
                                ?>
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($name); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo ucfirst($row['payment_method']); ?>
                                                    <span class="ms-2">•</span>
                                                    <span class="ms-2"><?php echo date('M j, Y', strtotime($row['payment_date'])); ?></span>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-success">₦<?php echo number_format($row['amount'], 2); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo $row['reference']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <p class="text-muted mb-0">No recent payments</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 