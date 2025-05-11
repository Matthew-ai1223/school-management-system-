<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();
$user = $auth->getCurrentUser();

// Get student ID from URL
$student_id = $_GET['id'] ?? 0;

// Fetch student details
$query = "SELECT * FROM students WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    header('Location: applications.php');
    exit;
}

// Fetch payment history
$query = "SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$payments = $stmt->get_result();

// Fetch exam results
$query = "SELECT * FROM exam_results WHERE student_id = ? ORDER BY exam_date DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$exam_results = $stmt->get_result();

// Handle PDF generation
if (isset($_GET['pdf'])) {
    $pdf = new PDFGenerator();
    $pdf->AliasNbPages();
    
    switch ($_GET['pdf']) {
        case 'application':
            $pdf->generateApplicationForm($student);
            break;
        case 'payments':
            foreach ($payments as $payment) {
                $payment['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $pdf->generatePaymentReceipt($payment);
            }
            break;
        case 'results':
            foreach ($exam_results as $result) {
                $result['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $pdf->generateExamResult($result);
            }
            break;
    }
    
    $pdf->Output();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - <?php echo SCHOOL_NAME; ?></title>
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
        .detail-card {
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Student Details</h2>
                    <div>
                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit Student
                        </a>
                        <a href="applications.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Applications
                        </a>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="card detail-card">
                    <div class="card-header">
                        <h4 class="mb-0">Student Information</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($student['registration_number']); ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                <p><strong>Application Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $student['application_type'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $student['status'] === 'pending' ? 'warning' : ($student['status'] === 'registered' ? 'success' : 'danger'); ?> status-badge">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Parent Name:</strong> <?php echo htmlspecialchars($student['parent_name']); ?></p>
                                <p><strong>Parent Phone:</strong> <?php echo htmlspecialchars($student['parent_phone']); ?></p>
                                <p><strong>Parent Email:</strong> <?php echo htmlspecialchars($student['parent_email']); ?></p>
                                <p><strong>Application Date:</strong> <?php echo date('F j, Y', strtotime($student['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="card detail-card">
                    <div class="card-header">
                        <h4 class="mb-0">Payment History</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($payments->num_rows > 0): ?>
                                        <?php while ($payment = $payments->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                                <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $payment['status'] === 'success' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($payment['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No payment history found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Exam Results -->
                <div class="card detail-card">
                    <div class="card-header">
                        <h4 class="mb-0">Exam Results</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Exam Date</th>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($exam_results->num_rows > 0): ?>
                                        <?php while ($result = $exam_results->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d', strtotime($result['exam_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($result['subject']); ?></td>
                                                <td><?php echo $result['score']; ?></td>
                                                <td><?php echo htmlspecialchars($result['grade']); ?></td>
                                                <td><?php echo htmlspecialchars($result['remarks']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No exam results found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 