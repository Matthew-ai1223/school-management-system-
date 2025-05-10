<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$user = $auth->getCurrentUser();

// Handle filters
$application_type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM students WHERE 1=1";
if ($application_type) {
    $query .= " AND application_type = '" . $db->escape($application_type) . "'";
}
if ($status) {
    $query .= " AND status = '" . $db->escape($status) . "'";
}
if ($search) {
    $search = $db->escape($search);
    $query .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR registration_number LIKE '%$search%')";
}
$query .= " ORDER BY created_at DESC";

$result = $db->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - <?php echo SCHOOL_NAME; ?></title>
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
                        <a href="students.php" class="nav-link active">
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
                        <a href="exams.php" class="nav-link">
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
                    <h2>Students Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="bi bi-plus"></i> Add New Student
                    </button>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Application Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="kiddies" <?php echo $application_type === 'kiddies' ? 'selected' : ''; ?>>Ace Kiddies</option>
                                    <option value="college" <?php echo $application_type === 'college' ? 'selected' : ''; ?>>Ace College</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="registered" <?php echo $status === 'registered' ? 'selected' : ''; ?>>Registered</option>
                                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Search by name or registration number" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Students List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Reg. Number</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Parent Name</th>
                                        <th>Parent Phone</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['registration_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo ucfirst($row['application_type']); ?></td>
                                                <td><?php echo htmlspecialchars($row['parent_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['parent_phone']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['status'] === 'pending' ? 'warning' : ($row['status'] === 'registered' ? 'success' : 'danger'); ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-info" onclick="viewStudent(<?php echo $row['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-primary" onclick="editStudent(<?php echo $row['id']; ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteStudent(<?php echo $row['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No students found</td>
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

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addStudentForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Application Type</label>
                                <select name="application_type" class="form-select" required>
                                    <option value="kiddies">Ace Kiddies</option>
                                    <option value="college">Ace College</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registration Number</label>
                                <input type="text" name="registration_number" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select" required>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Parent Name</label>
                                <input type="text" name="parent_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Parent Phone</label>
                                <input type="tel" name="parent_phone" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parent Email</label>
                            <input type="email" name="parent_email" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveStudent()">Save Student</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewStudent(id) {
            // Implement view student details
            window.location.href = 'student_details.php?id=' + id;
        }

        function editStudent(id) {
            // Implement edit student
            window.location.href = 'edit_student.php?id=' + id;
        }

        function deleteStudent(id) {
            if (confirm('Are you sure you want to delete this student?')) {
                // Implement delete student
                fetch('delete_student.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting student: ' + data.message);
                    }
                });
            }
        }

        function saveStudent() {
            const form = document.getElementById('addStudentForm');
            const formData = new FormData(form);
            
            fetch('save_student.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error saving student: ' + data.message);
                }
            });
        }
    </script>
</body>
</html> 