<?php
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'class_teacher')) {
    header("Location: login.php");
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get teacher details
$teacherId = $_SESSION['teacher_id'];
$teacherName = isset($_SESSION['first_name']) && isset($_SESSION['last_name']) 
    ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name']
    : (isset($_SESSION['name']) ? $_SESSION['name'] : 'Unknown Teacher');

// Get exam ID from URL if provided
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;

// Get all exams created by this teacher
$examsQuery = "SELECT * FROM cbt_exams WHERE teacher_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($examsQuery);
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$examsResult = $stmt->get_result();
$exams = [];

while ($row = $examsResult->fetch_assoc()) {
    $exams[] = $row;
}

// Get results for specific exam if exam_id is provided
$results = [];
if ($examId) {
    $resultsQuery = "SELECT 
        se.id as attempt_id,
        se.student_id,
        s.first_name,
        s.last_name,
        s.registration_number,
        se.started_at,
        se.submitted_at,
        se.score,
        (SELECT COUNT(*) FROM cbt_questions WHERE exam_id = e.id) as total_questions,
        se.status,
        e.title as exam_title,
        e.subject,
        e.class
    FROM cbt_student_exams se
    JOIN students s ON se.student_id = s.id
    JOIN cbt_exams e ON se.exam_id = e.id
    WHERE se.exam_id = ?
    ORDER BY se.score DESC";

    $stmt = $conn->prepare($resultsQuery);
    $stmt->bind_param("i", $examId);
    $stmt->execute();
    $resultsResult = $stmt->get_result();

    while ($row = $resultsResult->fetch_assoc()) {
        $results[] = $row;
    }
}

// Page title
$pageTitle = "Exam Results";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $pageTitle; ?> - ACE COLLEGE</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    
    <!-- Custom styles -->
    <style>
        :root {
            --primary: #4e73df;
            --secondary: #f6c23e;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            padding: 1rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border: none;
            border-radius: 0.35rem;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
        }
        
        .result-card {
            transition: transform 0.2s;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
        }
        
        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 auto;
        }
        
        .score-excellent {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
            color: white;
        }
        
        .score-good {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
        }
        
        .score-average {
            background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
            color: white;
        }
        
        .score-poor {
            background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
            color: white;
        }
        
        .table th {
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #224abe 0%, #1a3a9c 100%);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-laptop-code mr-2"></i> ACE COLLEGE - CBT System
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars($teacherName); ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-bar mr-2"></i> Exam Results
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Exam Selection -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <form method="get" class="form-inline">
                                    <select name="exam_id" class="form-control mr-2" style="min-width: 300px;">
                                        <option value="">Select an Exam</option>
                                        <?php foreach ($exams as $exam): ?>
                                            <option value="<?php echo $exam['id']; ?>" <?php echo ($examId == $exam['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($exam['title'] . ' - ' . $exam['subject'] . ' (' . $exam['class'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search mr-1"></i> View Results
                                    </button>
                                </form>
                            </div>
                            <?php if ($examId): ?>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-success" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel mr-1"></i> Export to Excel
                                </button>
                                <button class="btn btn-danger" onclick="printResults()">
                                    <i class="fas fa-print mr-1"></i> Print Results
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($examId && !empty($results)): ?>
                            <!-- Results Summary -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">Total Students</h6>
                                            <h2 class="mb-0"><?php echo count($results); ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">Average Score</h6>
                                            <h2 class="mb-0">
                                                <?php
                                                $totalScore = 0;
                                                foreach ($results as $result) {
                                                    $totalScore += $result['score'];
                                                }
                                                echo round($totalScore / count($results), 1);
                                                ?>%
                                            </h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">Highest Score</h6>
                                            <h2 class="mb-0"><?php echo max(array_column($results, 'score')); ?>%</h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">Lowest Score</h6>
                                            <h2 class="mb-0"><?php echo min(array_column($results, 'score')); ?>%</h2>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Results Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered" id="resultsTable">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Student Name</th>
                                            <th>Registration Number</th>
                                            <th>Score</th>
                                            <th>Total Questions</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rank = 1;
                                        foreach ($results as $result): 
                                            $duration = 0;
                                            if ($result['submitted_at'] && $result['started_at']) {
                                                $duration = strtotime($result['submitted_at']) - strtotime($result['started_at']);
                                            }
                                            $minutes = floor($duration / 60);
                                            $seconds = $duration % 60;
                                        ?>
                                            <tr>
                                                <td><?php echo $rank++; ?></td>
                                                <td><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($result['registration_number']); ?></td>
                                                <td>
                                                    <div class="score-circle <?php
                                                        if ($result['score'] >= 70) echo 'score-excellent';
                                                        elseif ($result['score'] >= 60) echo 'score-good';
                                                        elseif ($result['score'] >= 50) echo 'score-average';
                                                        else echo 'score-poor';
                                                    ?>">
                                                        <?php echo $result['score']; ?>%
                                                    </div>
                                                </td>
                                                <td><?php echo $result['total_questions']; ?></td>
                                                <td><?php echo $minutes . 'm ' . $seconds . 's'; ?></td>
                                                <td>
                                                    <?php if ($result['status'] == 'completed'): ?>
                                                        <span class="badge badge-success">Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">Incomplete</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $result['attempt_id']; ?>)">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif ($examId): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> No results found for this exam.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> Please select an exam to view results.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JavaScript -->
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#resultsTable').DataTable({
            "order": [[0, "asc"]], // Sort by rank
            "pageLength": 25
        });
    });

    function viewDetails(attemptId) {
        // Implement view details functionality
        window.location.href = 'view_attempt_details.php?attempt_id=' + attemptId;
    }

    function exportToExcel() {
        // Implement Excel export functionality
        window.location.href = 'export_results.php?exam_id=<?php echo $examId; ?>';
    }

    function printResults() {
        window.print();
    }
    </script>
</body>
</html>