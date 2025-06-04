<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

session_start();

// Check admin authentication
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit();
// }

$db = Database::getInstance()->getConnection();

// Get summary statistics
$stats_query = "SELECT 
    COUNT(DISTINCT CASE WHEN ea.status = 'completed' THEN ea.user_id END) as total_students,
    COUNT(DISTINCT CASE WHEN ea.status = 'completed' THEN ea.exam_id END) as total_exams,
    ROUND(AVG(CASE WHEN ea.status = 'completed' THEN ea.score END), 1) as avg_score,
    COUNT(DISTINCT CASE WHEN ea.status = 'completed' AND ea.score >= e.passing_score THEN ea.id END) as passed_exams,
    COUNT(DISTINCT CASE WHEN ea.status = 'completed' AND ea.score < e.passing_score THEN ea.id END) as failed_exams,
    MAX(CASE WHEN ea.status = 'completed' THEN ea.score END) as highest_score,
    MIN(CASE WHEN ea.status = 'completed' THEN ea.score END) as lowest_score
FROM exam_attempts ea
JOIN exams e ON ea.exam_id = e.id
WHERE 1=1";

// Apply filters to statistics
$stats_params = [];
if ($exam_id) {
    $stats_query .= " AND e.id = :exam_id";
    $stats_params[':exam_id'] = $exam_id;
}
if ($department) {
    $stats_query .= " AND (
        EXISTS (SELECT 1 FROM morning_students ms WHERE ms.id = ea.user_id AND ms.department = :dept_m)
        OR 
        EXISTS (SELECT 1 FROM afternoon_students afs WHERE afs.id = ea.user_id AND afs.department = :dept_a)
    )";
    $stats_params[':dept_m'] = $department;
    $stats_params[':dept_a'] = $department;
}
if ($session === 'morning') {
    $stats_query .= " AND EXISTS (SELECT 1 FROM morning_students ms WHERE ms.id = ea.user_id)";
} elseif ($session === 'afternoon') {
    $stats_query .= " AND EXISTS (SELECT 1 FROM afternoon_students afs WHERE afs.id = ea.user_id)";
}
if ($date_from) {
    $stats_query .= " AND DATE(ea.start_time) >= :date_from";
    $stats_params[':date_from'] = $date_from;
}
if ($date_to) {
    $stats_query .= " AND DATE(ea.start_time) <= :date_to";
    $stats_params[':date_to'] = $date_to;
}

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute($stats_params);
$statistics = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get filter parameters
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;
$department = isset($_GET['department']) ? $_GET['department'] : null;
$session = isset($_GET['session']) ? $_GET['session'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Base query for both morning and afternoon students
$base_conditions = [];
$params = [];

if ($exam_id) {
    $base_conditions[] = "e.id = :exam_id";
    $params[':exam_id'] = $exam_id;
}

if ($department) {
    $base_conditions[] = "department = :department";
    $params[':department'] = $department;
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

if ($session === 'morning') {
    $query = "SELECT 
                ms.id as student_id,
                ms.fullname as student_name,
                ms.department,
                'Morning' as session,
                e.title as exam_title,
                ea.score as score,
                ea.start_time,
                ea.end_time,
                e.passing_score,
                CASE 
                    WHEN ea.status = 'completed' AND ea.score >= e.passing_score THEN 'Pass'
                    WHEN ea.status = 'completed' AND ea.score < e.passing_score THEN 'Fail'
                    ELSE ea.status
                END as status,
                TIMESTAMPDIFF(SECOND, ea.start_time, COALESCE(ea.end_time, NOW())) as duration_seconds
              FROM morning_students ms
              JOIN exam_attempts ea ON ms.id = ea.user_id
              JOIN exams e ON ea.exam_id = e.id
              WHERE 1=1" . $where_clause . "
              ORDER BY ea.start_time DESC";
} elseif ($session === 'afternoon') {
    $query = "SELECT 
                afs.id as student_id,
                afs.fullname as student_name,
                afs.department,
                'Afternoon' as session,
                e.title as exam_title,
                ea.score as score,
                ea.start_time,
                ea.end_time,
                e.passing_score,
                CASE 
                    WHEN ea.status = 'completed' AND ea.score >= e.passing_score THEN 'Pass'
                    WHEN ea.status = 'completed' AND ea.score < e.passing_score THEN 'Fail'
                    ELSE ea.status
                END as status,
                TIMESTAMPDIFF(SECOND, ea.start_time, COALESCE(ea.end_time, NOW())) as duration_seconds
              FROM afternoon_students afs
              JOIN exam_attempts ea ON afs.id = ea.user_id
              JOIN exams e ON ea.exam_id = e.id
              WHERE 1=1" . $where_clause . "
              ORDER BY ea.start_time DESC";
} else {
    $query = "(SELECT 
                ms.id as student_id,
                ms.fullname as student_name,
                ms.department,
                'Morning' as session,
                e.title as exam_title,
                ea.score as score,
                ea.start_time,
                ea.end_time,
                e.passing_score,
                CASE 
                    WHEN ea.status = 'completed' AND ea.score >= e.passing_score THEN 'Pass'
                    WHEN ea.status = 'completed' AND ea.score < e.passing_score THEN 'Fail'
                    ELSE ea.status
                END as status,
                TIMESTAMPDIFF(SECOND, ea.start_time, COALESCE(ea.end_time, NOW())) as duration_seconds
              FROM morning_students ms
              JOIN exam_attempts ea ON ms.id = ea.user_id
              JOIN exams e ON ea.exam_id = e.id
              WHERE 1=1" . $where_clause . ")
              UNION ALL
              (SELECT 
                afs.id as student_id,
                afs.fullname as student_name,
                afs.department,
                'Afternoon' as session,
                e.title as exam_title,
                ea.score as score,
                ea.start_time,
                ea.end_time,
                e.passing_score,
                CASE 
                    WHEN ea.status = 'completed' AND ea.score >= e.passing_score THEN 'Pass'
                    WHEN ea.status = 'completed' AND ea.score < e.passing_score THEN 'Fail'
                    ELSE ea.status
                END as status,
                TIMESTAMPDIFF(SECOND, ea.start_time, COALESCE(ea.end_time, NOW())) as duration_seconds
              FROM afternoon_students afs
              JOIN exam_attempts ea ON afs.id = ea.user_id
              JOIN exams e ON ea.exam_id = e.id
              WHERE 1=1" . $where_clause . ")
              ORDER BY start_time DESC";
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all exams for filter
$exams_query = "SELECT id, title FROM exams ORDER BY title";
$exams_stmt = $db->query($exams_query);
$exams = $exams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all departments for filter
$dept_query = "(SELECT DISTINCT department FROM morning_students)
               UNION
               (SELECT DISTINCT department FROM afternoon_students)
               ORDER BY department";
$dept_stmt = $db->query($dept_query);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

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
                    <th>Session</th>
                    <th>Department</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>';
        
        // Add data rows
        foreach ($reports as $row) {
            $html .= '<tr>
                        <td>' . htmlspecialchars($row['student_name']) . '</td>
                        <td>' . htmlspecialchars($row['session']) . '</td>
                        <td>' . htmlspecialchars($row['department']) . '</td>
                        <td>' . htmlspecialchars($row['exam_title']) . '</td>
                        <td>' . $row['score'] . '%</td>
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
        fputcsv($output, ['Student Name', 'Session', 'Department', 'Exam', 'Score', 'Status', 'Date']);
        
        // Output data rows
        foreach ($reports as $row) {
            fputcsv($output, [
                $row['student_name'],
                $row['session'],
                $row['department'],
                $row['exam_title'],
                $row['score'] . '%',
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
                                <label class="form-label">Session</label>
                                <select name="session" class="form-select">
                                    <option value="">All Sessions</option>
                                    <option value="morning" <?php echo $session === 'morning' ? 'selected' : ''; ?>>Morning</option>
                                    <option value="afternoon" <?php echo $session === 'afternoon' ? 'selected' : ''; ?>>Afternoon</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select name="department" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept; ?>" 
                                                <?php echo $department === $dept ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
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

                <!-- Statistics Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Students</h6>
                                <h2 class="mb-0"><?php echo $statistics['total_students']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Exams Taken</h6>
                                <h2 class="mb-0"><?php echo $statistics['total_exams']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Passed Exams</h6>
                                <h2 class="mb-0"><?php echo $statistics['passed_exams']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6 class="card-title">Failed Exams</h6>
                                <h2 class="mb-0"><?php echo $statistics['failed_exams']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Average Score</h6>
                                <h2 class="mb-0"><?php echo $statistics['avg_score']; ?>%</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Highest Score</h6>
                                <h2 class="mb-0"><?php echo $statistics['highest_score']; ?>%</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Lowest Score</h6>
                                <h2 class="mb-0"><?php echo $statistics['lowest_score']; ?>%</h2>
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
                                        <th>Session</th>
                                        <th>Department</th>
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
                                                <td><?php echo htmlspecialchars($report['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($report['session']); ?></td>
                                                <td><?php echo htmlspecialchars($report['department']); ?></td>
                                                <td><?php echo htmlspecialchars($report['exam_title']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $report['score'] >= $report['passing_score'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $report['score']; ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $report['status'] === 'Pass' ? 'success' : ($report['status'] === 'Fail' ? 'danger' : 'warning'); ?>">
                                                        <?php echo $report['status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($report['start_time'])); ?></td>
                                                <td>
                                                    <?php
                                                    if ($report['duration_seconds'] !== null) {
                                                        $hours = floor($report['duration_seconds'] / 3600);
                                                        $minutes = floor(($report['duration_seconds'] % 3600) / 60);
                                                        $seconds = $report['duration_seconds'] % 60;
                                                        printf('%02d:%02d:%02d', $hours, $minutes, $seconds);
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