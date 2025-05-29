<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Handle search and pagination
$search = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search']), ENT_QUOTES, 'UTF-8') : '';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get teacher's subjects
$stmt = $db->prepare("SELECT DISTINCT subject FROM teacher_subjects WHERE teacher_id = :teacher_id ORDER BY subject");
$stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
$teacher_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected subject
$selected_subject = isset($_GET['subject']) ? htmlspecialchars(trim($_GET['subject']), ENT_QUOTES, 'UTF-8') : ($teacher_subjects[0]['subject'] ?? 'All Subjects');

// Build query for students
$query = "SELECT 
            u.id,
            u.username as fullname,
            u.email,
            u.phone,
            u.department,
            COUNT(DISTINCT ea.id) as total_exams,
            AVG(ea.score) as avg_score,
            (SELECT COUNT(*) FROM exam_attempts ea2 
             JOIN exams e2 ON ea2.exam_id = e2.id 
             WHERE ea2.user_id = u.id 
             AND ea2.score >= e2.passing_score) as passed_exams
          FROM users u
          LEFT JOIN exam_attempts ea ON u.id = ea.user_id
          LEFT JOIN exams e ON ea.exam_id = e.id
          WHERE u.role = 'student'
          AND u.added_by = :teacher_id";

if ($selected_subject !== 'All Subjects') {
    $query .= " AND e.subject = :subject";
}

if ($search) {
    $query .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.department LIKE :search)";
}

$query .= " GROUP BY u.id
           ORDER BY u.username
           LIMIT ? OFFSET ?";

$stmt = $db->prepare($query);

// Bind parameters
$paramIndex = 1;
$stmt->bindValue($paramIndex++, $_SESSION['teacher_id']);

if ($selected_subject !== 'All Subjects') {
    $stmt->bindValue(':subject', $selected_subject);
}

if ($search) {
    $stmt->bindValue(':search', "%$search%");
}

// Bind LIMIT and OFFSET parameters
$stmt->bindValue($paramIndex++, $per_page, PDO::PARAM_INT);
$stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);

$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM users WHERE role = 'student' AND added_by = ?";
$stmt = $db->prepare($count_query);
$stmt->bindValue(1, $_SESSION['teacher_id'], PDO::PARAM_INT);
$stmt->execute();
$total_students = $stmt->fetchColumn();
$total_pages = ceil($total_students / $per_page);

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .main-content {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
        }
        .subject-selector select {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .subject-selector select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <div>
                        <h1 class="h2">Manage Students</h1>
                        <p class="text-muted">View and manage your students</p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="subject-selector">
                            <form method="GET" class="d-flex align-items-center gap-2">
                                <?php if ($search): ?>
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <?php endif; ?>
                                <select name="subject" class="form-select" onchange="this.form.submit()">
                                    <option value="All Subjects">All Subjects</option>
                                    <?php foreach ($teacher_subjects as $subject): ?>
                                        <option value="<?php echo htmlspecialchars($subject['subject']); ?>"
                                                <?php echo $selected_subject === $subject['subject'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <a href="add-student.php" class="btn btn-primary">
                            <i class='bx bx-plus'></i> Add Student
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <?php if ($selected_subject !== 'All Subjects'): ?>
                                <input type="hidden" name="subject" value="<?php echo htmlspecialchars($selected_subject); ?>">
                            <?php endif; ?>
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text"><i class='bx bx-search'></i></span>
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search by name, email, or department" 
                                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if ($search): ?>
                                    <a href="?<?php echo $selected_subject !== 'All Subjects' ? 'subject=' . urlencode($selected_subject) : ''; ?>" 
                                       class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Total Exams</th>
                                        <th>Passed Exams</th>
                                        <th>Avg Score</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center">
                                                    <span class="text-primary"><?php echo strtoupper(substr($student['fullname'], 0, 2)); ?></span>
                                                </div>
                                                <?php echo htmlspecialchars($student['fullname']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['department']); ?></td>
                                        <td><?php echo $student['total_exams']; ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo $student['passed_exams']; ?> / <?php echo $student['total_exams']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($student['avg_score']): ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo getScoreClass($student['avg_score']); ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo round($student['avg_score']); ?>%"
                                                         aria-valuenow="<?php echo round($student['avg_score']); ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?php echo round($student['avg_score'], 1); ?>%
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No attempts</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit-student.php?id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                                <a href="student-performance.php?id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info" title="View Performance">
                                                    <i class='bx bx-line-chart'></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteStudent(<?php echo $student['id']; ?>)"
                                                        title="Delete">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : ''; 
                                            echo $selected_subject !== 'All Subjects' ? '&subject=' . urlencode($selected_subject) : '';
                                        ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function getScoreClass(score) {
            if (score >= 70) return 'success';
            if (score >= 50) return 'primary';
            return 'danger';
        }

        function deleteStudent(studentId) {
            if (confirm('Are you sure you want to delete this student? This will also delete all their exam attempts and records.')) {
                fetch('delete-student.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ student_id: studentId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error deleting student: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the student.');
                });
            }
        }
    </script>
</body>
</html> 