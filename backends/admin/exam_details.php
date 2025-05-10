<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$user = $auth->getCurrentUser();

// Add after Database::getInstance();
$mysqli = $db->getConnection();

// Get exam ID
$exam_id = $_GET['id'] ?? '';

if (!$exam_id) {
    header('Location: exams.php');
    exit;
}

// Get exam details
$query = "SELECT e.*, s.first_name, s.last_name, s.registration_number, s.application_type 
          FROM exam_results e 
          JOIN students s ON e.student_id = s.id 
          WHERE e.id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: exams.php');
    exit;
}

$exam = $result->fetch_assoc();

// Handle PDF generation
if (isset($_GET['pdf'])) {
    $pdf = new PDFGenerator();
    $pdf->generateExamResult($exam);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Details - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <h3 class="mb-4"><?php echo SCHOOL_NAME; ?></h3>
                <div class="mb-4">
                    <p class="mb-1">Welcome,</p>
                    <h5><?php echo $user['name']; ?></h5>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a href="dashboard.php" class="nav-link">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="students.php" class="nav-link">
                            <i class="bi bi-people"></i> Students
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="applications.php" class="nav-link">
                            <i class="bi bi-file-text"></i> Applications
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="payments.php" class="nav-link">
                            <i class="bi bi-cash"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="exams.php" class="nav-link active">
                            <i class="bi bi-pencil-square"></i> Exams
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="users.php" class="nav-link">
                            <i class="bi bi-person"></i> Users
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="settings.php" class="nav-link">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a href="logout.php" class="nav-link text-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Exam Details</h2>
                    <div>
                        <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="exam_details.php?id=<?php echo $exam['id']; ?>&pdf=1" class="btn btn-info">
                            <i class="bi bi-download"></i> Download PDF
                        </a>
                        <button type="button" class="btn btn-danger" onclick="deleteExam(<?php echo $exam['id']; ?>)">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <div class="row">
                    <!-- Exam Information -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Exam Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <th>Exam ID</th>
                                        <td><?php echo $exam['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Exam Type</th>
                                        <td><?php echo ucfirst($exam['exam_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Exam Date</th>
                                        <td><?php echo date('Y-m-d H:i', strtotime($exam['exam_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Score</th>
                                        <td><?php echo $exam['score']; ?> / <?php echo $exam['total_score']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Percentage</th>
                                        <td><?php echo number_format(($exam['score'] / $exam['total_score']) * 100, 1); ?>%</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge bg-<?php echo $exam['status'] === 'passed' ? 'success' : ($exam['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($exam['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Created At</th>
                                        <td><?php echo date('Y-m-d H:i', strtotime($exam['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Student Information -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Student Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <th>Registration Number</th>
                                        <td><?php echo htmlspecialchars($exam['registration_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Student Name</th>
                                        <td><?php echo htmlspecialchars($exam['first_name'] . ' ' . $exam['last_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Application Type</th>
                                        <td><?php echo ucfirst($exam['application_type']); ?></td>
                                    </tr>
                                </table>
                                <a href="student_details.php?id=<?php echo $exam['student_id']; ?>" class="btn btn-info">
                                    <i class="bi bi-person"></i> View Student Details
                                </a>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <?php if ($exam['remarks']): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Remarks</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($exam['remarks'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteExam(id) {
            if (confirm('Are you sure you want to delete this exam result? This action cannot be undone.')) {
                fetch('delete_exam.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Exam result deleted successfully!');
                        window.location.href = 'exams.php';
                    } else {
                        alert(data.message || 'An error occurred while deleting exam result.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting exam result.');
                });
            }
        }
    </script>
</body>
</html> 