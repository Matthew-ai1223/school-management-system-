<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

session_start();

// // Check admin authentication
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit();
// }

$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$session = filter_input(INPUT_GET, 'session', FILTER_SANITIZE_STRING);

if (!$student_id || !in_array($session, ['morning', 'afternoon'])) {
    header('Location: students.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get student details from appropriate table
$table = $session . '_students';
$query = "SELECT * FROM $table WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: students.php');
    exit();
}

// Get overall statistics
$stats_query = "SELECT 
                COUNT(DISTINCT ea.exam_id) as total_exams,
                ROUND(AVG(CASE WHEN ea.status = 'completed' THEN ea.score ELSE NULL END), 1) as avg_score,
                ROUND(MIN(CASE WHEN ea.status = 'completed' THEN ea.score ELSE NULL END), 1) as min_score,
                ROUND(MAX(CASE WHEN ea.status = 'completed' THEN ea.score ELSE NULL END), 1) as max_score,
                COUNT(DISTINCT CASE WHEN ea.status = 'completed' AND ea.score >= e.passing_score THEN ea.exam_id END) as passed_exams,
                COUNT(DISTINCT CASE WHEN ea.status = 'completed' THEN ea.exam_id END) as completed_exams
                FROM exam_attempts ea
                JOIN exams e ON ea.exam_id = e.id
                WHERE ea.user_id = :student_id";
$stmt = $db->prepare($stats_query);
$stmt->execute([':student_id' => $student_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get exam history
$history_query = "SELECT ea.*, e.title as exam_title, e.passing_score
                 FROM exam_attempts ea
                 JOIN exams e ON ea.exam_id = e.id
                 WHERE ea.user_id = :student_id
                 ORDER BY ea.start_time DESC";
$stmt = $db->prepare($history_query);
$stmt->execute([':student_id' => $student_id]);
$exam_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly performance data for chart
$monthly_query = "SELECT 
                  DATE_FORMAT(start_time, '%Y-%m') as month,
                  AVG(score) as avg_score,
                  COUNT(*) as exam_count
                  FROM exam_attempts
                  WHERE user_id = :student_id AND status = 'completed'
                  GROUP BY DATE_FORMAT(start_time, '%Y-%m')
                  ORDER BY month DESC
                  LIMIT 12";
$stmt = $db->prepare($monthly_query);
$stmt->execute([':student_id' => $student_id]);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get certificates
$cert_query = "SELECT c.*, e.title as exam_title
               FROM certificates c
               JOIN exams e ON c.exam_id = e.id
               WHERE c.user_id = :student_id
               ORDER BY c.issue_date DESC";
$stmt = $db->prepare($cert_query);
$stmt->execute([':student_id' => $student_id]);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Performance - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .main-content {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            height: 100%;
        }
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Student Performance</h1>
                        <p class="text-muted mb-0">
                            <?php echo htmlspecialchars($student['fullname']); ?> 
                            (<?php echo ucfirst($session); ?> Session)
                        </p>
                    </div>
                    <div class="btn-group">
                        <a href="edit-student.php?id=<?php echo $student_id; ?>&session=<?php echo $session; ?>" 
                           class="btn btn-outline-primary">
                            <i class='bx bx-edit'></i> Edit Profile
                        </a>
                        <a href="students.php" class="btn btn-secondary">
                            <i class='bx bx-arrow-back'></i> Back to Students
                        </a>
                    </div>
                </div>

                <!-- Student Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone']); ?></p>
                                <p>
                                    <strong>Account Status:</strong>
                                    <span class="badge bg-<?php echo strtotime($student['expiration_date']) > time() ? 'success' : 'danger'; ?>">
                                        <?php echo strtotime($student['expiration_date']) > time() ? 'Active' : 'Expired'; ?>
                                        (Expires: <?php echo date('M d, Y', strtotime($student['expiration_date'])); ?>)
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class='bx bxs-book-content text-primary'></i>
                            <h6 class="text-muted">Total/Completed Exams</h6>
                            <h3><?php echo $stats['completed_exams']; ?> / <?php echo $stats['total_exams']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class='bx bxs-bar-chart-alt-2 text-success'></i>
                            <h6 class="text-muted">Average Score</h6>
                            <h3><?php echo round($stats['avg_score'], 1); ?>%</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class='bx bxs-check-circle text-info'></i>
                            <h6 class="text-muted">Passed Exams</h6>
                            <h3><?php echo $stats['passed_exams']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class='bx bxs-trophy text-warning'></i>
                            <h6 class="text-muted">Best Score</h6>
                            <h3><?php echo round($stats['max_score'], 1); ?>%</h3>
                        </div>
                    </div>
                </div>

                <!-- Performance Chart -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Monthly Performance</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>

                <!-- Exam History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Exam History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Date</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam_history as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($attempt['start_time'])); ?></td>
                                        <td>
                                            <?php if ($attempt['score'] !== null): ?>
                                                <span class="badge bg-<?php echo $attempt['score'] >= $attempt['passing_score'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $attempt['score']; ?>%
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $attempt['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($attempt['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            if ($attempt['end_time']) {
                                                $duration = strtotime($attempt['end_time']) - strtotime($attempt['start_time']);
                                                echo floor($duration / 60) . 'm ' . ($duration % 60) . 's';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="view-attempt.php?id=<?php echo $attempt['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class='bx bx-show'></i> View Details
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Certificates -->
                <?php if (!empty($certificates)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Certificates</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Certificate Number</th>
                                        <th>Issue Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($certificates as $cert): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cert['exam_title']); ?></td>
                                        <td><?php echo htmlspecialchars($cert['certificate_number']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($cert['issue_date'])); ?></td>
                                        <td>
                                            <a href="download-certificate.php?id=<?php echo $cert['id']; ?>" 
                                               class="btn btn-sm btn-outline-success">
                                                <i class='bx bx-download'></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Performance Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column(array_reverse($monthly_data), 'month')); ?>,
                datasets: [{
                    label: 'Average Score (%)',
                    data: <?php echo json_encode(array_column(array_reverse($monthly_data), 'avg_score')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    </script>
</body>
</html> 