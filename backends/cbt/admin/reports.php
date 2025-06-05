<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get filter parameters
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;
$application_type = isset($_GET['application_type']) ? $_GET['application_type'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Base query for students
$base_conditions = [];
$params = [];

if ($exam_id) {
    $base_conditions[] = "e.id = :exam_id";
    $params[':exam_id'] = $exam_id;
}

if ($application_type) {
    $base_conditions[] = "s.application_type = :application_type";
    $params[':application_type'] = $application_type;
}

if ($date_from) {
    $base_conditions[] = "DATE(ea.start_time) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $base_conditions[] = "DATE(ea.start_time) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = !empty($base_conditions) ? " AND " . implode(" AND ", $base_conditions) : "";

// Get all exams for filter
try {
    $exams_query = "SELECT id, title FROM exams ORDER BY title";
    $stmt = $db->prepare($exams_query);
    $stmt->execute();
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    $exams = [];
}

// Get all application types for filter
try {
    $app_types_query = "SELECT DISTINCT application_type FROM students ORDER BY application_type";
    $stmt = $db->prepare($app_types_query);
    $stmt->execute();
    $application_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching application types: " . $e->getMessage());
    $application_types = [];
}

// Get available classes for the filter
$classes_query = "SELECT DISTINCT class 
                 FROM students 
                 WHERE class IS NOT NULL 
                 ORDER BY class";

try {
    // Use prepared statement instead of direct query
    $stmt = $db->prepare($classes_query);
    $stmt->execute();
    $available_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $available_classes = [];
}

// Get statistics with proper error handling
$stats_query = "SELECT 
    MIN(ROUND((ea.score / (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) * 100), 2)) as lowest_score,
    MAX(ROUND((ea.score / (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) * 100), 2)) as highest_score,
    AVG(ROUND((ea.score / (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) * 100), 2)) as average_score,
    COUNT(*) as total_attempts,
    SUM(CASE WHEN (ea.score / (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) * 100) >= e.passing_score THEN 1 ELSE 0 END) as passed_count
FROM exam_attempts ea
JOIN exams e ON ea.exam_id = e.id
JOIN students s ON ea.student_id = s.id
WHERE ea.status = 'completed'" . $where_clause;

try {
    $stmt = $db->prepare($stats_query);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate pass percentage
    $pass_percentage = $stats['total_attempts'] > 0 
        ? round(($stats['passed_count'] / $stats['total_attempts']) * 100, 1)
        : 0;
} catch (PDOException $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    $stats = [
        'lowest_score' => 0,
        'highest_score' => 0,
        'average_score' => 0,
        'total_attempts' => 0,
        'passed_count' => 0
    ];
    $pass_percentage = 0;
}

// Single query for all students with proper error handling
try {
    $query = "SELECT 
                s.id as student_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.application_type,
                s.class,
                e.title as exam_title,
                (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as total_questions,
                ea.score as raw_score,
                ROUND((ea.score / (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) * 100), 2) as percentage,
                ea.start_time,
                ea.end_time,
                e.passing_score,
                CASE 
                    WHEN (ea.score / (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) * 100) >= e.passing_score 
                    THEN 'Pass' 
                    ELSE 'Fail' 
                END as status
              FROM students s
              JOIN exam_attempts ea ON s.id = ea.student_id
              JOIN exams e ON ea.exam_id = e.id
              WHERE ea.status = 'completed'" . $where_clause . "
              ORDER BY ea.start_time DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Successfully fetched " . count($reports) . " reports");
} catch (PDOException $e) {
    error_log("Error fetching reports: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Error Details: " . print_r($e->errorInfo, true));
    $reports = [];
    
    // Display error message on page
    echo "<div class='alert alert-danger'>";
    echo "<h4>Database Error:</h4>";
    echo "<pre>";
    echo "Error: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "SQL State: " . htmlspecialchars($e->getCode()) . "\n";
    echo "</pre>";
    echo "</div>";
}

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    if ($export_type === 'pdf') {
        require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(SITE_NAME);
        $pdf->SetTitle('Student Reports');
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Create the table header
        $html = '<h1>Student Performance Report</h1>
                <table border="1" cellpadding="4">
                <tr style="background-color: #f5f5f5;">
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Application Type</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>';
        
        // Add data rows
        foreach ($reports as $row) {
            $html .= '<tr>
                        <td>' . htmlspecialchars($row['student_name']) . '</td>
                        <td>' . htmlspecialchars($row['class']) . '</td>
                        <td>' . htmlspecialchars($row['application_type']) . '</td>
                        <td>' . htmlspecialchars($row['exam_title']) . '</td>
                        <td>' . $row['percentage'] . '%</td>
                        <td>' . $row['status'] . '</td>
                        <td>' . date('M d, Y H:i', strtotime($row['start_time'])) . '</td>
                    </tr>';
        }
        
        $html .= '</table>';
        
        // Print the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Close and output PDF document
        $pdf->Output('student_reports.pdf', 'D');
        exit();
    } 
    elseif ($export_type === 'excel') {
        // Set headers for Excel download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="student_reports.csv"');
        
        // Create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for proper Excel encoding
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Output header row
        fputcsv($output, ['Student Name', 'Class', 'Application Type', 'Exam', 'Score', 'Status', 'Date']);
        
        // Output data rows
        foreach ($reports as $row) {
            fputcsv($output, [
                $row['student_name'],
                $row['class'],
                $row['application_type'],
                $row['exam_title'],
                $row['percentage'] . '%',
                $row['status'],
                date('M d, Y H:i', strtotime($row['start_time']))
            ]);
        }
        
        fclose($output);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Reports - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .main-content {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Student Performance Reports</h1>
                    <div class="btn-group">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" class="btn btn-primary">
                            <i class='bx bxs-file-pdf'></i> Export PDF
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success">
                            <i class='bx bxs-file-export'></i> Export Excel
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Exam</label>
                                <select name="exam_id" class="form-select">
                                    <option value="">All Exams</option>
                                    <?php foreach ($exams as $exam): ?>
                                        <option value="<?php echo $exam['id']; ?>" 
                                                <?php echo $exam_id == $exam['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($exam['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Class</label>
                                <select name="class" class="form-select">
                                    <option value="">All Classes</option>
                                    <?php foreach ($available_classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class); ?>"
                                                <?php echo (isset($_GET['class']) && $_GET['class'] === $class) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Application Type</label>
                                <select name="application_type" class="form-select">
                                    <option value="">All Application Types</option>
                                    <?php foreach ($application_types as $app_type): ?>
                                        <option value="<?php echo $app_type; ?>" 
                                                <?php echo $application_type === $app_type ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($app_type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="reports.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Performance Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center p-3">
                                            <h6 class="text-muted mb-1">Lowest Score</h6>
                                            <h2 class="mb-0 text-danger"><?php echo number_format($stats['lowest_score'], 1); ?>%</h2>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3">
                                            <h6 class="text-muted mb-1">Average Score</h6>
                                            <h2 class="mb-0 text-primary"><?php echo number_format($stats['average_score'], 1); ?>%</h2>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3">
                                            <h6 class="text-muted mb-1">Highest Score</h6>
                                            <h2 class="mb-0 text-success"><?php echo number_format($stats['highest_score'], 1); ?>%</h2>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3">
                                            <h6 class="text-muted mb-1">Pass Rate</h6>
                                            <h2 class="mb-0 <?php echo $pass_percentage >= 70 ? 'text-success' : ($pass_percentage >= 50 ? 'text-primary' : 'text-danger'); ?>">
                                                <?php echo $pass_percentage; ?>%
                                            </h2>
                                            <small class="text-muted"><?php echo $stats['passed_count']; ?> of <?php echo $stats['total_attempts']; ?> attempts</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                style="width: <?php echo min($stats['lowest_score'], 100); ?>%" 
                                                title="Lowest: <?php echo number_format($stats['lowest_score'], 1); ?>%">
                                            </div>
                                            <div class="progress-bar bg-primary" role="progressbar" 
                                                style="width: <?php echo min($stats['average_score'] - $stats['lowest_score'], 100); ?>%" 
                                                title="Average: <?php echo number_format($stats['average_score'], 1); ?>%">
                                            </div>
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                style="width: <?php echo min($stats['highest_score'] - $stats['average_score'], 100); ?>%" 
                                                title="Highest: <?php echo number_format($stats['highest_score'], 1); ?>%">
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2">
                                            <small class="text-muted">0%</small>
                                            <small class="text-muted">Score Distribution</small>
                                            <small class="text-muted">100%</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th>Exam</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reports)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No reports found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($report['student_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($report['class'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($report['exam_title'] ?? ''); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($report['percentage'] ?? 0) >= $report['passing_score'] ? 'success' : 'danger'; ?>">
                                                        <?php echo number_format($report['percentage'] ?? 0, 2); ?>%
                                                        <small>(<?php echo $report['raw_score']; ?>/<?php echo $report['total_questions']; ?>)</small>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">Pass mark: <?php echo $report['passing_score']; ?>%</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($report['status'] ?? '') === 'Pass' ? 'success' : 'danger'; ?>">
                                                        <?php echo $report['status'] ?? 'N/A'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo isset($report['start_time']) ? date('M d, Y H:i', strtotime($report['start_time'])) : 'N/A'; ?></td>
                                                <td>
                                                    <?php
                                                    if (isset($report['end_time']) && isset($report['start_time'])) {
                                                        $start = new DateTime($report['start_time']);
                                                        $end = new DateTime($report['end_time']);
                                                        $duration = $start->diff($end);
                                                        echo $duration->format('%H:%I:%S');
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 