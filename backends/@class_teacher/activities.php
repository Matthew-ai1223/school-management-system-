<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get class teacher information
$userId = $_SESSION['user_id'];
$className = $_SESSION['class_name'] ?? '';

$teacherQuery = "SELECT ct.*, t.first_name, t.last_name
                FROM class_teachers ct
                JOIN teachers t ON ct.teacher_id = t.id
                JOIN users u ON ct.user_id = u.id
                WHERE ct.user_id = ? AND ct.is_active = 1";

$stmt = $conn->prepare($teacherQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Error: You are not assigned as a class teacher. Please contact the administrator.";
    exit;
}

$classTeacher = $result->fetch_assoc();
$classTeacherId = $classTeacher['id'];
$className = $className ?: $classTeacher['class_name']; // Use from session or DB

// Filter options
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$studentId = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get students list for dropdown filter
$studentsQuery = "SELECT s.id, s.first_name, s.last_name, s.registration_number
                 FROM students s
                 WHERE s.class = ?
                 ORDER BY s.first_name, s.last_name";

$stmt = $conn->prepare($studentsQuery);
$stmt->bind_param("s", $className);
$stmt->execute();
$studentsResult = $stmt->get_result();
$students = [];

while ($row = $studentsResult->fetch_assoc()) {
    $students[$row['id']] = $row;
}

// Build the activities query with filters
$activitiesQuery = "SELECT a.*, s.first_name, s.last_name, s.registration_number
                  FROM class_teacher_activities a
                  JOIN students s ON a.student_id = s.id
                  WHERE a.class_teacher_id = ?";

$params = [$classTeacherId];
$types = "i";

if (!empty($activityType)) {
    $activitiesQuery .= " AND a.activity_type = ?";
    $types .= "s";
    $params[] = $activityType;
}

if (!empty($dateFrom)) {
    $activitiesQuery .= " AND a.activity_date >= ?";
    $types .= "s";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $activitiesQuery .= " AND a.activity_date <= ?";
    $types .= "s";
    $params[] = $dateTo;
}

if (!empty($studentId)) {
    $activitiesQuery .= " AND a.student_id = ?";
    $types .= "i";
    $params[] = $studentId;
}

if (!empty($search)) {
    $activitiesQuery .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.registration_number LIKE ? OR a.description LIKE ?)";
    $types .= "ssss";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$activitiesQuery .= " ORDER BY a.activity_date DESC, a.created_at DESC";

$stmt = $conn->prepare($activitiesQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$activitiesResult = $stmt->get_result();
$activities = [];

while ($row = $activitiesResult->fetch_assoc()) {
    $activities[] = $row;
}

// Include header/dashboard template
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Student Activities - <?php echo $className; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item active">Activities</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clipboard-list mr-2"></i> Student Activities
                            </h3>
                            <div class="card-tools">
                                <a href="bulk_activity.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-tasks mr-1"></i> Record Bulk Activities
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filter Form -->
                            <form action="" method="GET" class="mb-4">
                                <div class="row">
                                    <div class="col-md-2 mb-2">
                                        <label for="activity_type">Activity Type</label>
                                        <select name="activity_type" id="activity_type" class="form-control">
                                            <option value="">All Types</option>
                                            <option value="attendance" <?php echo $activityType == 'attendance' ? 'selected' : ''; ?>>Attendance</option>
                                            <option value="behavioral" <?php echo $activityType == 'behavioral' ? 'selected' : ''; ?>>Behavioral</option>
                                            <option value="academic" <?php echo $activityType == 'academic' ? 'selected' : ''; ?>>Academic</option>
                                            <option value="health" <?php echo $activityType == 'health' ? 'selected' : ''; ?>>Health</option>
                                            <option value="other" <?php echo $activityType == 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="student_id">Student</label>
                                        <select name="student_id" id="student_id" class="form-control">
                                            <option value="">All Students</option>
                                            <?php foreach ($students as $id => $student): ?>
                                                <option value="<?php echo $id; ?>" <?php echo $studentId == $id ? 'selected' : ''; ?>>
                                                    <?php echo $student['first_name'] . ' ' . $student['last_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="date_from">Date From</label>
                                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="date_to">Date To</label>
                                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="search">Search</label>
                                        <input type="text" name="search" id="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <label class="d-block">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Activities Table -->
                            <?php if (count($activities) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Student</th>
                                                <th>Registration #</th>
                                                <th>Type</th>
                                                <th>Description</th>
                                                <th>Recorded On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activities as $activity): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></td>
                                                    <td>
                                                        <a href="student_details.php?id=<?php echo $activity['student_id']; ?>">
                                                            <?php echo $activity['first_name'] . ' ' . $activity['last_name']; ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo $activity['registration_number']; ?></td>
                                                    <td>
                                                        <?php
                                                        $badgeClass = '';
                                                        switch ($activity['activity_type']) {
                                                            case 'attendance':
                                                                $badgeClass = 'badge-info';
                                                                break;
                                                            case 'behavioral':
                                                                $badgeClass = 'badge-warning';
                                                                break;
                                                            case 'academic':
                                                                $badgeClass = 'badge-success';
                                                                break;
                                                            case 'health':
                                                                $badgeClass = 'badge-danger';
                                                                break;
                                                            default:
                                                                $badgeClass = 'badge-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php echo ucfirst($activity['activity_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $activity['description']; ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="edit_activity.php?id=<?php echo $activity['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $activity['id']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <span>Total: <?php echo count($activities); ?> activities</span>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> No Activities Found</h5>
                                    <p>No student activities match your search criteria.</p>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="students.php" class="btn btn-primary">
                                        <i class="fas fa-users mr-1"></i> Go to Students List
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($activities) > 0): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">Export Options</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <button onclick="exportTable('csv')" class="btn btn-block btn-success">
                                        <i class="fas fa-file-csv mr-2"></i> Export to CSV
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button onclick="printReport()" class="btn btn-block btn-primary">
                                        <i class="fas fa-print mr-2"></i> Print Report
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <a href="record_activity.php" class="btn btn-block btn-warning">
                                        <i class="fas fa-plus mr-2"></i> Add New Activity
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this activity record? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="delete_activity.php">
                    <input type="hidden" id="deleteActivityId" name="activity_id">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(activityId) {
        document.getElementById('deleteActivityId').value = activityId;
        $('#deleteModal').modal('show');
    }

    function exportTable(format) {
        // Get current URL with parameters
        const currentUrl = window.location.href;
        // Create export URL by adding export parameter
        const exportUrl = currentUrl + (currentUrl.includes('?') ? '&' : '?') + 'export=' + format;
        // Redirect to export URL
        window.location.href = exportUrl;
    }

    function printReport() {
        // Clone the table and prepare for printing
        const table = document.querySelector('.table').cloneNode(true);
        const actionsColumn = [...table.querySelectorAll('tr')].map(row => row.lastElementChild);
        
        // Hide actions column
        actionsColumn.forEach(cell => cell.style.display = 'none');
        
        // Create print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Student Activities Report - ${<?php echo json_encode($className); ?>}</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
                    <style>
                        body { padding: 20px; }
                        .header { margin-bottom: 20px; }
                        .table { width: 100%; }
                        .footer { margin-top: 30px; font-size: 12px; text-align: center; }
                        @media print {
                            .no-print { display: none; }
                            a { text-decoration: none; color: #000; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h3>Student Activities Report</h3>
                        <p>Class: ${<?php echo json_encode($className); ?>}</p>
                        <p>Generated: ${new Date().toLocaleString()}</p>
                        <div class="filters">
                            ${document.querySelector('form').getAttribute('action') ? 'Filters Applied: ' + Array.from(new FormData(document.querySelector('form'))).filter(item => item[1]).map(item => item[0] + ': ' + item[1]).join(', ') : 'No filters applied'}
                        </div>
                    </div>
                    <div class="table-container">
                        ${table.outerHTML}
                    </div>
                    <div class="footer">
                        <p>Report generated from ${<?php echo json_encode(SCHOOL_NAME); ?>} Class Teacher Portal</p>
                    </div>
                    <div class="no-print text-center mt-4">
                        <button onclick="window.print();" class="btn btn-primary">Print Report</button>
                        <button onclick="window.close();" class="btn btn-secondary ml-2">Close</button>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
    }

    // Enhance select inputs
    $(document).ready(function() {
        $('#student_id').select2({
            placeholder: 'Select a student',
            allowClear: true
        });
    });
</script>

<?php include '../admin/include/footer.php'; ?> 