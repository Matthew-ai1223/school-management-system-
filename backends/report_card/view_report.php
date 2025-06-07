<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Check if user is logged in
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header('Location: ../unauthorized.php');
//     exit();
// }

$message = '';
$report_card = null;
$report_details = [];
$all_reports = [];
$selected_class = isset($_GET['class']) ? $_GET['class'] : '';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Get unique classes for filter
    $sql = "SELECT DISTINCT class FROM report_cards ORDER BY class";
    $result = $conn->query($sql);
    $classes = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row['class'];
        }
    }

    // Fetch all report cards with class filter
    $sql = "SELECT rc.*, 
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.registration_number,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM report_cards rc
            LEFT JOIN students s ON rc.student_id = s.id
            LEFT JOIN teachers t ON rc.created_by = t.id";
    
    if ($selected_class) {
        $selected_class = $conn->real_escape_string($selected_class);
        $sql .= " WHERE rc.class = '$selected_class'";
    }
    
    $sql .= " ORDER BY rc.class, rc.created_at DESC";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_reports[] = $row;
        }
    }

    // Group reports by class
    $grouped_reports = [];
    foreach ($all_reports as $report) {
        $class = $report['class'];
        if (!isset($grouped_reports[$class])) {
            $grouped_reports[$class] = [];
        }
        $grouped_reports[$class][] = $report;
    }

    // If specific report card is requested
    if (isset($_GET['id'])) {
        $report_id = $conn->real_escape_string($_GET['id']);
        
        // Fetch specific report card
        $sql = "SELECT rc.*, 
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.registration_number,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM report_cards rc
                LEFT JOIN students s ON rc.student_id = s.id
                LEFT JOIN teachers t ON rc.created_by = t.id
                WHERE rc.id = '$report_id'";
        
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $report_card = $result->fetch_assoc();

            // Fetch report details
            $sql = "SELECT rcd.*, rs.subject_name
                    FROM report_card_details rcd
                    LEFT JOIN report_subjects rs ON rcd.subject_id = rs.id
                    WHERE rcd.report_card_id = '$report_id'
                    ORDER BY rs.subject_name";
            
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $report_details[] = $row;
                }
            }
        }
    }
} catch(Exception $e) {
    $message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Cards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            .card {
                border: none !important;
            }
            .card-header {
                background-color: #fff !important;
            }
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .class-section {
            margin-bottom: 2rem;
        }
        .class-header {
            background-color: #f8f9fa;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-danger"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- List of all report cards -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Report Cards</h3>
                    <div>
                        <form class="d-inline-block me-2">
                            <select name="class" class="form-select" onchange="this.form.submit()">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>" 
                                            <?php echo $selected_class === $class ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <a href="generate_report.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Generate New Report
                        </a>
                        <a href="../cbt/admin/dashboard.php" style="background-color:rgb(255, 183, 0); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">Back</a>
                   
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($grouped_reports)): ?>
                    <div class="alert alert-info">No report cards found.</div>
                <?php else: ?>
                    <?php foreach ($grouped_reports as $class => $reports): ?>
                        <div class="class-section">
                            <div class="class-header">
                                <h4 class="mb-0"><?php echo htmlspecialchars($class); ?></h4>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Term</th>
                                            <th>Year</th>
                                            <th>Teacher</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($report['student_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($report['term']); ?></td>
                                            <td><?php echo htmlspecialchars($report['academic_year']); ?></td>
                                            <td><?php echo htmlspecialchars($report['teacher_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                            <td>
                                                <a href="?id=<?php echo $report['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="edit_report.php?id=<?php echo $report['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="download_pdf.php?id=<?php echo $report['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i> PDF
                                                </a>
                                                <button class="btn <?php echo $report['allow_download'] ? 'btn-success' : 'btn-secondary'; ?> btn-sm" 
                                                        onclick="toggleDownloadPermission(<?php echo $report['id']; ?>, <?php echo $report['allow_download'] ? 'false' : 'true'; ?>)">
                                                    <i class="fas <?php echo $report['allow_download'] ? 'fa-lock-open' : 'fa-lock'; ?>"></i>
                                                    <?php echo $report['allow_download'] ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($report_card): ?>
            <!-- Specific Report Card View -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Report Card Details</h3>
                        <div>
                            <a href="edit_report.php?id=<?php echo $report_card['id']; ?>" class="btn btn-warning me-2">
                                <i class="fas fa-edit"></i> Edit Report
                            </a>
                            <a href="download_pdf.php?id=<?php echo $report_card['id']; ?>" class="btn btn-info me-2">
                                <i class="fas fa-download"></i> Download PDF
                            </a>
                            <button class="btn btn-primary me-2" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn <?php echo $report_card['allow_download'] ? 'btn-success' : 'btn-secondary'; ?>" 
                                    onclick="toggleDownloadPermission(<?php echo $report_card['id']; ?>, <?php echo $report_card['allow_download'] ? 'false' : 'true'; ?>)">
                                <i class="fas <?php echo $report_card['allow_download'] ? 'fa-lock-open' : 'fa-lock'; ?>"></i>
                                <?php echo $report_card['allow_download'] ? 'Disable Download' : 'Enable Download'; ?>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- School Information -->
                    <div class="text-center mb-4">
                        <h2><?php echo htmlspecialchars($report_card['school_name']); ?></h2>
                        <h4>Student Report Card</h4>
                    </div>

                    <!-- Student Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($report_card['student_name'] ?? 'N/A'); ?></p>
                            <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($report_card['registration_number'] ?? 'N/A'); ?></p>
                            <p><strong>Class:</strong> <?php echo htmlspecialchars($report_card['class']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Academic Year:</strong> <?php echo htmlspecialchars($report_card['academic_year']); ?></p>
                            <p><strong>Term:</strong> <?php echo htmlspecialchars($report_card['term']); ?></p>
                            <p><strong>Date Generated:</strong> <?php echo date('F d, Y', strtotime($report_card['created_at'])); ?></p>
                        </div>
                    </div>

                    <!-- Academic Performance -->
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Test Score (30%)</th>
                                    <th>Exam Score (70%)</th>
                                    <th>Total</th>
                                    <th>Grade</th>
                                    <th>Remark</th>
                                    <th>Comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($report_details)): ?>
                                    <?php foreach ($report_details as $detail): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($detail['subject_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($detail['test_score'], 1); ?></td>
                                        <td><?php echo number_format($detail['exam_score'], 1); ?></td>
                                        <td><?php echo number_format($detail['total_score'], 1); ?></td>
                                        <td><?php echo htmlspecialchars($detail['grade'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($detail['remark'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($detail['teacher_comment'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No subject details found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3"><strong>Total Score</strong></td>
                                    <td colspan="4"><strong><?php echo number_format($report_card['total_score'], 1); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="3"><strong>Average Score</strong></td>
                                    <td colspan="4"><strong><?php echo number_format($report_card['average_score'], 1); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="3"><strong>Position in Class</strong></td>
                                    <td colspan="4"><strong><?php echo $report_card['position_in_class']; ?> out of <?php echo $report_card['total_students']; ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Comments -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Teacher's Comment</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($report_card['teacher_comment'])): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($report_card['teacher_comment'])); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0"><em>No comment provided</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Principal's Comment</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($report_card['principal_comment'])): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($report_card['principal_comment'])); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0"><em>No comment provided</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures -->
                    <div class="row mt-5">
                        <div class="col-md-4 text-center">
                            <p>Class Teacher's Signature</p>
                            <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                            <p class="mt-2"><?php echo htmlspecialchars($report_card['teacher_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-4 text-center">
                            <p>Principal's Signature</p>
                            <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                        </div>
                        <div class="col-md-4 text-center">
                            <p>Parent's Signature</p>
                            <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleDownloadPermission(reportId, enable) {
        if (confirm('Are you sure you want to ' + (enable ? 'enable' : 'disable') + ' download permission for this report card?')) {
            fetch('toggle_download.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'report_id=' + reportId + '&enable=' + (enable ? '1' : '0')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating download permission');
            });
        }
    }
    </script>
</body>
</html> 