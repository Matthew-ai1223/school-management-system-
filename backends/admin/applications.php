<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Get filters
$application_type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM applications WHERE 1=1";
$params = [];

if ($application_type) {
    $query .= " AND application_type = ?";
    $params[] = $application_type;
}

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND JSON_EXTRACT(applicant_data, '$.field_1') LIKE ? OR JSON_EXTRACT(applicant_data, '$.field_2') LIKE ?";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$query .= " ORDER BY submission_date DESC";

// Execute query
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get form fields for reference
$fields_query = "SELECT id, field_label, application_type FROM form_fields WHERE is_active = 1 ORDER BY field_order";
$fields_result = $mysqli->query($fields_query);
$form_fields = [];
while ($field = $fields_result->fetch_assoc()) {
    $form_fields[$field['application_type']][$field['id']] = $field['field_label'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - <?php echo SCHOOL_NAME; ?></title>
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
                        <a href="applications.php" class="nav-link active">
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
                    <h2>Applications</h2>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="type" class="form-label">Application Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="kiddies" <?php echo $application_type === 'kiddies' ? 'selected' : ''; ?>>Kiddies</option>
                                    <option value="college" <?php echo $application_type === 'college' ? 'selected' : ''; ?>>College</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Applications List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Application Type</th>
                                        <th>Status</th>
                                        <th>Submission Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $applicant_data = json_decode($row['applicant_data'], true);
                                            $first_name = '';
                                            $last_name = '';
                                            
                                            // Find the first name and last name fields
                                            foreach ($form_fields[$row['application_type']] as $field_id => $field_label) {
                                                if (stripos($field_label, 'First Name') !== false) {
                                                    $first_name = $applicant_data["field_$field_id"] ?? '';
                                                }
                                                if (stripos($field_label, 'Last Name') !== false) {
                                                    $last_name = $applicant_data["field_$field_id"] ?? '';
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></td>
                                                <td><?php echo ucfirst($row['application_type']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['status'] === 'pending' ? 'warning' : ($row['status'] === 'approved' ? 'success' : 'danger'); ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($row['submission_date'])); ?></td>
                                                <td>
                                                    <a href="view_application.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="updateStatus(<?php echo $row['id']; ?>, 'approved')">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="updateStatus(<?php echo $row['id']; ?>, 'rejected')">
                                                        <i class="bi bi-x-circle"></i> Reject
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No applications found</td>
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
            if (confirm('Are you sure you want to ' + status + ' this application?')) {
                fetch('update_application_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id + '&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Application status updated successfully!');
                        window.location.reload();
                    } else {
                        alert(data.message || 'An error occurred while updating the application status.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the application status.');
                });
            }
        }
    </script>
</body>
</html> 