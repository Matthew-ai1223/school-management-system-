<?php
session_start();
require_once '../config/config.php';
require_once '../includes/Database.php';

// Check admin authentication
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit();
// }

$db = Database::getInstance()->getConnection();

// // Get admin details
// $stmt = $db->prepare("SELECT * FROM admins WHERE id = :admin_id");
// $stmt->execute([':admin_id' => $_SESSION['admin_id']]);
// $admin = $stmt->fetch(PDO::FETCH_ASSOC);

// if (!$admin) {
//     session_destroy();
//     header('Location: login.php');
//     exit();
// }

// Get statistics
$stats = [];

// Total students (both morning and afternoon)
$stmt = $db->query("SELECT 
    (SELECT COUNT(*) FROM morning_students) + 
    (SELECT COUNT(*) FROM afternoon_students) as total_students,
    (SELECT COUNT(*) FROM morning_students WHERE is_active = true) +
    (SELECT COUNT(*) FROM afternoon_students WHERE is_active = true) as active_students,
    (SELECT COUNT(*) FROM exams) as total_exams,
    (SELECT COUNT(*) FROM exam_attempts WHERE status = 'completed') as total_attempts");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent exam attempts
$stmt = $db->prepare("
    SELECT ea.*, e.title as exam_title, 
    CASE 
        WHEN ms.id IS NOT NULL THEN ms.fullname 
        ELSE afs.fullname 
    END as student_name,
    CASE 
        WHEN ms.id IS NOT NULL THEN 'Morning'
        ELSE 'Afternoon'
    END as session
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    LEFT JOIN morning_students ms ON ea.user_id = ms.id
    LEFT JOIN afternoon_students afs ON ea.user_id = afs.id
    ORDER BY ea.start_time DESC
    LIMIT 5
");
$stmt->execute();
$recent_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get expiring accounts (within next 7 days)
$stmt = $db->prepare("
    (SELECT id, fullname, email, expiration_date, 'Morning' as session 
     FROM morning_students 
     WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))
    UNION
    (SELECT id, fullname, email, expiration_date, 'Afternoon' as session 
     FROM afternoon_students 
     WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))
    ORDER BY expiration_date ASC
");
$stmt->execute();
$expiring_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            padding: 20px;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
        }
        .sidebar .nav-link.active {
            background: #3498db;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card i {
            font-size: 2rem;
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <h4 class="text-white mb-4">Admin Panel</h4>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class='bx bxs-dashboard'></i> Dashboard
                    </a>
                    <a class="nav-link" href="students.php">
                        <i class='bx bxs-user-detail'></i> Students
                    </a>
                    <a class="nav-link" href="exams.php">
                        <i class='bx bxs-book'></i> Exams
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class='bx bxs-report'></i> Reports
                    </a>
                    <a class="nav-link" href="admins.php">
                        <i class='bx bxs-user'></i> Admins
                    </a>
                    <a class="nav-link text-danger" href="logout.php">
                        <i class='bx bxs-log-out'></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, Admin!</h2>
                    <!-- <h2>Welcome, <?php echo htmlspecialchars($admin['name']); ?>!</h2> -->
                    <span class="text-muted"><?php echo date('F d, Y'); ?></span>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Total Students</h6>
                                    <h3><?php echo $stats['total_students']; ?></h3>
                                </div>
                                <i class='bx bxs-group'></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Active Students</h6>
                                    <h3><?php echo $stats['active_students']; ?></h3>
                                </div>
                                <i class='bx bxs-user-check'></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Total Exams</h6>
                                    <h3><?php echo $stats['total_exams']; ?></h3>
                                </div>
                                <i class='bx bxs-book-content'></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Exam Attempts</h6>
                                    <h3><?php echo $stats['total_attempts']; ?></h3>
                                </div>
                                <i class='bx bxs-bar-chart-alt-2'></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Exam Attempts</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Session</th>
                                                <th>Exam</th>
                                                <th>Score</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_attempts as $attempt): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($attempt['session']); ?></td>
                                                <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                                                <td><?php echo $attempt['score']; ?>%</td>
                                                <td><?php echo date('M d, Y', strtotime($attempt['start_time'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Expiring Accounts</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($expiring_accounts)): ?>
                                    <p class="text-muted">No accounts expiring in the next 7 days.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($expiring_accounts as $account): ?>
                                        <div class="list-group-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($account['fullname']); ?></h6>
                                            <p class="mb-1 text-muted">
                                                <?php echo htmlspecialchars($account['session']); ?> Session
                                            </p>
                                            <small class="text-danger">
                                                Expires: <?php echo date('M d, Y', strtotime($account['expiration_date'])); ?>
                                            </small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
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