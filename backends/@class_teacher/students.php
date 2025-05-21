<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils.php';

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Ensure both admission_number and registration_number columns exist
ensureStudentNumberColumns($conn);

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
$gender = isset($_GET['gender']) ? $_GET['gender'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query with filters
$studentsQuery = "SELECT s.*, 
                 COALESCE(s.admission_number, s.registration_number) as display_number
                 FROM students s
                 WHERE s.class = ?";

$params = [$className];
$types = "s";

if (!empty($gender)) {
    $studentsQuery .= " AND LOWER(s.gender) = LOWER(?)";
    $types .= "s";
    $params[] = $gender;
}

if (!empty($search)) {
    $studentsQuery .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_number LIKE ? OR s.registration_number LIKE ?)";
    $types .= "ssss";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$studentsQuery .= " ORDER BY s.first_name, s.last_name";

$stmt = $conn->prepare($studentsQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$studentsResult = $stmt->get_result();
$students = [];

while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}

// Include header/dashboard template
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Manage Students - <?php echo $className; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Students</li>
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
                                <i class="fas fa-users mr-2"></i> Students List
                            </h3>
                            <div class="card-tools">
                                <form action="" method="GET" class="form-inline">
                                    <div class="input-group input-group-sm mr-2">
                                        <select name="gender" class="form-control">
                                            <option value="">All Genders</option>
                                            <option value="male" <?php echo $gender == 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo $gender == 'female' ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <?php if (count($students) > 0): ?>
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Registration #</th>
                                        <th>Name</th>
                                        <th>Gender</th>
                                        <th>Date of Birth</th>
                                        <th>Parent</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo $student['display_number'] ?? 'N/A'; ?></td>
                                        <td><?php echo ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''); ?></td>
                                        <td><?php echo isset($student['gender']) ? ucfirst(strtolower($student['gender'])) : 'N/A'; ?></td>
                                        <td><?php echo isset($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : 'N/A'; ?></td>
                                        <td><?php echo $student['parent_name'] ?? 'N/A'; ?></td>
                                        <td><?php echo $student['parent_phone'] ?? 'N/A'; ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="record_activity.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-clipboard"></i> Record Activity
                                                </a>
                                                <a href="add_comment.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-comment"></i> Add Comment
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="callout callout-info m-3">
                                <h5>No Students Found</h5>
                                <p>There are no students in this class matching your search criteria.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (count($students) > 0): ?>
                        <div class="card-footer clearfix">
                            <div class="float-right">
                                <span>Total: <?php echo count($students); ?> Students</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">Student Management Options</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <a href="activities.php" class="btn btn-block btn-warning">
                                        <i class="fas fa-clipboard-list mr-2"></i> All Activities
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="comments.php" class="btn btn-block btn-primary">
                                        <i class="fas fa-comment mr-2"></i> All Comments
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="bulk_activity.php" class="btn btn-block btn-info">
                                        <i class="fas fa-tasks mr-2"></i> Bulk Activities
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="print_class_list.php" target="_blank" class="btn btn-block btn-secondary">
                                        <i class="fas fa-print mr-2"></i> Print Class List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../admin/include/footer.php'; ?> 