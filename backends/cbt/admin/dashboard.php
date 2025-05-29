<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get teacher details
$stmt = $db->prepare("SELECT t.*, u.username, u.email, u.role 
                     FROM teachers t 
                     JOIN users u ON t.user_id = u.id 
                     WHERE t.id = :teacher_id");
$stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Get statistics
$stats = [];

// Get teacher's subjects (with fallback if table doesn't exist)
try {
    $stmt = $db->prepare("SELECT DISTINCT subject FROM teacher_subjects WHERE teacher_id = :teacher_id ORDER BY subject");
    $stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
    $teacher_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If table doesn't exist, use default subjects
    $teacher_subjects = [
        ['subject' => 'All Subjects']
    ];
}

// Get selected subject
$selected_subject = isset($_GET['subject']) ? $_GET['subject'] : ($teacher_subjects[0]['subject'] ?? 'All Subjects');

// Get subject-specific statistics
$stmt = $db->prepare("SELECT 
    (SELECT COUNT(*) FROM exams WHERE created_by = :teacher_id" . 
    ($selected_subject !== 'All Subjects' ? " AND subject = :subject" : "") . ") as total_exams,
    (SELECT COUNT(*) FROM exam_attempts ea 
     JOIN exams e ON ea.exam_id = e.id 
     WHERE e.created_by = :teacher_id" .
    ($selected_subject !== 'All Subjects' ? " AND e.subject = :subject" : "") . "
     AND ea.status = 'completed') as total_attempts");

$params = [':teacher_id' => $_SESSION['teacher_id']];
if ($selected_subject !== 'All Subjects') {
    $params[':subject'] = $selected_subject;
}
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent exam attempts for selected subject
$stmt = $db->prepare("
    SELECT 
        ea.*,
        e.title as exam_title,
        e.subject,
        u.username as student_name,
        u.id as student_id
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    JOIN users u ON ea.user_id = u.id
    WHERE e.created_by = :teacher_id" .
    ($selected_subject !== 'All Subjects' ? " AND e.subject = :subject" : "") . "
    AND ea.status = 'completed'
    ORDER BY ea.start_time DESC
    LIMIT 5
");

$params = [':teacher_id' => $_SESSION['teacher_id']];
if ($selected_subject !== 'All Subjects') {
    $params[':subject'] = $selected_subject;
}
$stmt->execute($params);
$recent_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo SITE_NAME; ?></title>
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
            transition: transform 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card i {
            font-size: 2rem;
            color: #3498db;
        }
        .subject-selector select {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .subject-selector select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .subject-selector .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
        .subject-selector .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        .alert {
            border: none;
            border-radius: 0.5rem;
        }
        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: #2980b9;
        }
        @media (max-width: 768px) {
            .subject-selector {
                width: 100%;
            }
            .subject-selector form {
                width: 100%;
            }
            .subject-selector .d-flex {
                flex-direction: column;
                width: 100%;
            }
            .subject-selector select,
            .subject-selector button {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <h4 class="text-white mb-4">Teacher Panel</h4>
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
                    <a class="nav-link" href="assign-subjects.php">
                        <i class='bx bxs-book-content'></i> Assign Subjects
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class='bx bxs-report'></i> Reports
                    </a>
                    <a class="nav-link text-danger" href="logout.php">
                        <i class='bx bxs-log-out'></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <div>
                        <h2 class="mb-1">Welcome, <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>!</h2>
                        <p class="text-muted mb-0">Here's what's happening with your subjects today.</p>
                    </div>
                    <div class="subject-selector">
                        <form method="GET" class="bg-white p-2 rounded shadow-sm">
                            <div class="d-flex align-items-center gap-2">
                                <div class="position-relative">
                                    <select name="subject" id="subject" class="form-select form-select-lg pe-5" onchange="this.form.submit()" style="min-width: 200px;">
                                        <?php foreach ($teacher_subjects as $subject): ?>
                                            <option value="<?php echo htmlspecialchars($subject['subject']); ?>"
                                                    <?php echo $selected_subject === $subject['subject'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subject['subject']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class='bx bx-book-open position-absolute' style="right: 2rem; top: 50%; transform: translateY(-50%);"></i>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class='bx bx-filter-alt'></i>
                                    Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class='bx bx-info-circle me-2 fs-5'></i>
                            <div>
                                <?php if ($selected_subject === 'All Subjects'): ?>
                                    Showing statistics for all your subjects
                                <?php else: ?>
                                    Currently viewing statistics for <strong><?php echo htmlspecialchars($selected_subject); ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Total Exams<?php echo $selected_subject !== 'All Subjects' ? ' (' . htmlspecialchars($selected_subject) . ')' : ''; ?></h6>
                                    <h3><?php echo $stats['total_exams'] ?? 0; ?></h3>
                                </div>
                                <i class='bx bxs-book-content'></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Total Attempts<?php echo $selected_subject !== 'All Subjects' ? ' (' . htmlspecialchars($selected_subject) . ')' : ''; ?></h6>
                                    <h3><?php echo $stats['total_attempts'] ?? 0; ?></h3>
                                </div>
                                <i class='bx bxs-bar-chart-alt-2'></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row mt-4">
                    <div class="col-12">
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
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Refresh button functionality
        document.getElementById('refresh-attempts').addEventListener('click', function() {
            location.reload();
        });

        // Function to handle result download
        function downloadResult(attemptId) {
            // Add your download logic here
            alert('Downloading result for attempt ' + attemptId);
        }
    </script>
</body>
</html>