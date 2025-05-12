<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Get filter parameters
$application_type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Debug log
error_log("Filter parameters: type=$application_type, status=$status, search=$search");

// Base query
$query = "SELECT * FROM students WHERE 1=1";
$params = [];
$types = "";

// Add filters
if (!empty($application_type)) {
    $query .= " AND application_type = ?";
    $params[] = $application_type;
    $types .= "s";
}

if (!empty($status)) {
    $query .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR registration_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Add ordering
$query .= " ORDER BY created_at DESC";

// Debug log
error_log("SQL Query: $query");
error_log("Parameters: " . print_r($params, true));
error_log("Types: $types");

// Execute the query directly first to test
$test_result = $mysqli->query("SELECT COUNT(*) as count FROM students");
error_log("Total students in database: " . $test_result->fetch_assoc()['count']);

// Now try with prepared statement
$stmt = $mysqli->prepare($query);
if ($stmt === false) {
    error_log("Prepare failed: " . $mysqli->error);
} else {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    error_log("Number of filtered results: " . ($result ? $result->num_rows : 0));
}

// Get the file paths in a separate query if needed
$student_files = [];
if ($result && $result->num_rows > 0) {
    $student_ids = [];
    while ($row = $result->fetch_assoc()) {
        $student_ids[] = $row['id'];
    }
    if (!empty($student_ids)) {
        $ids_str = implode(',', $student_ids);
        $files_query = "SELECT entity_id, file_path FROM files WHERE entity_type = 'student' AND entity_id IN ($ids_str)";
        $files_result = $mysqli->query($files_query);
        while ($file = $files_result->fetch_assoc()) {
            $student_files[$file['entity_id']] = $file['file_path'];
        }
    }
    // Reset result pointer
    $result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - <?php echo SCHOOL_NAME; ?></title>
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
        .dropdown-item.active {
            background-color: #e9ecef;
            color: #000;
        }
        .status-btn {
            min-width: 100px;
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
                    <h2>Students</h2>
                    <a href="add_student.php" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Add New Student
                    </a>
                </div>

                <!-- Filter Form -->
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

                <!-- Students Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Reg Number</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Parent Name</th>
                                        <th>Parent Phone</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($student = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $file_path = isset($student_files[$student['id']]) ? $student_files[$student['id']] : '';
                                                    if (!empty($file_path)): 
                                                    ?>
                                                        <img src="<?php echo str_replace('../../', '../', $file_path); ?>" 
                                                             alt="Student Photo" 
                                                             class="rounded-circle"
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center"
                                                             style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person-fill"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['registration_number'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></td>
                                                <td><?php echo ucfirst($student['application_type'] ?? ''); ?></td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm dropdown-toggle status-btn 
                                                            <?php echo ($student['status'] ?? '') === 'pending' ? 'btn-warning' : 
                                                                (($student['status'] ?? '') === 'registered' ? 'btn-success' : 'btn-danger'); ?>"
                                                            type="button"
                                                            data-bs-toggle="dropdown"
                                                            aria-expanded="false">
                                                            <?php echo ucfirst($student['status'] ?? ''); ?>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item <?php echo ($student['status'] ?? '') === 'pending' ? 'active' : ''; ?>" 
                                                                href="#" onclick="updateStatus(<?php echo $student['id']; ?>, 'pending')">Pending</a></li>
                                                            <li><a class="dropdown-item <?php echo ($student['status'] ?? '') === 'registered' ? 'active' : ''; ?>" 
                                                                href="#" onclick="updateStatus(<?php echo $student['id']; ?>, 'registered')">Registered</a></li>
                                                            <li><a class="dropdown-item <?php echo ($student['status'] ?? '') === 'rejected' ? 'active' : ''; ?>" 
                                                                href="#" onclick="updateStatus(<?php echo $student['id']; ?>, 'rejected')">Rejected</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['parent_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?></td>
                                                <td>
                                                    <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteStudent(<?php echo $student['id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No students found</td>
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
    <script>
        function updateStatus(id, status) {
            console.log('Updating status:', { id, status });
            
            fetch('update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    // Update the button appearance
                    const statusBtn = event.target.closest('.dropdown').querySelector('.status-btn');
                    statusBtn.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    
                    // Update button class
                    statusBtn.classList.remove('btn-warning', 'btn-success', 'btn-danger');
                    if (status === 'pending') {
                        statusBtn.classList.add('btn-warning');
                    } else if (status === 'registered') {
                        statusBtn.classList.add('btn-success');
                    } else {
                        statusBtn.classList.add('btn-danger');
                    }
                    
                    // Update dropdown active state
                    const dropdownItems = event.target.closest('.dropdown-menu').querySelectorAll('.dropdown-item');
                    dropdownItems.forEach(item => {
                        item.classList.remove('active');
                        if (item.textContent.toLowerCase() === status) {
                            item.classList.add('active');
                        }
                    });
                } else {
                    console.error('Error from server:', data.message);
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                alert('An error occurred while updating the status: ' + error.message);
            });
        }

        function deleteStudent(id) {
            if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                fetch('delete_student.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        alert(data.message);
                        // Reload the page to refresh the list
                        window.location.reload();
                    } else {
                        // Show error message
                        alert('Error: ' + data.message);
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