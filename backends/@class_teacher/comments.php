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
$commentType = isset($_GET['comment_type']) ? $_GET['comment_type'] : '';
$term = isset($_GET['term']) ? $_GET['term'] : '';
$session = isset($_GET['session']) ? $_GET['session'] : '';
$studentId = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

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

// Get unique terms and sessions for dropdown filters
$termsQuery = "SELECT DISTINCT term FROM class_teacher_comments 
              WHERE class_teacher_id = ? AND term IS NOT NULL AND term != ''
              ORDER BY term";
$stmt = $conn->prepare($termsQuery);
$stmt->bind_param("i", $classTeacherId);
$stmt->execute();
$termsResult = $stmt->get_result();
$terms = [];

while ($row = $termsResult->fetch_assoc()) {
    $terms[] = $row['term'];
}

$sessionsQuery = "SELECT DISTINCT session FROM class_teacher_comments 
                 WHERE class_teacher_id = ? AND session IS NOT NULL AND session != ''
                 ORDER BY session";
$stmt = $conn->prepare($sessionsQuery);
$stmt->bind_param("i", $classTeacherId);
$stmt->execute();
$sessionsResult = $stmt->get_result();
$sessions = [];

while ($row = $sessionsResult->fetch_assoc()) {
    $sessions[] = $row['session'];
}

// Build the comments query with filters
$commentsQuery = "SELECT c.*, s.first_name, s.last_name, s.registration_number
                  FROM class_teacher_comments c
                  JOIN students s ON c.student_id = s.id
                  WHERE c.class_teacher_id = ?";

$params = [$classTeacherId];
$types = "i";

if (!empty($commentType)) {
    $commentsQuery .= " AND c.comment_type = ?";
    $types .= "s";
    $params[] = $commentType;
}

if (!empty($term)) {
    $commentsQuery .= " AND c.term = ?";
    $types .= "s";
    $params[] = $term;
}

if (!empty($session)) {
    $commentsQuery .= " AND c.session = ?";
    $types .= "s";
    $params[] = $session;
}

if (!empty($studentId)) {
    $commentsQuery .= " AND c.student_id = ?";
    $types .= "i";
    $params[] = $studentId;
}

if (!empty($dateFrom)) {
    $commentsQuery .= " AND DATE(c.created_at) >= ?";
    $types .= "s";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $commentsQuery .= " AND DATE(c.created_at) <= ?";
    $types .= "s";
    $params[] = $dateTo;
}

if (!empty($search)) {
    $commentsQuery .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.registration_number LIKE ? OR c.comment LIKE ?)";
    $types .= "ssss";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$commentsQuery .= " ORDER BY c.created_at DESC";

$stmt = $conn->prepare($commentsQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$commentsResult = $stmt->get_result();
$comments = [];

while ($row = $commentsResult->fetch_assoc()) {
    $comments[] = $row;
}

// Include header/dashboard template
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Student Comments - <?php echo $className; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item active">Comments</li>
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
                                <i class="fas fa-comment mr-2"></i> Student Comments
                            </h3>
                            <div class="card-tools">
                                <a href="add_comment.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Add New Comment
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filter Form -->
                            <form action="" method="GET" class="mb-4">
                                <div class="row">
                                    <div class="col-md-2 mb-2">
                                        <label for="comment_type">Comment Type</label>
                                        <select name="comment_type" id="comment_type" class="form-control">
                                            <option value="">All Types</option>
                                            <option value="term_report" <?php echo $commentType == 'term_report' ? 'selected' : ''; ?>>Term Report</option>
                                            <option value="behavioral" <?php echo $commentType == 'behavioral' ? 'selected' : ''; ?>>Behavioral</option>
                                            <option value="academic" <?php echo $commentType == 'academic' ? 'selected' : ''; ?>>Academic</option>
                                            <option value="general" <?php echo $commentType == 'general' ? 'selected' : ''; ?>>General</option>
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
                                        <label for="term">Term</label>
                                        <select name="term" id="term" class="form-control">
                                            <option value="">All Terms</option>
                                            <?php foreach ($terms as $t): ?>
                                                <option value="<?php echo $t; ?>" <?php echo $term == $t ? 'selected' : ''; ?>>
                                                    <?php echo $t; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="session">Session</label>
                                        <select name="session" id="session" class="form-control">
                                            <option value="">All Sessions</option>
                                            <?php foreach ($sessions as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo $session == $s ? 'selected' : ''; ?>>
                                                    <?php echo $s; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="search">Search</label>
                                        <input type="text" name="search" id="search" class="form-control" placeholder="Search by name, registration number or comment..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mt-2">
                                    <div class="col-md-3 mb-2">
                                        <label for="date_from">Date From</label>
                                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="date_to">Date To</label>
                                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                                    </div>
                                    <div class="col-md-2 mb-2 ml-auto">
                                        <label class="d-block">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-search mr-1"></i> Filter
                                        </button>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="d-block">&nbsp;</label>
                                        <a href="comments.php" class="btn btn-outline-secondary btn-block">
                                            <i class="fas fa-sync-alt mr-1"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <!-- Comments Table -->
                            <?php if (count($comments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th width="5%">ID</th>
                                                <th width="15%">Student</th>
                                                <th width="10%">Type</th>
                                                <th width="8%">Term</th>
                                                <th width="8%">Session</th>
                                                <th width="39%">Comment</th>
                                                <th width="10%">Date Added</th>
                                                <th width="5%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($comments as $comment): ?>
                                                <tr>
                                                    <td><?php echo $comment['id']; ?></td>
                                                    <td>
                                                        <a href="student_details.php?id=<?php echo $comment['student_id']; ?>">
                                                            <?php echo $comment['first_name'] . ' ' . $comment['last_name']; ?>
                                                        </a>
                                                        <div class="small text-muted"><?php echo $comment['registration_number']; ?></div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badgeClass = '';
                                                        switch ($comment['comment_type']) {
                                                            case 'term_report':
                                                                $badgeClass = 'badge-primary';
                                                                $label = 'Term Report';
                                                                break;
                                                            case 'behavioral':
                                                                $badgeClass = 'badge-warning';
                                                                $label = 'Behavioral';
                                                                break;
                                                            case 'academic':
                                                                $badgeClass = 'badge-success';
                                                                $label = 'Academic';
                                                                break;
                                                            default:
                                                                $badgeClass = 'badge-secondary';
                                                                $label = 'General';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php echo $label; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $comment['term'] ?? 'N/A'; ?></td>
                                                    <td><?php echo $comment['session'] ?? 'N/A'; ?></td>
                                                    <td>
                                                        <?php 
                                                        // Truncate long comments for display
                                                        $fullComment = $comment['comment'];
                                                        $shortComment = strlen($fullComment) > 150 ? 
                                                            substr($fullComment, 0, 150) . '...' : 
                                                            $fullComment;
                                                        
                                                        echo htmlspecialchars($shortComment);
                                                        
                                                        // Add view more button for long comments
                                                        if (strlen($fullComment) > 150):
                                                        ?>
                                                        <button type="button" class="btn btn-xs btn-link view-more" 
                                                                data-toggle="modal" 
                                                                data-target="#commentModal" 
                                                                data-comment="<?php echo htmlspecialchars($fullComment); ?>"
                                                                data-student="<?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>"
                                                                data-type="<?php echo htmlspecialchars($label); ?>">
                                                            View More
                                                        </button>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="edit_comment.php?id=<?php echo $comment['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $comment['id']; ?>)">
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
                                    <span>Total: <?php echo count($comments); ?> comments</span>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> No Comments Found</h5>
                                    <p>No student comments match your search criteria.</p>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="add_comment.php" class="btn btn-primary">
                                        <i class="fas fa-plus mr-1"></i> Add New Comment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($comments) > 0): ?>
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
                                    <a href="bulk_comment.php" class="btn btn-block btn-warning">
                                        <i class="fas fa-comments mr-2"></i> Bulk Comment Entry
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

<!-- Full Comment Modal -->
<div class="modal fade" id="commentModal" tabindex="-1" role="dialog" aria-labelledby="commentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commentModalLabel">Comment Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Student:</strong> <span id="modalStudent"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Type:</strong> <span id="modalType"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="fullComment">Full Comment:</label>
                    <textarea class="form-control" id="fullComment" rows="10" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
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
                Are you sure you want to delete this comment? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="delete_comment.php">
                    <input type="hidden" id="deleteCommentId" name="comment_id">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(commentId) {
        document.getElementById('deleteCommentId').value = commentId;
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
        
        // Hide actions column and view more buttons
        const actionCells = table.querySelectorAll('th:last-child, td:last-child');
        actionCells.forEach(cell => cell.style.display = 'none');
        
        const viewMoreButtons = table.querySelectorAll('.view-more');
        viewMoreButtons.forEach(btn => btn.style.display = 'none');
        
        // Create print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Student Comments Report - ${<?php echo json_encode($className); ?>}</title>
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
                        <h3>Student Comments Report</h3>
                        <p>Class: ${<?php echo json_encode($className); ?>}</p>
                        <p>Generated: ${new Date().toLocaleString()}</p>
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

    // Initialize modal for full comment view
    $(document).ready(function() {
        $('#commentModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const comment = button.data('comment');
            const student = button.data('student');
            const type = button.data('type');
            
            const modal = $(this);
            modal.find('#fullComment').val(comment);
            modal.find('#modalStudent').text(student);
            modal.find('#modalType').text(type);
        });
        
        // Enhance select inputs with select2
        $('#student_id, #comment_type, #term, #session').select2({
            placeholder: 'Select an option',
            allowClear: true
        });
    });
</script>

<?php include '../admin/include/footer.php'; ?> 