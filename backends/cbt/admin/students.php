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
$query = "SELECT DISTINCT
            s.id,
            s.first_name,
            s.last_name,
            s.email,
            s.parent_phone,
            s.class,
            s.registration_number,
            COUNT(DISTINCT ea.id) as total_exams,
            ROUND(AVG(CASE WHEN ea.status = 'completed' THEN (ea.score / e.duration * 100) ELSE NULL END), 2) as avg_score,
            MAX(CASE WHEN ea.status = 'completed' THEN (ea.score / e.duration * 100) ELSE NULL END) as highest_score,
            MIN(CASE WHEN ea.status = 'completed' THEN (ea.score / e.duration * 100) ELSE NULL END) as lowest_score,
            SUM(CASE WHEN ea.status = 'completed' AND (ea.score / e.duration * 100) >= 50 THEN 1 ELSE 0 END) as passed_exams,
            SUM(CASE WHEN ea.status = 'completed' AND (ea.score / e.duration * 100) < 50 THEN 1 ELSE 0 END) as failed_exams,
            GROUP_CONCAT(DISTINCT CASE WHEN ea.status = 'completed' THEN e.subject END) as subjects_taken,
            SUM(CASE WHEN ea.status = 'completed' AND (ea.score / e.duration * 100) >= 70 THEN 1 ELSE 0 END) as distinctions,
            SUM(CASE WHEN ea.status = 'completed' AND (ea.score / e.duration * 100) >= 50 AND (ea.score / e.duration * 100) < 70 THEN 1 ELSE 0 END) as credits
          FROM ace_school_system.students s
          INNER JOIN ace_school_system.exam_attempts ea ON s.id = ea.student_id
          INNER JOIN ace_school_system.exams e ON ea.exam_id = e.id
          WHERE EXISTS (
              SELECT 1 
              FROM ace_school_system.teacher_subjects ts 
              WHERE ts.teacher_id = :main_teacher_id
          )
          AND ea.status = 'completed'";

// Add class filter if provided
if (isset($_GET['class']) && !empty($_GET['class'])) {
    $query .= " AND s.class = :class";
}

if ($selected_subject !== 'All Subjects') {
    $query .= " AND EXISTS (
        SELECT 1 
        FROM ace_school_system.teacher_subjects ts2 
        WHERE ts2.subject = :selected_subject
        AND ts2.teacher_id = :subject_teacher_id
    )";
}

if ($search) {
    $query .= " AND (s.first_name LIKE :search 
                     OR s.last_name LIKE :search 
                     OR s.parent_phone LIKE :search 
                     OR s.registration_number LIKE :search)";
}

$query .= " GROUP BY s.id
           ORDER BY s.class, s.first_name, s.last_name
           LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

// Bind parameters
$stmt->bindValue(':main_teacher_id', $_SESSION['teacher_id'], PDO::PARAM_INT);

if (isset($_GET['class']) && !empty($_GET['class'])) {
    $stmt->bindValue(':class', $_GET['class']);
}

if ($selected_subject !== 'All Subjects') {
    $stmt->bindValue(':selected_subject', $selected_subject);
    $stmt->bindValue(':subject_teacher_id', $_SESSION['teacher_id'], PDO::PARAM_INT);
}

if ($search) {
    $stmt->bindValue(':search', "%$search%");
}

// Bind LIMIT and OFFSET parameters
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT s.id) 
                FROM ace_school_system.students s
                INNER JOIN ace_school_system.exam_attempts ea ON s.id = ea.student_id
                INNER JOIN ace_school_system.exams e ON ea.exam_id = e.id
                WHERE EXISTS (
                    SELECT 1 
                    FROM ace_school_system.teacher_subjects ts 
                    WHERE ts.teacher_id = :main_teacher_id
                )
                AND ea.status = 'completed'";

// Add class filter if provided
if (isset($_GET['class']) && !empty($_GET['class'])) {
    $count_query .= " AND s.class = :class";
}

if ($selected_subject !== 'All Subjects') {
    $count_query .= " AND EXISTS (
        SELECT 1 
        FROM ace_school_system.teacher_subjects ts2 
        WHERE ts2.subject = :selected_subject
        AND ts2.teacher_id = :subject_teacher_id
    )";
}

if ($search) {
    $count_query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.email LIKE :search OR s.registration_number LIKE :search)";
}

$stmt = $db->prepare($count_query);
$stmt->bindValue(':main_teacher_id', $_SESSION['teacher_id'], PDO::PARAM_INT);

if (isset($_GET['class']) && !empty($_GET['class'])) {
    $stmt->bindValue(':class', $_GET['class']);
}

if ($selected_subject !== 'All Subjects') {
    $stmt->bindValue(':selected_subject', $selected_subject);
    $stmt->bindValue(':subject_teacher_id', $_SESSION['teacher_id'], PDO::PARAM_INT);
}

if ($search) {
    $stmt->bindValue(':search', "%$search%");
}

$stmt->execute();
$total_students = $stmt->fetchColumn();
$total_pages = ceil($total_students / $per_page);

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Get available classes for the filter
$classes_query = "SELECT DISTINCT s.class 
                 FROM ace_school_system.students s
                 INNER JOIN ace_school_system.exam_attempts ea ON s.id = ea.student_id
                 INNER JOIN ace_school_system.exams e ON ea.exam_id = e.id
                 WHERE EXISTS (
                     SELECT 1 
                     FROM ace_school_system.teacher_subjects ts 
                     WHERE ts.teacher_id = :teacher_id
                 )
                 AND ea.status = 'completed'
                 ORDER BY s.class";
$stmt = $db->prepare($classes_query);
$stmt->bindValue(':teacher_id', $_SESSION['teacher_id'], PDO::PARAM_INT);
$stmt->execute();
$available_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
        .avatar-sm {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 500;
        }
        .table > :not(caption) > * > * {
            padding: 1rem 0.75rem;
        }
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.825rem;
            letter-spacing: 0.5px;
            color: #495057;
        }
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
        }
        .progress {
            background-color: #e9ecef;
            border-radius: 0.25rem;
            overflow: hidden;
        }
        .btn-group .btn i {
            font-size: 1.1rem;
        }
        .text-muted {
            color: #6c757d !important;
        }
        .student-info h6 {
            font-weight: 600;
            color: #2c3e50;
        }
        .student-stats {
            font-size: 0.875rem;
        }
        .student-stats i {
            width: 18px;
            text-align: center;
            margin-right: 4px;
        }
        .subject-badges .badge {
            margin: 2px;
            font-size: 0.75rem;
            font-weight: normal;
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
                                           placeholder="Search by name, parent's phone, or registration number" 
                                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="class" class="form-select mb-2">
                                    <option value="">All Classes</option>
                                    <?php foreach ($available_classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class); ?>"
                                                <?php echo (isset($_GET['class']) && $_GET['class'] === $class) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <?php if ($search || isset($_GET['class'])): ?>
                                        <a href="?<?php echo $selected_subject !== 'All Subjects' ? 'subject=' . urlencode($selected_subject) : ''; ?>" 
                                           class="btn btn-secondary">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student Details</th>
                                        <th>Contact Info</th>
                                        <th>Academic Info</th>
                                        <th>Exam Performance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): 
                                        $subjects = explode(',', $student['subjects_taken'] ?? '');
                                        $subjects = array_filter($subjects);
                                        $performance_class = '';
                                        if ($student['avg_score'] >= 70) {
                                            $performance_class = 'success';
                                        } elseif ($student['avg_score'] >= 50) {
                                            $performance_class = 'primary';
                                        } else {
                                            $performance_class = 'danger';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center">
                                                    <span class="text-primary"><?php 
                                                        $firstName = $student['first_name'] ?? '';
                                                        $lastName = $student['last_name'] ?? '';
                                                        echo strtoupper(
                                                            substr($firstName, 0, 1) . 
                                                            substr($lastName, 0, 1)
                                                        ); 
                                                    ?></span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h6>
                                                    <small class="text-muted">Reg #: <?php echo htmlspecialchars($student['registration_number'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="contact-info">
                                                    <div class="mb-1">
                                                        <i class='bx bx-phone text-primary'></i> 
                                                        <strong><?php echo htmlspecialchars($student['parent_phone'] ?? 'No phone number'); ?></strong>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class='bx bx-user-voice'></i> Parent's Contact
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>Class:</strong> 
                                                <?php echo htmlspecialchars($student['class']); ?>
                                            </div>
                                            <?php if (!empty($subjects)): ?>
                                            <div class="mt-1">
                                                <strong>Subjects Taken:</strong><br>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <span class="badge bg-info me-1">
                                                        <?php echo htmlspecialchars(trim($subject)); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <small>Average Score</small>
                                                    <small class="fw-bold"><?php echo number_format($student['avg_score'] ?? 0, 2); ?>%</small>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-<?php echo $performance_class; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo min(100, $student['avg_score'] ?? 0); ?>%"
                                                         aria-valuenow="<?php echo $student['avg_score'] ?? 0; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between small">
                                                <div>
                                                    <i class='bx bx-check-circle text-success'></i> 
                                                    Passed: <?php echo $student['passed_exams'] ?? 0; ?>
                                                    <small class="text-muted">(≥50%)</small>
                                                </div>
                                                <div>
                                                    <i class='bx bx-x-circle text-danger'></i> 
                                                    Failed: <?php echo $student['failed_exams'] ?? 0; ?>
                                                    <small class="text-muted">(<50%)</small>
                                                </div>
                                                <div>
                                                    <i class='bx bx-book'></i> 
                                                    Total: <?php echo $student['total_exams'] ?? 0; ?>
                                                </div>
                                            </div>
                                            <?php if (($student['total_exams'] ?? 0) > 0): ?>
                                            <div class="mt-2 small">
                                                <div class="mb-1">
                                                    <span class="badge bg-success me-1">Distinctions: <?php echo $student['distinctions'] ?? 0; ?> (≥70%)</span>
                                                    <span class="badge bg-primary">Credits: <?php echo $student['credits'] ?? 0; ?> (50-69%)</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <div>Highest: <strong><?php echo number_format($student['highest_score'] ?? 0, 2); ?>%</strong></div>
                                                    <div>Lowest: <strong><?php echo number_format($student['lowest_score'] ?? 0, 2); ?>%</strong></div>
                                                </div>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        Overall Pass Rate: <strong><?php 
                                                            echo number_format($student['avg_score'] ?? 0, 1) . '%';
                                                        ?></strong>
                                                        <i class='bx bx-info-circle' title="Percentage of exams with score ≥50%"></i>
                                                    </small>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="student-performance.php?id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="View Performance">
                                                    <i class='bx bx-line-chart'></i>
                                                </a>
                                                <a href="edit-student.php?id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-info" 
                                                   title="Edit Student">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                                <a href="generate_result_pdf.php?student_id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-success" 
                                                   title="Download Result"
                                                   target="_blank">
                                                    <i class='bx bx-download'></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger"
                                                        onclick="deleteStudent(<?php echo $student['id']; ?>)"
                                                        title="Delete Student">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class='bx bx-info-circle fs-4'></i>
                                                <p class="mb-0">No students found</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
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