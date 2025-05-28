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

// Handle search and pagination
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query for both morning and afternoon students
$query = "(SELECT 
            ms.id,
            ms.fullname,
            ms.email,
            ms.phone,
            ms.department,
            ms.expiration_date,
            'Morning' as session,
            COUNT(DISTINCT ea.id) as total_exams,
            AVG(ea.score) as avg_score
          FROM morning_students ms
          LEFT JOIN exam_attempts ea ON ms.id = ea.user_id";

if ($search) {
    $query .= " WHERE (ms.fullname LIKE :search OR ms.email LIKE :search OR ms.department LIKE :search)";
}

$query .= " GROUP BY ms.id)
           UNION ALL
           (SELECT 
            afs.id,
            afs.fullname,
            afs.email,
            afs.phone,
            afs.department,
            afs.expiration_date,
            'Afternoon' as session,
            COUNT(DISTINCT ea.id) as total_exams,
            AVG(ea.score) as avg_score
           FROM afternoon_students afs
           LEFT JOIN exam_attempts ea ON afs.id = ea.user_id";

if ($search) {
    $query .= " WHERE (afs.fullname LIKE :search OR afs.email LIKE :search OR afs.department LIKE :search)";
}

$query .= " GROUP BY afs.id)
           ORDER BY fullname
           LIMIT :offset, :per_page";

$stmt = $db->prepare($query);
if ($search) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "(SELECT COUNT(*) as count FROM morning_students";
if ($search) {
    $count_query .= " WHERE (fullname LIKE :search OR email LIKE :search OR department LIKE :search)";
}
$count_query .= ") UNION ALL (SELECT COUNT(*) FROM afternoon_students";
if ($search) {
    $count_query .= " WHERE (fullname LIKE :search OR email LIKE :search OR department LIKE :search)";
}
$count_query .= ")";

$stmt = $db->prepare($count_query);
if ($search) {
    $stmt->bindParam(':search', $search_param);
}
$stmt->execute();
$counts = $stmt->fetchAll(PDO::FETCH_COLUMN);
$total_students = array_sum($counts);
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
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Students</h1>
                    <div>
                        <a href="add-student.php" class="btn btn-primary me-2">
                            <i class='bx bx-plus'></i> Add Student
                        </a>
                        <a href="bulk-upload-students.php" class="btn btn-success">
                            <i class='bx bx-upload'></i> Bulk Upload
                        </a>
                    </div>
                </div>

                <?php echo $message; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by name, email, or department" 
                                       value="<?php echo htmlspecialchars($search ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if ($search): ?>
                                    <a href="students.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Session</th>
                                        <th>Total Exams</th>
                                        <th>Avg Score</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['department']); ?></td>
                                        <td><?php echo htmlspecialchars($student['session']); ?></td>
                                        <td><?php echo $student['total_exams']; ?></td>
                                        <td><?php echo $student['avg_score'] ? round($student['avg_score'], 2) . '%' : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo strtotime($student['expiration_date']) > time() ? 'success' : 'danger'; ?>">
                                                <?php echo strtotime($student['expiration_date']) > time() ? 'Active' : 'Expired'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit-student.php?id=<?php echo $student['id']; ?>&session=<?php echo strtolower($student['session']); ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                                <a href="student-performance.php?id=<?php echo $student['id']; ?>&session=<?php echo strtolower($student['session']); ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class='bx bx-line-chart'></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo strtolower($student['session']); ?>')">
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
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
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
        function deleteStudent(studentId, session) {
            if (confirm('Are you sure you want to delete this student? This will also delete all their exam attempts and records.')) {
                fetch('delete-student.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        student_id: studentId,
                        session: session
                    })
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