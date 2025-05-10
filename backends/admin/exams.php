<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$user = $auth->getCurrentUser();

// Add after Database::getInstance();
$mysqli = $db->getConnection();

// Get filters
$exam_type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT e.*, s.first_name, s.last_name, s.registration_number, s.application_type 
          FROM exam_results e 
          JOIN students s ON e.student_id = s.id 
          WHERE 1=1";
$params = [];

if ($exam_type) {
    $query .= " AND e.exam_type = ?";
    $params[] = $exam_type;
}

if ($status) {
    $query .= " AND e.status = ?";
    $params[] = $status;
}

if ($date_from) {
    $query .= " AND DATE(e.exam_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(e.exam_date) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $query .= " AND (s.registration_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$query .= " ORDER BY e.exam_date DESC";

// Execute query
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Calculate statistics
$total_exams = 0;
$total_passed = 0;
$total_failed = 0;
$total_pending = 0;
$average_score = 0;
$total_score = 0;

while ($row = $result->fetch_assoc()) {
    $total_exams++;
    $total_score += ($row['score'] / $row['total_score']) * 100;
    
    switch ($row['status']) {
        case 'passed':
            $total_passed++;
            break;
        case 'failed':
            $total_failed++;
            break;
        case 'pending':
            $total_pending++;
            break;
    }
}

$average_score = $total_exams > 0 ? $total_score / $total_exams : 0;

// Reset result pointer
$result->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams - <?php echo SCHOOL_NAME; ?></title>
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
                    <h2>Exams</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
                        <i class="bi bi-plus"></i> Add Exam Result
                    </button>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Exams</h5>
                                <h3 class="mb-0"><?php echo $total_exams; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Passed</h5>
                                <h3 class="mb-0"><?php echo $total_passed; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Failed</h5>
                                <h3 class="mb-0"><?php echo $total_failed; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Average Score</h5>
                                <h3 class="mb-0"><?php echo number_format($average_score, 1); ?>%</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="type" class="form-label">Exam Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="entrance" <?php echo $exam_type === 'entrance' ? 'selected' : ''; ?>>Entrance Exam</option>
                                    <option value="midterm" <?php echo $exam_type === 'midterm' ? 'selected' : ''; ?>>Midterm Exam</option>
                                    <option value="final" <?php echo $exam_type === 'final' ? 'selected' : ''; ?>>Final Exam</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="passed" <?php echo $status === 'passed' ? 'selected' : ''; ?>>Passed</option>
                                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search...">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Exams List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Exam Type</th>
                                        <th>Score</th>
                                        <th>Total Score</th>
                                        <th>Percentage</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i', strtotime($row['exam_date'])); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($row['registration_number']); ?></small>
                                                </td>
                                                <td><?php echo ucfirst($row['exam_type']); ?></td>
                                                <td><?php echo $row['score']; ?></td>
                                                <td><?php echo $row['total_score']; ?></td>
                                                <td><?php echo number_format(($row['score'] / $row['total_score']) * 100, 1); ?>%</td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['status'] === 'passed' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="exam_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <a href="edit_exam.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteExam(<?php echo $row['id']; ?>)">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No exam results found</td>
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

    <!-- Add Exam Modal -->
    <div class="modal fade" id="addExamModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Exam Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addExamForm">
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Student</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php
                                $students = $db->query("SELECT id, registration_number, first_name, last_name FROM students WHERE status = 'registered' ORDER BY first_name, last_name");
                                while ($student = $students->fetch_assoc()) {
                                    echo '<option value="' . $student['id'] . '">' . 
                                         htmlspecialchars($student['registration_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']) . 
                                         '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="exam_type" class="form-label">Exam Type</label>
                            <select class="form-select" id="exam_type" name="exam_type" required>
                                <option value="">Select Type</option>
                                <option value="entrance">Entrance Exam</option>
                                <option value="midterm">Midterm Exam</option>
                                <option value="final">Final Exam</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="exam_date" class="form-label">Exam Date</label>
                            <input type="datetime-local" class="form-control" id="exam_date" name="exam_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="score" class="form-label">Score</label>
                            <input type="number" class="form-control" id="score" name="score" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_score" class="form-label">Total Score</label>
                            <input type="number" class="form-control" id="total_score" name="total_score" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="passed">Passed</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveExam()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function saveExam() {
            const form = document.getElementById('addExamForm');
            const formData = new FormData(form);
            
            fetch('save_exam.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Exam result added successfully!');
                    window.location.reload();
                } else {
                    alert(data.message || 'An error occurred while adding exam result.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding exam result.');
            });
        }

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
                        window.location.reload();
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