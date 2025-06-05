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
    SELECT 
        ea.*,
        e.title as exam_title,
        COALESCE(ms.fullname, afs.fullname) as student_name,
        COALESCE(ms.id, afs.id) as student_id,
        CASE 
            WHEN ms.id IS NOT NULL THEN 'Morning'
            WHEN afs.id IS NOT NULL THEN 'Afternoon'
            ELSE 'Unknown'
        END as session
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    LEFT JOIN morning_students ms ON ea.user_id = ms.id
    LEFT JOIN afternoon_students afs ON ea.user_id = afs.id
    WHERE ea.status = 'completed'
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
                <!-- <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Exam Attempts</h5>
                                <div>
                                    <button id="refresh-attempts" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                    <a href="view-attempts.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="recentAttemptsTable">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Session</th>
                                                <th>Exam</th>
                                                <th>Score</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_attempts as $attempt): 
                                                // Calculate status based on score
                                                $status = '';
                                                $status_class = '';
                                                if ($attempt['score'] >= 70) {
                                                    $status = 'Excellent';
                                                    $status_class = 'success';
                                                } elseif ($attempt['score'] >= 50) {
                                                    $status = 'Pass';
                                                    $status_class = 'primary';
                                                } else {
                                                    $status = 'Fail';
                                                    $status_class = 'danger';
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center">
                                                            <span class="text-primary"><?php echo strtoupper(substr($attempt['student_name'] ?? 'NA', 0, 2)); ?></span>
                                                        </div>
                                                        <div>
                                                            <?php echo htmlspecialchars($attempt['student_name'] ?? 'Unknown Student'); ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                ID: <?php echo htmlspecialchars($attempt['student_id'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-info"><?php echo htmlspecialchars($attempt['session']); ?></span></td>
                                                <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $attempt['score']; ?>%"
                                                             aria-valuenow="<?php echo $attempt['score']; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo $attempt['score']; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($attempt['start_time'])); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="view-attempt.php?id=<?php echo $attempt['id']; ?>" 
                                                           class="btn btn-sm btn-info" 
                                                           title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-success" 
                                                                onclick="downloadResult(<?php echo $attempt['id']; ?>)"
                                                                title="Download Result">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    </div>
                                                </td>
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
                </div> -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            const attemptsTable = $('#recentAttemptsTable').DataTable({
                pageLength: 10,
                order: [[5, 'desc']], // Sort by date column by default
                responsive: true,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search attempts..."
                }
            });

            // Refresh button functionality
            $('#refresh-attempts').click(function() {
                const button = $(this);
                const icon = button.find('i');
                
                // Add spinning animation
                icon.addClass('fa-spin');
                button.prop('disabled', true);

                // Simulate refresh (replace with actual AJAX call)
                setTimeout(function() {
                    // Remove spinning animation
                    icon.removeClass('fa-spin');
                    button.prop('disabled', false);
                    
                    // Show success message
                    const toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true">')
                        .html(`
                            <div class="toast-header bg-success text-white">
                                <strong class="me-auto">Success</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                            <div class="toast-body">
                                Data refreshed successfully!
                            </div>
                        `)
                        .appendTo($('body'));
                    
                    const bsToast = new bootstrap.Toast(toast);
                    bsToast.show();
                    
                    // Remove toast after it's hidden
                    toast.on('hidden.bs.toast', function() {
                        toast.remove();
                    });
                }, 1000);
            });
        });

        // Function to handle result download
        function downloadResult(attemptId) {
            // Add your download logic here
            alert('Downloading result for attempt ' + attemptId);
        }

        // Add custom styles
        const style = document.createElement('style');
        style.textContent = `
            .avatar-sm {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
            }
            .progress {
                background-color: #e9ecef;
                border-radius: 0.25rem;
            }
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1050;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>