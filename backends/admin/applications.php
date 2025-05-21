<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

// $auth = new Auth();
// $auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Get filters with proper sanitization
$application_type = isset($_GET['type']) && in_array($_GET['type'], ['kiddies', 'college']) ? $_GET['type'] : '';
$status = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'rejected']) ? $_GET['status'] : '';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'oldest', 'name_asc', 'name_desc']) ? $_GET['sort'] : 'newest';

// Debug information
$debug = [];
$debug['filters'] = [
    'type' => $application_type,
    'status' => $status,
    'search' => $search,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'sort' => $sort
];

// Build base query
$query = "SELECT a.*, u.first_name as reviewer_first_name, u.last_name as reviewer_last_name 
          FROM applications a 
          LEFT JOIN users u ON a.reviewed_by = u.id 
          WHERE 1=1";
$params = [];
$types = "";

// Add filters to query
if ($application_type !== '') {
    $query .= " AND a.application_type = ?";
    $params[] = $application_type;
    $types .= "s";
}

if ($status !== '') {
    $query .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($date_from !== '') {
    $query .= " AND DATE(a.submission_date) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to !== '') {
    $query .= " AND DATE(a.submission_date) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Get searchable fields
if ($search !== '') {
    $name_fields_query = "SELECT id, field_label, application_type 
                         FROM form_fields 
                         WHERE is_active = 1 
                         AND (
                             LOWER(field_label) LIKE '%name%' 
                             OR LOWER(field_label) LIKE '%email%' 
                             OR LOWER(field_label) LIKE '%phone%'
                         )";
    $name_fields_result = $mysqli->query($name_fields_query);
    $search_conditions = [];
    
    if ($name_fields_result) {
        while ($field = $name_fields_result->fetch_assoc()) {
            $search_conditions[] = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.field_" . $field['id'] . "'))) LIKE LOWER(?)";
            $params[] = "%$search%";
            $types .= "s";
        }
    }
    
    if (!empty($search_conditions)) {
        $query .= " AND (" . implode(" OR ", $search_conditions) . ")";
    }
}

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY a.submission_date ASC";
        break;
    case 'name_asc':
        // Get the first name field ID
        $first_name_field = $mysqli->query("SELECT id FROM form_fields WHERE field_label LIKE '%First Name%' AND is_active = 1 LIMIT 1");
        if ($first_name_field && $field = $first_name_field->fetch_assoc()) {
            $query .= " ORDER BY JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.field_" . $field['id'] . "')) ASC";
        } else {
            $query .= " ORDER BY a.submission_date DESC";
        }
        break;
    case 'name_desc':
        // Get the first name field ID
        $first_name_field = $mysqli->query("SELECT id FROM form_fields WHERE field_label LIKE '%First Name%' AND is_active = 1 LIMIT 1");
        if ($first_name_field && $field = $first_name_field->fetch_assoc()) {
            $query .= " ORDER BY JSON_UNQUOTE(JSON_EXTRACT(a.applicant_data, '$.field_" . $field['id'] . "')) DESC";
        } else {
            $query .= " ORDER BY a.submission_date DESC";
        }
        break;
    default: // newest
        $query .= " ORDER BY a.submission_date DESC";
}

$debug['query'] = $query;
$debug['params'] = $params;
$debug['types'] = $types;

// Execute query
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
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

// Store the number of results
$total_results = $result->num_rows;
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
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Applications</h2>
                    <div>
                        <button id="downloadSelected" class="btn btn-primary" style="display: none;">
                            <i class="bi bi-download"></i> Download Selected
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3" id="filterForm">
                            <div class="col-md-3">
                                <label for="type" class="form-label">Application Type</label>
                                <select class="form-select" id="type" name="type" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <option value="kiddies" <?php echo $application_type === 'kiddies' ? 'selected' : ''; ?>>Kiddies</option>
                                    <option value="college" <?php echo $application_type === 'college' ? 'selected' : ''; ?>>College</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo $date_to; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search by name, email, or phone...">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="sort" class="form-label">Sort By</label>
                                <select class="form-select" id="sort" name="sort" onchange="this.form.submit()">
                                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                    <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <a href="applications.php" class="btn btn-secondary d-block">
                                    <i class="bi bi-x-circle"></i> Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="alert alert-info mb-4">
                    Found <?php echo $total_results; ?> application(s)
                    <?php if ($application_type || $status || $search || $date_from || $date_to): ?>
                        matching your filters
                    <?php endif; ?>
                </div>

                <!-- Applications List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Application Type</th>
                                        <th>Status</th>
                                        <th>Submission Date</th>
                                        <th>Reviewed By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $applicant_data = json_decode($row['applicant_data'], true);
                                            $first_name = '';
                                            $last_name = '';
                                            
                                            // Initialize form fields array for this application type if not exists
                                            if (!isset($form_fields[$row['application_type']])) {
                                                $form_fields[$row['application_type']] = [];
                                                
                                                // Get form fields for this application type
                                                $fields_stmt = $mysqli->prepare("SELECT id, field_label FROM form_fields WHERE application_type = ? AND is_active = 1");
                                                $fields_stmt->bind_param("s", $row['application_type']);
                                                $fields_stmt->execute();
                                                $fields_result = $fields_stmt->get_result();
                                                
                                                while ($field = $fields_result->fetch_assoc()) {
                                                    $form_fields[$row['application_type']][$field['id']] = $field['field_label'];
                                                }
                                            }

                                            // Extract name from applicant data
                                            if (!empty($applicant_data)) {
                                                foreach ($form_fields[$row['application_type']] as $field_id => $field_label) {
                                                    if (stripos($field_label, 'First Name') !== false || stripos($field_label, 'Firstname') !== false) {
                                                        $first_name = $applicant_data["field_$field_id"] ?? '';
                                                    }
                                                    if (stripos($field_label, 'Last Name') !== false || stripos($field_label, 'Lastname') !== false || stripos($field_label, 'Surname') !== false) {
                                                        $last_name = $applicant_data["field_$field_id"] ?? '';
                                                    }
                                                }
                                            }

                                            // If name fields are still empty, try to find any field containing 'name'
                                            if (empty($first_name) && empty($last_name) && !empty($applicant_data)) {
                                                foreach ($form_fields[$row['application_type']] as $field_id => $field_label) {
                                                    if (stripos($field_label, 'name') !== false) {
                                                        $full_name = $applicant_data["field_$field_id"] ?? '';
                                                        if (!empty($full_name)) {
                                                            $name_parts = explode(' ', $full_name);
                                                            $first_name = $name_parts[0] ?? '';
                                                            $last_name = implode(' ', array_slice($name_parts, 1)) ?? '';
                                                            break;
                                                        }
                                                    }
                                                }
                                            }

                                            // If still no name found, use application ID as identifier
                                            $display_name = trim($first_name . ' ' . $last_name);
                                            if (empty($display_name)) {
                                                $display_name = 'Application #' . $row['id'];
                                            }
                                            
                                            $reviewer_name = '';
                                            if ($row['reviewed_by']) {
                                                $reviewer_name = $row['reviewer_first_name'] . ' ' . $row['reviewer_last_name'];
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input application-checkbox" 
                                                           value="<?php echo $row['id']; ?>">
                                                </td>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($display_name); ?></td>
                                                <td><?php echo ucfirst((string)$row['application_type']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['status'] === 'pending' ? 'warning' : ($row['status'] === 'approved' ? 'success' : 'danger'); ?>">
                                                        <?php echo ucfirst((string)$row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($row['submission_date'])); ?></td>
                                                <td><?php echo $reviewer_name ? htmlspecialchars($reviewer_name) : '<span class="text-muted">Not reviewed</span>'; ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="view_application.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                        <?php if ($row['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-sm btn-success" onclick="updateStatus(<?php echo $row['id']; ?>, 'approved')">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="updateStatus(<?php echo $row['id']; ?>, 'rejected')">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No applications found</td>
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

        // Add new JavaScript for handling bulk downloads
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.application-checkbox');
            const downloadButton = document.getElementById('downloadSelected');

            // Toggle all checkboxes
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateDownloadButton();
            });

            // Update select all checkbox state
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    const someChecked = Array.from(checkboxes).some(c => c.checked);
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                    updateDownloadButton();
                });
            });

            // Update download button visibility
            function updateDownloadButton() {
                const checkedBoxes = document.querySelectorAll('.application-checkbox:checked');
                downloadButton.style.display = checkedBoxes.length > 0 ? 'block' : 'none';
            }

            // Handle bulk download
            downloadButton.addEventListener('click', function() {
                const selectedIds = Array.from(document.querySelectorAll('.application-checkbox:checked'))
                    .map(checkbox => checkbox.value);
                
                if (selectedIds.length > 0) {
                    window.location.href = 'download_application_pdf.php?ids=' + selectedIds.join(',');
                }
            });
        });
    </script>
</body>
</html> 