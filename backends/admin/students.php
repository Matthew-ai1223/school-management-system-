<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

// Base URL for assets
$base_url = "http://" . $_SERVER['HTTP_HOST'];

// Add a trailing slash to ensure proper path construction
if (substr($base_url, -1) !== '/') {
    $base_url = rtrim($base_url, '/');
}

// Debug the complete image path
$default_image_path = $base_url . '/backends/assets/images/default-user.png';
error_log("Default image path: " . $default_image_path);

// Get filter parameters
$application_type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Define which fields to display in the list view
$display_fields = [
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'email' => 'Email',
    'phone' => 'Phone',
    'father_s_name' => 'Father\'s Name',
    'mother_s_name' => 'Mother\'s Name',
    'date_of_birth' => 'Date of Birth',
    'gender' => 'Gender'
];

// Define fields by category for the detailed view
$field_categories = [
    'student_info' => [
        'date_of_birth' => 'Date of Birth',
        'gender' => 'Gender',
        'nationality' => 'Nationality',
        'state' => 'State',
        'contact_address' => 'Contact Address',
        'email' => 'Email'
    ],
    'parent_info' => [
        'father_s_name' => 'Father\'s Name',
        'father_s_occupation' => 'Father\'s Occupation',
        'father_s_office_address' => 'Father\'s Office Address',
        'father_s_contact_phone_number_s_' => 'Father\'s Contact Phone Number(s)',
        'mother_s_name' => 'Mother\'s Name',
        'mother_s_occupation' => 'Mother\'s Occupation',
        'mother_s_office_address' => 'Mother\'s Office Address',
        'mother_s_contact_phone_number_s_' => 'Mother\'s Contact Phone Number(s)'
    ],
    'guardian_info' => [
        'guardian_name' => 'Guardian Name',
        'guardian_occupation' => 'Guardian Occupation',
        'guardian_office_address' => 'Guardian Office Address',
        'guardian_contact_phone_number' => 'Guardian Contact Phone Number',
        'child_lives_with' => 'Child Lives With'
    ],
    'medical_info' => [
        'allergies' => 'Allergies',
        'blood_group' => 'Blood Group',
        'genotype' => 'Genotype'
    ]
];

// Get all possible student fields from registration_form_fields
$allFields = [];
$fieldsQuery = "SELECT field_label, registration_type FROM registration_form_fields WHERE is_active = 1 ORDER BY field_order";
$fieldsResult = $mysqli->query($fieldsQuery);
if ($fieldsResult && $fieldsResult->num_rows > 0) {
    while ($field = $fieldsResult->fetch_assoc()) {
        $db_field_name = str_replace(' ', '_', strtolower($field['field_label']));
        if (!in_array($db_field_name, array_keys($display_fields))) {
            $allFields[$db_field_name] = [
                'label' => $field['field_label'],
                'type' => $field['registration_type'] ?? 'all'
            ];
        }
    }
}

// Debug log
error_log("Filter parameters: type=$application_type, status=$status, search=$search");

// Base query - get all columns to support dynamic fields
$query = "SELECT s.* FROM students s WHERE 1=1";
$params = [];
$types = "";

// Add filters
if (!empty($application_type)) {
    // Check both application_type and registration_type fields
    $query .= " AND (s.application_type = ? OR s.registration_type = ?)";
    $params[] = $application_type;
    $params[] = $application_type;
    $types .= "ss";
}

if (!empty($status)) {
    $query .= " AND s.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    // Extend search to include more fields like email, phone
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.registration_number LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sssss";
}

// Add ordering and limit the results to improve performance
$query .= " ORDER BY s.created_at DESC LIMIT 100";

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
    $students_data = [];
    
    while ($row = $result->fetch_assoc()) {
        $student_ids[] = $row['id'];
        $students_data[$row['id']] = $row;
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
        @media (min-width: 768px) {
            .main-content {
                margin-left: 25%; /* Adjust based on sidebar width */
            }
        }
        @media (min-width: 992px) {
            .main-content {
                margin-left: 16.667%; /* Adjust for col-lg-2 width */
            }
        }
        .dropdown-item.active {
            background-color: #e9ecef;
            color: #000;
        }
        .status-btn {
            min-width: 100px;
        }
        .student-table th, .student-table td {
            vertical-align: middle;
        }
        .dropdown-menu {
            max-height: 300px;
            overflow-y: auto;
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
                                <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone or registration number" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Choose Columns Dropdown -->
                <div class="d-flex justify-content-end mb-3">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="columnSelector" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear"></i> Display Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnSelector">
                            <!-- Default fields -->
                            <li><a class="dropdown-item" href="#" onclick="return false;"><strong>Standard Fields</strong></a></li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <?php foreach ($display_fields as $field_name => $field_label): ?>
                                <li>
                                    <div class="dropdown-item">
                                        <div class="form-check">
                                            <input class="form-check-input toggle-column" type="checkbox" value="<?php echo $field_name; ?>" id="column_<?php echo $field_name; ?>" checked data-column="<?php echo array_search($field_name, array_keys($display_fields)) + 3; ?>">
                                            <label class="form-check-label" for="column_<?php echo $field_name; ?>">
                                                <?php echo $field_label; ?>
                                            </label>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            
                            <!-- Dynamic fields from registration form -->
                            <?php if (!empty($allFields)): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="return false;"><strong>Dynamic Fields</strong></a></li>
                                <li><hr class="dropdown-divider"></li>
                                
                                <?php foreach ($allFields as $field_name => $field_info): 
                                    // Only show fields for the selected application type or fields for all types
                                    if (!empty($application_type) && $field_info['type'] !== 'all' && $field_info['type'] !== $application_type) continue;
                                ?>
                                    <li>
                                        <div class="dropdown-item">
                                            <div class="form-check">
                                                <input class="form-check-input toggle-column" type="checkbox" value="<?php echo $field_name; ?>" id="column_<?php echo $field_name; ?>" data-column="additional" data-field="<?php echo $field_name; ?>">
                                                <label class="form-check-label" for="column_<?php echo $field_name; ?>">
                                                    <?php echo $field_info['label']; ?>
                                                </label>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped student-table">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Reg Number</th>
                                        <th>Type</th>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Father's Name</th>
                                        <th>Mother's Name</th>
                                        <th>Date of Birth</th>
                                        <th>Gender</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($student = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    // Simplified image source determination
                                                    $profile_image = $default_image_path;
                                                    
                                                    // Only try to find a profile image if the student ID exists
                                                    if (!empty($student['id'])) {
                                                        if (!empty($student['file_path'])) {
                                                            $profile_image = str_replace('../../', $base_url . '/backends/', $student['file_path']);
                                                        } 
                                                        elseif (!empty($student['file'])) {
                                                            $profile_image = $base_url . '/backends/student/uploads/student_files/' . $student['file'];
                                                        }
                                                        elseif (!empty($student['passport'])) {
                                                            $profile_image = $base_url . '/backends/student/uploads/student_files/' . $student['passport'];
                                                        }
                                                        elseif (!empty($student['profile_picture'])) {
                                                            $profile_image = $base_url . '/backends/student/uploads/student_files/' . $student['profile_picture'];
                                                        }
                                                        elseif (!empty($student['photo'])) {
                                                            $profile_image = $base_url . '/backends/student/uploads/student_files/' . $student['photo'];
                                                        }
                                                        elseif (!empty($student['student_photo'])) {
                                                            $profile_image = $base_url . '/backends/student/uploads/student_files/' . $student['student_photo'];
                                                        }
                                                    }
                                                    
                                                    // Try alternative paths if the main path doesn't work
                                                    $alternative_paths = [
                                                        $base_url . '/backends/assets/images/default-user.png',
                                                        $base_url . '/assets/images/default-user.png',
                                                        './assets/images/default-user.png',
                                                        '../assets/images/default-user.png'
                                                    ];
                                                    ?>
                                                    
                                                    <img src="<?php echo $profile_image; ?>" 
                                                        alt="Student Photo" 
                                                        class="rounded-circle"
                                                        style="width: 40px; height: 40px; object-fit: cover;"
                                                        onerror="this.onerror=null; tryAlternativeImages(this, <?php echo htmlspecialchars(json_encode($alternative_paths)); ?>)">
                                                </td>
                                                <td><?php echo htmlspecialchars($student['registration_number'] ?? ''); ?></td>
                                                <td>
                                                    <?php 
                                                    // Get the registration type with fallbacks and better handling
                                                    $studentType = $student['registration_type'] ?? $student['application_type'] ?? '';
                                                    
                                                    // Check registration number to determine type
                                                    $regNumber = $student['registration_number'] ?? '';
                                                    if (!empty($regNumber)) {
                                                        if (strpos($regNumber, 'COL') !== false) {
                                                            $studentType = 'college';
                                                        } elseif (strpos($regNumber, 'KID') !== false) {
                                                            $studentType = 'kiddies';
                                                        }
                                                    }
                                                    
                                                    // If still empty, normalize the registration type
                                                    if (empty($studentType) && !empty($student['registration_type'] ?? $student['application_type'] ?? '')) {
                                                        // Convert to lowercase and handle common variations
                                                        $studentType = strtolower($student['registration_type'] ?? $student['application_type'] ?? '');
                                                        
                                                        // Map aliases to standard values
                                                        if ($studentType == 'kid' || $studentType == 'ace kiddies' || $studentType == 'kiddies school') {
                                                            $studentType = 'kiddies';
                                                        } elseif ($studentType == 'col' || $studentType == 'ace college' || $studentType == 'college school') {
                                                            $studentType = 'college';
                                                        }
                                                    }
                                                    
                                                    // If it's still empty but we have a student record, show 'Unknown'
                                                    if (empty($studentType) && !empty($student['id'])) {
                                                        $studentType = 'unknown';
                                                    }
                                                    
                                                    // Display in proper format matching registration form
                                                    if ($studentType == 'kiddies') {
                                                        echo 'Ace Kiddies';
                                                    } elseif ($studentType == 'college') {
                                                        echo 'Ace College';
                                                    } else {
                                                        echo ucfirst($studentType);
                                                    }
                                                    
                                                    // Debug info if needed
                                                    if (isset($_GET['debug'])) {
                                                        echo '<div class="small text-muted">';
                                                        echo 'Raw: ' . ($student['registration_type'] ?? 'NULL');
                                                        echo ' / ' . ($student['application_type'] ?? 'NULL');
                                                        echo ' / Reg#: ' . $regNumber;
                                                        echo ' → ' . $studentType;
                                                        echo '</div>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['first_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['last_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['email'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['phone'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['father_s_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['mother_s_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['gender'] ?? ''); ?></td>
                                                
                                                <!-- All additional dynamic field data is stored as data attributes for JS to handle -->
                                                <?php foreach ($allFields as $field_name => $field_info): ?>
                                                    <td class="d-none dynamic-field" data-field="<?php echo $field_name; ?>">
                                                        <?php echo htmlspecialchars($student[$field_name] ?? ''); ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                
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
                                            <td colspan="12" class="text-center">No students found</td>
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
        // Function to try alternative image paths when the main one fails
        function tryAlternativeImages(imgElement, alternativePaths) {
            let currentPathIndex = 0;
            
            const tryNextPath = () => {
                if (currentPathIndex < alternativePaths.length) {
                    imgElement.src = alternativePaths[currentPathIndex];
                    currentPathIndex++;
                    // If this path also fails, try the next one
                    imgElement.onerror = tryNextPath;
                } else {
                    // If all paths fail, use a data URI for a generic user icon
                    imgElement.onerror = null;
                    imgElement.src = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9ImN1cnJlbnRDb2xvciIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIGNsYXNzPSJmZWF0aGVyIGZlYXRoZXItdXNlciI+PHBhdGggZD0iTTIwIDIxdi0yYTQgNCAwIDAgMC00LTRINGEyIDQgMCAwIDAtNCA0djIiPjwvcGF0aD48Y2lyY2xlIGN4PSIxMiIgY3k9IjciIHI9IjQiPjwvY2lyY2xlPjwvc3ZnPg==';
                }
            };
            
            tryNextPath();
        }

        // Initialize column visibility
        document.addEventListener('DOMContentLoaded', function() {
            // Initially hide some columns for better display
            toggleColumn('email', false);
            toggleColumn('phone', false);
            
            // Setup column toggle listeners
            const toggles = document.querySelectorAll('.toggle-column');
            toggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    if (this.dataset.column === 'additional') {
                        toggleDynamicColumn(this.dataset.field, this.checked);
                    } else {
                        toggleColumn(this.value, this.checked);
                    }
                });
            });
        });
        
        // Toggle standard column visibility
        function toggleColumn(fieldName, visible) {
            try {
                const table = document.querySelector('.student-table');
                if (!table) return;
                
                const headerIndex = Array.from(table.querySelectorAll('thead th')).findIndex(
                    th => th.textContent.toLowerCase().includes(fieldName.replace('_', ' '))
                );
                
                if (headerIndex > -1) {
                    // Toggle header
                    table.querySelectorAll('thead th')[headerIndex].style.display = visible ? '' : 'none';
                    
                    // Toggle all cells in that column
                    table.querySelectorAll('tbody tr').forEach(row => {
                        const cells = row.querySelectorAll('td');
                        if (headerIndex < cells.length) {
                            cells[headerIndex].style.display = visible ? '' : 'none';
                        }
                    });
                }
            } catch (err) {
                console.error('Error in toggleColumn:', err);
            }
        }
        
        // Toggle dynamic column visibility
        function toggleDynamicColumn(fieldName, visible) {
            try {
                const table = document.querySelector('.student-table');
                if (!table) return;
                
                if (visible) {
                    // Add header if it doesn't exist
                    if (!document.querySelector(`th[data-field="${fieldName}"]`)) {
                        const headerRow = table.querySelector('thead tr');
                        const newHeader = document.createElement('th');
                        newHeader.setAttribute('data-field', fieldName);
                        newHeader.textContent = fieldName.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                        
                        // Insert before Status column
                        const statusCell = headerRow.querySelector('th:nth-last-child(2)');
                        if (statusCell) {
                            headerRow.insertBefore(newHeader, statusCell);
                            
                            // Add cells to each row
                            table.querySelectorAll('tbody tr').forEach(row => {
                                const dynamicField = row.querySelector(`.dynamic-field[data-field="${fieldName}"]`);
                                const newCell = document.createElement('td');
                                newCell.setAttribute('data-field', fieldName);
                                newCell.textContent = dynamicField ? dynamicField.textContent : '';
                                
                                const statusCell = row.querySelector('td:nth-last-child(2)');
                                if (statusCell) {
                                    row.insertBefore(newCell, statusCell);
                                }
                            });
                        }
                    } else {
                        // Show existing column
                        table.querySelectorAll(`[data-field="${fieldName}"]`).forEach(el => {
                            el.style.display = '';
                        });
                    }
                } else {
                    // Hide column
                    table.querySelectorAll(`[data-field="${fieldName}"]`).forEach(el => {
                        el.style.display = 'none';
                    });
                }
            } catch (err) {
                console.error('Error in toggleDynamicColumn:', err);
            }
        }

        function updateStatus(id, status) {
            try {
                console.log('Updating status:', { id, status });
                
                const dropdown = event && event.target ? event.target.closest('.dropdown') : null;
                if (!dropdown) {
                    console.error('Cannot find dropdown element');
                    return;
                }
                
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
                        const statusBtn = dropdown.querySelector('.status-btn');
                        if (statusBtn) {
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
                        }
                        
                        // Update dropdown active state
                        const dropdownMenu = event.target.closest('.dropdown-menu');
                        if (dropdownMenu) {
                            const dropdownItems = dropdownMenu.querySelectorAll('.dropdown-item');
                            dropdownItems.forEach(item => {
                                item.classList.remove('active');
                                if (item.textContent.toLowerCase() === status) {
                                    item.classList.add('active');
                                }
                            });
                        }
                    } else {
                        console.error('Error from server:', data.message);
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error details:', error);
                    alert('An error occurred while updating the status: ' + error.message);
                });
            } catch (err) {
                console.error('Error in updateStatus:', err);
                alert('An error occurred in the updateStatus function');
            }
        }

        function deleteStudent(id) {
            try {
                if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                    fetch('delete_student.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(id)
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
            } catch (err) {
                console.error('Error in deleteStudent:', err);
                alert('An error occurred in the deleteStudent function');
            }
        }
    </script>
</body>
</html> 