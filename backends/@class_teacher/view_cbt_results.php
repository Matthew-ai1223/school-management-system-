<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if class teacher is logged in
if (!isset($_SESSION['class_teacher_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

$class_teacher_id = $_SESSION['class_teacher_id'];
$teacher_id = $_SESSION['teacher_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Get exam ID from URL
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header('Location: manage_cbt_exams.php');
    exit;
}

// Verify that this exam belongs to the current teacher
$examQuery = "SELECT e.*, s.name AS subject_name, c.name AS class_name 
              FROM cbt_exams e
              JOIN subjects s ON e.subject_id = s.id
              JOIN classes c ON e.class_id = c.id
              WHERE e.id = ? AND e.teacher_id = ?";
$stmt = $conn->prepare($examQuery);
$stmt->bind_param("ii", $exam_id, $teacher_id);
$stmt->execute();
$examResult = $stmt->get_result();

if ($examResult->num_rows === 0) {
    $_SESSION['error_message'] = "You don't have permission to view this exam's results.";
    header('Location: manage_cbt_exams.php');
    exit;
}

$exam = $examResult->fetch_assoc();

// Get overall statistics for this exam
$statsQuery = "SELECT 
               COUNT(*) AS total_attempts,
               SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_attempts,
               SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
               AVG(CASE WHEN score IS NOT NULL THEN score ELSE 0 END) AS average_score,
               MAX(score) AS highest_score,
               MIN(CASE WHEN score > 0 THEN score ELSE NULL END) AS lowest_score,
               SUM(CASE WHEN score >= ? THEN 1 ELSE 0 END) AS passed_count
               FROM cbt_student_exams
               WHERE exam_id = ?";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("di", $exam['passing_score'], $exam_id);
$stmt->execute();
$statsResult = $stmt->get_result();
$stats = $statsResult->fetch_assoc();

// Get student results for this exam
$resultsQuery = "SELECT se.*, s.first_name, s.last_name, s.registration_number
                FROM cbt_student_exams se
                JOIN students s ON se.student_id = s.id
                WHERE se.exam_id = ?
                ORDER BY se.score DESC, se.submitted_at ASC";
$stmt = $conn->prepare($resultsQuery);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$resultsResult = $stmt->get_result();
$results = [];
while ($row = $resultsResult->fetch_assoc()) {
    $results[] = $row;
}

// Toggle result visibility if requested
$successMessage = '';
$errorMessage = '';
if (isset($_POST['toggle_visibility'])) {
    $show_results = $_POST['show_results'] ? 0 : 1;
    
    $updateQuery = "UPDATE cbt_exams SET show_results = ? WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("iii", $show_results, $exam_id, $teacher_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $successMessage = $show_results ? "Results are now visible to students." : "Results are now hidden from students.";
        $exam['show_results'] = $show_results;
    } else {
        $errorMessage = "Failed to update result visibility.";
    }
}

// Include header
include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Exam Results</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="manage_cbt_exams.php">CBT Exams</a></li>
                        <li class="breadcrumb-item active">Exam Results</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Success!</h5>
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>

            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        Exam: <?php echo htmlspecialchars($exam['title']); ?> | 
                        Subject: <?php echo htmlspecialchars($exam['subject_name']); ?> | 
                        Class: <?php echo htmlspecialchars($exam['class_name']); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p>
                                <strong>Time Limit:</strong> <?php echo $exam['time_limit']; ?> minutes<br>
                                <strong>Total Questions:</strong> <?php echo $exam['total_questions']; ?><br>
                                <strong>Passing Score:</strong> <?php echo $exam['passing_score']; ?>%<br>
                                <strong>Active Period:</strong> 
                                <?php echo date('M d, Y g:i A', strtotime($exam['start_datetime'])); ?> - 
                                <?php echo date('M d, Y g:i A', strtotime($exam['end_datetime'])); ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-right">
                            <form method="post" action="" class="mb-3">
                                <input type="hidden" name="show_results" value="<?php echo $exam['show_results'] ?? 0; ?>">
                                <button type="submit" name="toggle_visibility" class="btn btn-<?php echo ($exam['show_results'] ?? 0) ? 'warning' : 'success'; ?>">
                                    <i class="fas fa-<?php echo ($exam['show_results'] ?? 0) ? 'eye-slash' : 'eye'; ?>"></i>
                                    <?php echo ($exam['show_results'] ?? 0) ? 'Hide Results from Students' : 'Show Results to Students'; ?>
                                </button>
                            </form>
                            <a href="manage_cbt_exams.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Exams
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Exam Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box bg-info">
                                        <span class="info-box-icon"><i class="fas fa-users"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total Attempts</span>
                                            <span class="info-box-number"><?php echo $stats['total_attempts']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box bg-success">
                                        <span class="info-box-icon"><i class="fas fa-check"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Completed</span>
                                            <span class="info-box-number"><?php echo $stats['completed_attempts']; ?></span>
                                            <?php if ($stats['total_attempts'] > 0): ?>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo ($stats['completed_attempts'] / $stats['total_attempts']) * 100; ?>%"></div>
                                            </div>
                                            <span class="progress-description">
                                                <?php echo round(($stats['completed_attempts'] / $stats['total_attempts']) * 100); ?>% Completion Rate
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box bg-warning">
                                        <span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Average Score</span>
                                            <span class="info-box-number"><?php echo number_format($stats['average_score'], 1); ?>%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box bg-danger">
                                        <span class="info-box-icon"><i class="fas fa-trophy"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Pass Rate</span>
                                            <?php if ($stats['completed_attempts'] > 0): ?>
                                            <span class="info-box-number">
                                                <?php echo round(($stats['passed_count'] / $stats['completed_attempts']) * 100); ?>%
                                            </span>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo ($stats['passed_count'] / $stats['completed_attempts']) * 100; ?>%"></div>
                                            </div>
                                            <span class="progress-description">
                                                <?php echo $stats['passed_count']; ?> out of <?php echo $stats['completed_attempts']; ?> passed
                                            </span>
                                            <?php else: ?>
                                            <span class="info-box-number">0%</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="card-title">Score Distribution</h3>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="scoreDistributionChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="card-title">Pass/Fail Rate</h3>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="passFailChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Student Results</h3>
                </div>
                <div class="card-body">
                    <?php if (count($results) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="resultsTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Registration No</th>
                                        <th>Start Time</th>
                                        <th>Submission Time</th>
                                        <th>Duration</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($result['registration_number']); ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($result['started_at'])); ?></td>
                                        <td>
                                            <?php echo $result['submitted_at'] 
                                                  ? date('M d, Y g:i A', strtotime($result['submitted_at'])) 
                                                  : '<span class="badge badge-warning">Not Submitted</span>'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($result['started_at'] && $result['submitted_at']) {
                                                $start = new DateTime($result['started_at']);
                                                $end = new DateTime($result['submitted_at']);
                                                $diff = $start->diff($end);
                                                echo $diff->format('%H:%I:%S');
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($result['score'] !== null): ?>
                                                <span class="badge badge-<?php echo ($result['score'] >= $exam['passing_score']) ? 'success' : 'danger'; ?>">
                                                    <?php echo $result['score']; ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Not Graded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch ($result['status']) {
                                                case 'Completed':
                                                    $statusClass = 'success';
                                                    break;
                                                case 'In Progress':
                                                    $statusClass = 'warning';
                                                    break;
                                                case 'Pending':
                                                    $statusClass = 'info';
                                                    break;
                                                default:
                                                    $statusClass = 'secondary';
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $statusClass; ?>">
                                                <?php echo $result['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_student_answers.php?exam_id=<?php echo $exam_id; ?>&student_exam_id=<?php echo $result['id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-search"></i> View Answers
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-info"></i> No results yet</h5>
                            <p>No students have attempted this exam yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable for results
    $('#resultsTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true
    });
    
    // Score distribution chart
    var scoreCtx = document.getElementById('scoreDistributionChart').getContext('2d');
    var scoreData = {
        labels: ['0-9%', '10-19%', '20-29%', '30-39%', '40-49%', '50-59%', '60-69%', '70-79%', '80-89%', '90-100%'],
        datasets: [{
            label: 'Number of Students',
            data: [
                <?php 
                $scoreBuckets = array_fill(0, 10, 0);
                foreach ($results as $result) {
                    if ($result['score'] !== null) {
                        $bucket = min(floor($result['score'] / 10), 9);
                        $scoreBuckets[$bucket]++;
                    }
                }
                echo implode(', ', $scoreBuckets);
                ?>
            ],
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(255, 205, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(201, 203, 207, 0.7)',
                'rgba(255, 99, 132, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(75, 192, 192, 0.7)'
            ]
        }]
    };
    
    new Chart(scoreCtx, {
        type: 'bar',
        data: scoreData,
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    precision: 0
                }
            }
        }
    });
    
    // Pass/Fail chart
    var passCtx = document.getElementById('passFailChart').getContext('2d');
    var passData = {
        labels: ['Passed', 'Failed', 'Not Completed'],
        datasets: [{
            data: [
                <?php echo $stats['passed_count']; ?>,
                <?php echo $stats['completed_attempts'] - $stats['passed_count']; ?>,
                <?php echo $stats['total_attempts'] - $stats['completed_attempts']; ?>
            ],
            backgroundColor: [
                'rgba(75, 192, 192, 0.7)',
                'rgba(255, 99, 132, 0.7)',
                'rgba(201, 203, 207, 0.7)'
            ]
        }]
    };
    
    new Chart(passCtx, {
        type: 'pie',
        data: passData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script> 