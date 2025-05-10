<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$user = $auth->getCurrentUser();

// Get statistics
$stats = [
    'total_students' => 0,
    'pending_applications' => 0,
    'total_payments' => 0,
    'total_exams' => 0
];

// Get total students
$result = $db->query("SELECT COUNT(*) as count FROM students");
if ($result) {
    $stats['total_students'] = $result->fetch_assoc()['count'];
}

// Get pending applications
$result = $db->query("SELECT COUNT(*) as count FROM students WHERE status = 'pending'");
if ($result) {
    $stats['pending_applications'] = $result->fetch_assoc()['count'];
}

// Get total payments
$result = $db->query("SELECT COUNT(*) as count FROM payments");
if ($result) {
    $stats['total_payments'] = $result->fetch_assoc()['count'];
}

// Get total exams
$result = $db->query("SELECT COUNT(*) as count FROM exam_results");
if ($result) {
    $stats['total_exams'] = $result->fetch_assoc()['count'];
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
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <h3 class="mb-4"><?php echo SCHOOL_NAME; ?></h3>
                <div class="mb-4">
                    <p class="mb-1">Welcome,</p>
                    <h5><?php echo $user['name']; ?></h5>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="students.php" class="nav-link">
                            <i class="bi bi-people"></i> Students
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="applications.php" class="nav-link">
                            <i class="bi bi-file-text"></i> Applications
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="payments.php" class="nav-link">
                            <i class="bi bi-cash"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="exams.php" class="nav-link">
                            <i class="bi bi-pencil-square"></i> Exams
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="users.php" class="nav-link">
                            <i class="bi bi-person"></i> Users
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="settings.php" class="nav-link">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a href="logout.php" class="nav-link text-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Dashboard Overview</h2>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white">
                            <h3><?php echo $stats['total_students']; ?></h3>
                            <p class="mb-0">Total Students</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white">
                            <h3><?php echo $stats['pending_applications']; ?></h3>
                            <p class="mb-0">Pending Applications</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <h3><?php echo $stats['total_payments']; ?></h3>
                            <p class="mb-0">Total Payments</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <h3><?php echo $stats['total_exams']; ?></h3>
                            <p class="mb-0">Total Exams</p>
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
                                $result = $db->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 5");
                                if ($result && $result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $row['application_type']; ?></small>
                                        </div>
                                        <span class="badge bg-<?php echo $row['status'] === 'pending' ? 'warning' : ($row['status'] === 'registered' ? 'success' : 'danger'); ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
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
                                $result = $db->query("SELECT p.*, s.first_name, s.last_name FROM payments p 
                                                    JOIN students s ON p.student_id = s.id 
                                                    ORDER BY p.payment_date DESC LIMIT 5");
                                if ($result && $result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $row['payment_type']; ?></small>
                                        </div>
                                        <div class="text-end">
                                            <strong>₦<?php echo number_format($row['amount'], 2); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $row['payment_method']; ?></small>
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