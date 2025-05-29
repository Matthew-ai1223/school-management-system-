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

$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$student_id) {
    header('Location: students.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get student details and verify access
$query = "SELECT u.* FROM users u
          WHERE u.id = :id 
          AND u.role = 'student'
          AND u.added_by = :teacher_id";
$stmt = $db->prepare($query);
$stmt->execute([
    ':id' => $student_id,
    ':teacher_id' => $_SESSION['teacher_id']
]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: students.php');
    exit();
}

// Get teacher's subjects
$stmt = $db->prepare("SELECT DISTINCT subject FROM teacher_subjects WHERE teacher_id = :teacher_id ORDER BY subject");
$stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
$teacher_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected subject
$selected_subject = isset($_GET['subject']) ? $_GET['subject'] : ($teacher_subjects[0]['subject'] ?? 'All Subjects');

// Get overall statistics for exams created by this teacher
$stats_query = "SELECT 
                COUNT(DISTINCT ea.id) as total_exams,
                AVG(ea.score) as avg_score,
                MIN(ea.score) as min_score,
                MAX(ea.score) as max_score,
                COUNT(DISTINCT CASE WHEN ea.score >= e.passing_score THEN ea.id END) as passed_exams
                FROM exam_attempts ea
                JOIN exams e ON ea.exam_id = e.id
                WHERE ea.user_id = :student_id 
                AND e.created_by = :teacher_id
                AND ea.status = 'completed'";

if ($selected_subject !== 'All Subjects') {
    $stats_query .= " AND e.subject = :subject";
}

$stmt = $db->prepare($stats_query);
$params = [
    ':student_id' => $student_id,
    ':teacher_id' => $_SESSION['teacher_id']
];

if ($selected_subject !== 'All Subjects') {
    $params[':subject'] = $selected_subject;
}

$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get exam history for exams created by this teacher
$history_query = "SELECT ea.*, e.title as exam_title, e.subject, e.passing_score
                 FROM exam_attempts ea
                 JOIN exams e ON ea.exam_id = e.id
                 WHERE ea.user_id = :student_id
                 AND e.created_by = :teacher_id";

if ($selected_subject !== 'All Subjects') {
    $history_query .= " AND e.subject = :subject";
}

$history_query .= " ORDER BY ea.start_time DESC";

$stmt = $db->prepare($history_query);
$stmt->execute($params);
$exam_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly performance data for chart
$monthly_query = "SELECT 
                  DATE_FORMAT(ea.start_time, '%Y-%m') as month,
                  AVG(ea.score) as avg_score,
                  COUNT(*) as exam_count
                  FROM exam_attempts ea
                  JOIN exams e ON ea.exam_id = e.id
                  WHERE ea.user_id = :student_id 
                  AND ea.status = 'completed'
                  AND e.created_by = :teacher_id";

if ($selected_subject !== 'All Subjects') {
    $monthly_query .= " AND e.subject = :subject";
}

$monthly_query .= " GROUP BY DATE_FORMAT(ea.start_time, '%Y-%m')
                    ORDER BY month DESC
                    LIMIT 12";

$stmt = $db->prepare($monthly_query);
$stmt->execute($params);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subject performance
$subject_query = "SELECT 
                  e.subject,
                  COUNT(DISTINCT ea.id) as total_exams,
                  AVG(ea.score) as avg_score,
                  COUNT(DISTINCT CASE WHEN ea.score >= e.passing_score THEN ea.id END) as passed_exams
                  FROM exam_attempts ea
                  JOIN exams e ON ea.exam_id = e.id
                  WHERE ea.user_id = :student_id
                  AND e.created_by = :teacher_id
                  AND ea.status = 'completed'
                  GROUP BY e.subject
                  ORDER BY avg_score DESC";
$stmt = $db->prepare($subject_query);
$stmt->execute([
    ':student_id' => $student_id,
    ':teacher_id' => $_SESSION['teacher_id']
]);
$subject_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            transition: transform 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
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
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <div>
                        <h1 class="h2">Student Performance</h1>
                        <p class="text-muted mb-0">
                            <?php echo htmlspecialchars($student['username']); ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="subject-selector">
                            <form method="GET" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="id" value="<?php echo $student_id; ?>">
                                <select name="subject" class="form-select" onchange="this.form.submit()">
                                    <option value="All Subjects">All Subjects</option>
                                    <?php foreach ($teacher_subjects as $subject): ?>
                                        <option value="<?php echo htmlspecialchars($subject['subject']); ?>"
                                                <?php echo $selected_subject === $subject['subject'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
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
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></p>
                                <p><strong>Status:</strong> Active</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class='bx bxs-book-content text-primary'></i>
                            <h6 class="text-muted">Total Exams</h6>
                            <h3><?php echo $stats['total_exams']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class='bx bxs-bar-chart-alt-2 text-success'></i>
                            <h6 class="text-muted">Average Score</h6>
                            <h3><?php echo $stats['avg_score'] ? round($stats['avg_score'], 1) . '%' : 'N/A'; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class='bx bxs-check-circle text-info'></i>
                            <h6 class="text-muted">Passed Exams</h6>
                            <h3><?php echo $stats['passed_exams']; ?> / <?php echo $stats['total_exams']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class='bx bxs-trophy text-warning'></i>
                            <h6 class="text-muted">Best Score</h6>
                            <h3><?php echo $stats['max_score'] ? round($stats['max_score'], 1) . '%' : 'N/A'; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Subject Performance -->
                <?php if (!empty($subject_performance)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Performance by Subject</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Total Exams</th>
                                        <th>Passed Exams</th>
                                        <th>Average Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subject_performance as $perf): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($perf['subject']); ?></td>
                                        <td><?php echo $perf['total_exams']; ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo $perf['passed_exams']; ?> / <?php echo $perf['total_exams']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo getScoreClass($perf['avg_score']); ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo round($perf['avg_score']); ?>%"
                                                     aria-valuenow="<?php echo round($perf['avg_score']); ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo round($perf['avg_score'], 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

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
                                        <th>Subject</th>
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
                                        <td><?php echo htmlspecialchars($attempt['subject']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($attempt['start_time'])); ?></td>
                                        <td>
                                            <?php if ($attempt['score'] !== null): ?>
                                                <span class="badge bg-<?php echo getScoreClass($attempt['score']); ?>">
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
                                                <i class='bx bx-show'></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function getScoreClass(score) {
            if (score >= 70) return 'success';
            if (score >= 50) return 'primary';
            return 'danger';
        }

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