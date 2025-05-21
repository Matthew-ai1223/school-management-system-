<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();
$user = $auth->getCurrentUser();

// Base URL for assets
$base_url = "http://" . $_SERVER['HTTP_HOST'];

// Add a trailing slash to ensure proper path construction
if (substr($base_url, -1) !== '/') {
    $base_url = rtrim($base_url, '/');
}

// Debug the complete image path
$default_image_path = $base_url . '/backends/assets/images/default-user.png';
error_log("Default image path: " . $default_image_path);

// Alternative paths to try for the default user image
$alternative_paths = [
    $base_url . '/backends/assets/images/default-user.png',
    $base_url . '/assets/images/default-user.png',
    './assets/images/default-user.png',
    '../assets/images/default-user.png'
];
$alternative_paths_json = json_encode($alternative_paths);

// Get student ID from URL
$student_id = $_GET['id'] ?? 0;

// Fetch student details - get all columns from the students table
$query = "SELECT s.*, f.file_path, f.file_name 
          FROM students s 
          LEFT JOIN files f ON s.file_id = f.id 
          WHERE s.id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Debug the raw SQL and table structure
error_log("SQL Query: $query with student_id = $student_id");

// Get table structure to see all available columns
if ($student_id > 0) {
    $tableQuery = "SHOW COLUMNS FROM students";
    $tableResult = $mysqli->query($tableQuery);
    $columns = [];
    if ($tableResult) {
        while ($column = $tableResult->fetch_assoc()) {
            $columns[] = $column['Field'];
        }
        error_log("Students table columns: " . print_r($columns, true));
    } else {
        error_log("Error getting table structure: " . $mysqli->error);
    }
}

// Debug log
error_log("Student data for ID $student_id: " . print_r($student, true));

if (!$student) {
    header('Location: applications.php');
    exit;
}

// Get registration type
$registrationType = $student['registration_type'] ?? $student['application_type'] ?? '';

// Check registration number to determine type
$regNumber = $student['registration_number'] ?? '';
if (!empty($regNumber)) {
    if (strpos($regNumber, 'COL') !== false) {
        $registrationType = 'college';
    } elseif (strpos($regNumber, 'KID') !== false) {
        $registrationType = 'kiddies';
    }
}

// If still empty, normalize the registration type
if (empty($registrationType) && !empty($student['registration_type'] ?? $student['application_type'] ?? '')) {
    // Convert to lowercase and handle common variations
    $registrationType = strtolower($student['registration_type'] ?? $student['application_type'] ?? '');
    
    // Map aliases to standard values
    if ($registrationType == 'kid' || $registrationType == 'ace kiddies' || $registrationType == 'kiddies school') {
        $registrationType = 'kiddies';
    } elseif ($registrationType == 'col' || $registrationType == 'ace college' || $registrationType == 'college school') {
        $registrationType = 'college';
    }
}

// Debug the registration type values
error_log("Registration type data: " . 
          "Original registration_type=" . ($student['registration_type'] ?? 'NULL') . 
          ", application_type=" . ($student['application_type'] ?? 'NULL') .
          ", normalized registrationType=" . $registrationType);

// Ensure the registration type is valid
if (empty($registrationType) && !empty($student['id'])) {
    // If we can't determine type but have student ID, use a default
    $registrationType = 'unknown';
}

// Fetch all form fields for this registration type
$fields = [];
$fieldsQuery = "SELECT * FROM registration_form_fields WHERE is_active = 1 AND registration_type = ? ORDER BY field_order";
$fieldsStmt = $mysqli->prepare($fieldsQuery);
$fieldsStmt->bind_param('s', $registrationType);
$fieldsStmt->execute();
$fieldsResult = $fieldsStmt->get_result();
if ($fieldsResult && $fieldsResult->num_rows > 0) {
    while ($field = $fieldsResult->fetch_assoc()) {
        $fields[] = $field;
    }
}

// Debug output of form fields
error_log("Form fields for registration type '$registrationType': " . print_r($fields, true));

// Define field categories with their display order
$fieldCategories = [
    'student_info' => 'Student Information',
    'parent_info' => 'Parent/Guardian Information',
    'guardian_info' => 'Guardian Info',
    'medical_info' => 'Medical Background'
];

// Map fields to their categories
$categorized_fields = [
    'student_info' => [
        'first_name' => 'First Name',
        'last_name' => 'Last Name', 
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

// Organize student data fields by category
$categorizedFields = [
    'student_info' => [],
    'parent_info' => [],
    'guardian_info' => [],
    'medical_info' => [],
    'other' => []
];

// Map student fields into categories
$skipFields = ['id', 'file_id', 'file_path', 'file_name', 'created_at', 'updated_at', 'status', 'registration_number', 'registration_type', 'payment_reference'];

// Debug fields that begin with field_ prefix (which are custom form fields)
$customFields = [];
foreach (array_keys($student) as $field) {
    if (strpos($field, 'field_') === 0) {
        $customFields[$field] = $student[$field];
    }
}
error_log("Custom form fields found: " . print_r($customFields, true));

// Register field definitions by their db_field_name for quick lookup
$fieldDefinitions = [];
foreach ($fields as $field) {
    // Standard db field name (label converted to field name)
    $db_field_name = str_replace(' ', '_', strtolower($field['field_label']));
    $fieldDefinitions[$db_field_name] = $field;
    
    // Also register by field_ID format which is what the form uses
    $field_id_format = 'field_' . $field['id'];
    $fieldDefinitions[$field_id_format] = $field;
    
    error_log("Registered field definition: {$field['field_label']} as $db_field_name and $field_id_format");
}

// Process each student field
foreach ($student as $field => $value) {
    if (in_array($field, $skipFields) || is_null($value) || $value === '') continue;
    
    // Determine category
    $category = 'other';
    foreach ($categorized_fields as $cat => $catFields) {
        // Look at each key in the category fields
        foreach ($catFields as $fieldKey => $fieldLabel) {
            if (strpos($field, $fieldKey) !== false) {
                $category = $cat;
                break 2;
            }
        }
    }
    
    // Store field data with definitions if available
    $fieldInfo = [
        'name' => $field,
        'value' => $value,
        'label' => ucwords(str_replace('_', ' ', $field)),
        'field_type' => isset($fieldDefinitions[$field]) ? $fieldDefinitions[$field]['field_type'] : 'text'
    ];
    
    $categorizedFields[$category][] = $fieldInfo;
}

// Debug output of all student fields
error_log("All student fields: " . print_r(array_keys($student), true));
error_log("Student data keys: " . print_r($student, true)); // Full dump of all student data

// Add a debug display in HTML to troubleshoot
function addDebugSection() {
    global $student;
    if (isset($_GET['debug'])) {
        echo '<div class="card detail-card">';
        echo '<div class="card-header"><h4 class="mb-0">Debug Information</h4></div>';
        echo '<div class="card-body">';
        echo '<h5>All student data fields:</h5>';
        echo '<pre>' . htmlspecialchars(print_r($student, true)) . '</pre>';
        echo '</div></div>';
    }
}

// Process form field definitions and match them with student data or find them separately
foreach ($fields as $field) {
    $db_field_name = str_replace(' ', '_', strtolower($field['field_label']));
    
    // Debug the field being processed
    error_log("Processing field: {$field['field_label']} (DB field name: $db_field_name)");
    
    // Skip if already processed
    $alreadyProcessed = false;
    foreach ($categorizedFields as $category => $categoryFields) {
        foreach ($categoryFields as $existingField) {
            if ($existingField['name'] === $db_field_name) {
                $alreadyProcessed = true;
                error_log("Field $db_field_name already processed");
                break 2;
            }
        }
    }
    
    if ($alreadyProcessed) continue;
    
    // Try to find the field value in student data using various matching strategies
    $fieldValue = null;
    $matchedKey = null;
    
    // Strategy 1: Direct match with db_field_name
    if (isset($student[$db_field_name])) {
        $fieldValue = $student[$db_field_name];
        $matchedKey = $db_field_name;
        error_log("Direct match found for $db_field_name");
    } 
    // Strategy 2: Match with 'field_' prefix + field id
    elseif (isset($student['field_' . $field['id']])) {
        $fieldValue = $student['field_' . $field['id']];
        $matchedKey = 'field_' . $field['id'];
        error_log("ID-based match found for field_{$field['id']}");
    }
    // Strategy 3: Try partial matches by comparing each part of field name
    else {
        foreach ($student as $sKey => $sValue) {
            // Exact match of field label
            if (strtolower($sKey) === $db_field_name) {
                $fieldValue = $sValue;
                $matchedKey = $sKey;
                error_log("Exact name match found: $sKey");
                break;
            }
            
            // Partial match - does the database field contain this field name?
            if (strpos($sKey, $db_field_name) !== false) {
                $fieldValue = $sValue;
                $matchedKey = $sKey;
                error_log("Partial match found: $sKey contains $db_field_name");
                break;
            }
            
            // Reverse partial match - does the field name contain this database field?
            if (strpos($db_field_name, $sKey) !== false) {
                $fieldValue = $sValue;
                $matchedKey = $sKey;
                error_log("Reverse partial match found: $db_field_name contains $sKey");
                break;
            }
            
            // Word by word comparison
            $fieldNameWords = explode('_', $db_field_name);
            $dbFieldWords = explode('_', $sKey);
            
            $intersect = array_intersect($fieldNameWords, $dbFieldWords);
            if (count($intersect) > 0) {
                $fieldValue = $sValue;
                $matchedKey = $sKey;
                error_log("Word match found: $sKey matches $db_field_name through words: " . implode(', ', $intersect));
                break;
            }
        }
    }
    
    // Skip empty values 
    if (is_null($fieldValue) || $fieldValue === '') {
        error_log("No value found for field $db_field_name");
        continue;
    }
    
    // Determine category
    $category = 'other';
    foreach ($categorized_fields as $cat => $catFields) {
        // Look at each key in the category fields
        foreach ($catFields as $fieldKey => $fieldLabel) {
            if (strpos($db_field_name, $fieldKey) !== false) {
                $category = $cat;
                break 2;
            }
        }
    }
    
    error_log("Adding field $db_field_name to category $category with value $fieldValue");
    
    // Add to categorized fields
    $categorizedFields[$category][] = [
        'name' => $matchedKey ?: $db_field_name,
        'value' => $fieldValue,
        'label' => $field['field_label'],
        'field_type' => $field['field_type']
    ];
}

// Fetch payment history
$query = "SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$payments = $stmt->get_result();

// Fetch exam results
$query = "SELECT * FROM exam_results WHERE student_id = ? ORDER BY exam_date DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$exam_results = $stmt->get_result();

// Handle PDF generation
if (isset($_GET['pdf'])) {
    $pdf = new PDFGenerator();
    $pdf->AliasNbPages();
    
    switch ($_GET['pdf']) {
        case 'application':
            $pdf->generateApplicationForm($student);
            break;
        case 'payments':
            foreach ($payments as $payment) {
                $payment['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $pdf->generatePaymentReceipt($payment);
            }
            break;
        case 'results':
            foreach ($exam_results as $result) {
                $result['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $pdf->generateExamResult($result);
            }
            break;
        case 'full_profile':
            // Generate complete student profile with all data
            $student['categorized_fields'] = $categorizedFields;
            $student['payments'] = [];
            $student['exam_results'] = [];
            
            // Add payment data
            if ($payments && $payments->num_rows > 0) {
                $payments->data_seek(0);
                while ($payment = $payments->fetch_assoc()) {
                    $student['payments'][] = $payment;
                }
            }
            
            // Add exam results data
            if ($exam_results && $exam_results->num_rows > 0) {
                $exam_results->data_seek(0);
                while ($result = $exam_results->fetch_assoc()) {
                    $student['exam_results'][] = $result;
                }
            }
            
            $pdf->generateStudentProfile($student);
            break;
    }
    
    $pdf->Output();
    exit;
}

// Get student class/level information
$student_class = '';
$class_columns = ['class', 'level', 'grade', 'student_class'];
foreach ($class_columns as $column) {
    if (isset($student[$column]) && !empty($student[$column])) {
        $student_class = $student[$column];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - <?php echo SCHOOL_NAME; ?></title>
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
        .detail-card {
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
        .field-label {
            font-weight: 600;
            margin-bottom: 0.25rem;
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
                    <h2>Student Details</h2>
                    <div>
                        <div class="dropdown d-inline-block me-2">
                            <button class="btn btn-success dropdown-toggle" type="button" id="pdfDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-file-pdf"></i> Download PDF
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="pdfDropdown">
                                <li><a class="dropdown-item" href="student_details.php?id=<?php echo $student_id; ?>&pdf=full_profile">Complete Student Profile</a></li>
                                <li><a class="dropdown-item" href="student_details.php?id=<?php echo $student_id; ?>&pdf=application">Application Form</a></li>
                                <li><a class="dropdown-item" href="student_details.php?id=<?php echo $student_id; ?>&pdf=payments">Payment History</a></li>
                                <li><a class="dropdown-item" href="student_details.php?id=<?php echo $student_id; ?>&pdf=results">Exam Results</a></li>
                            </ul>
                        </div>
                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit Student
                        </a>
                        <a href="applications.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Applications
                        </a>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="card detail-card">
                    <div class="card-header">
                        <h4 class="mb-0">Student Information</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <?php 
                                // Simplified image source determination
                                $profile_image = $default_image_path;
                                
                                // Debug all possible paths
                                $debug_paths = [];
                                
                                // Only try to find a profile image if the student exists
                                if (!empty($student['id'])) {
                                    // First try to find the sanitized passport image
                                    if (!empty($student['registration_number'])) {
                                        $safe_registration = str_replace('/', '_', $student['registration_number']);
                                        
                                        // Direct path to the file that we confirmed exists
                                        $passport_file = $safe_registration . '.jpg';
                                        $passport_path = __DIR__ . '/../../uploads/student_passports/' . $passport_file;
                                        
                                        $debug_paths[] = "Direct path: " . $passport_path . " - Exists: " . (file_exists($passport_path) ? 'Yes' : 'No');
                                        
                                        if (file_exists($passport_path)) {
                                            // Use the relative URL for web access - this is key
                                            $profile_image = '../uploads/student_passports/' . $passport_file;
                                            $debug_paths[] = "Using image path: " . $profile_image;
                                        }
                                    }
                                    
                                    // If passport not found, try other image sources
                                    if ($profile_image == $default_image_path) {
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
                                }
                                
                                if (!empty($profile_image)): 
                                ?>
                                    <!-- Debug image path -->
                                    <div class="small text-muted mb-2">Image path: <?php echo $profile_image; ?></div>
                                    <div class="small text-muted mb-2">
                                        <?php 
                                        // Display all debug paths we checked
                                        foreach ($debug_paths as $debug_path) {
                                            echo $debug_path . "<br>";
                                        }
                                        
                                        // Convert URL to filesystem path for checks
                                        $filesystem_path = str_replace($base_url, $_SERVER['DOCUMENT_ROOT'], $profile_image);
                                        // Also try direct path in case of relative paths
                                        $direct_path = $_SERVER['DOCUMENT_ROOT'] . '/backends/uploads/student_files/' . basename($profile_image);
                                        ?>
                                        Filesystem path: <?php echo $filesystem_path; ?><br>
                                        Direct path: <?php echo $direct_path; ?><br>
                                        File exists (filesystem): <?php echo file_exists($filesystem_path) ? 'Yes' : 'No'; ?><br>
                                        File exists (direct): <?php echo file_exists($direct_path) ? 'Yes' : 'No'; ?><br>
                                        Readable: <?php echo is_readable($filesystem_path) ? 'Yes' : (is_readable($direct_path) ? 'Yes (direct)' : 'No'); ?><br>
                                        File size: <?php echo file_exists($filesystem_path) ? filesize($filesystem_path) . ' bytes' : (file_exists($direct_path) ? filesize($direct_path) . ' bytes' : 'N/A'); ?>
                                    </div>
                                    
                                    <img id="student-profile-image"
                                         src="<?php echo $profile_image; ?>" 
                                         alt="Student Photo" 
                                         class="img-fluid rounded-circle mb-2"
                                         style="max-width: 150px; height: 150px; object-fit: cover;"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiB2aWV3Qm94PSIwIDAgMjQgMjQiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzZjNzU3ZCIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik0yMCAyMXYtMmE0IDQgMCAwIDAtNC00SDhhNCA0IDAgMCAwLTQgNHYyIj48L3BhdGg+PGNpcmNsZSBjeD0iMTIiIGN5PSI3IiByPSI0Ij48L2NpcmNsZT48L3N2Zz4=';">
                                <?php else: ?>
                                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 150px; height: 150px; margin: 0 auto;">
                                        <i class="bi bi-person-fill" style="font-size: 4rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <h5 class="mt-2"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($student['registration_number'] ?? ''); ?></p>
                                
                                <span class="badge bg-<?php echo ($student['status'] ?? '') === 'pending' ? 'warning' : (($student['status'] ?? '') === 'registered' ? 'success' : 'danger'); ?> status-badge">
                                    <?php echo ucfirst($student['status'] ?? ''); ?>
                                </span>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <h5>Registration Information</h5>
                                        <hr>
                                    </div>
                                    
                                    <!-- Fixed/Known Fields -->
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Registration Type</div>
                                        <div>
                                            <?php 
                                            // Show debug info in small text and specific registration type in main text
                                            $displayType = '';
                                            if ($registrationType == 'kiddies') {
                                                $displayType = 'Ace Kiddies';
                                            } elseif ($registrationType == 'college') {
                                                $displayType = 'Ace College';
                                            } else {
                                                $displayType = ucfirst(str_replace('_', ' ', $registrationType));
                                            }
                                            echo $displayType; 
                                            
                                            // Add debug info in small text
                                            if (isset($_GET['debug'])) {
                                                echo '<div class="small text-muted mt-1">';
                                                echo 'Raw fields: ';
                                                echo 'registration_type=' . ($student['registration_type'] ?? 'NULL');
                                                echo ', application_type=' . ($student['application_type'] ?? 'NULL');
                                                echo ', normalized=' . $registrationType;
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Registration Date</div>
                                        <div><?php echo $student['created_at'] ? date('F j, Y', strtotime($student['created_at'])) : 'N/A'; ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Payment Reference</div>
                                        <div><?php echo htmlspecialchars($student['payment_reference'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <!-- Student Information -->
                                    <div class="col-12 mt-3 mb-3">
                                        <h5>Student Information</h5>
                                        <hr>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">First Name</div>
                                        <div><?php echo htmlspecialchars($student['first_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Last Name</div>
                                        <div><?php echo htmlspecialchars($student['last_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Date of Birth</div>
                                        <div><?php echo htmlspecialchars($student['date_of_birth'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Gender</div>
                                        <div><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Nationality</div>
                                        <div><?php echo htmlspecialchars($student['nationality'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">State</div>
                                        <div><?php echo htmlspecialchars($student['state'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Email</div>
                                        <div><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Class/Level</div>
                                        <div><?php echo !empty($student_class) ? htmlspecialchars($student_class) : 'Not assigned'; ?></div>
                                    </div>
                                    
                                    <div class="col-md-8 mb-3">
                                        <div class="field-label">Contact Address</div>
                                        <div><?php echo htmlspecialchars($student['contact_address'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <!-- Parent Information -->
                                    <div class="col-12 mt-3 mb-3">
                                        <h5>Parent/Guardian Information</h5>
                                        <hr>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Father's Name</div>
                                        <div><?php echo htmlspecialchars($student['father_s_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Father's Occupation</div>
                                        <div><?php echo htmlspecialchars($student['father_s_occupation'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-8 mb-3">
                                        <div class="field-label">Father's Office Address</div>
                                        <div><?php echo htmlspecialchars($student['father_s_office_address'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Father's Contact Phone Number(s)</div>
                                        <div><?php echo htmlspecialchars($student['father_s_contact_phone_number_s_'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Mother's Name</div>
                                        <div><?php echo htmlspecialchars($student['mother_s_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Mother's Occupation</div>
                                        <div><?php echo htmlspecialchars($student['mother_s_occupation'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-8 mb-3">
                                        <div class="field-label">Mother's Office Address</div>
                                        <div><?php echo htmlspecialchars($student['mother_s_office_address'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Mother's Contact Phone Number(s)</div>
                                        <div><?php echo htmlspecialchars($student['mother_s_contact_phone_number_s_'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <!-- Guardian Information -->
                                    <?php if (!empty($student['guardian_name']) || !empty($student['child_lives_with'])): ?>
                                    <div class="col-12 mt-3 mb-3">
                                        <h5>Guardian Information</h5>
                                        <hr>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Guardian Name</div>
                                        <div><?php echo htmlspecialchars($student['guardian_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Guardian Occupation</div>
                                        <div><?php echo htmlspecialchars($student['guardian_occupation'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Guardian Office Address</div>
                                        <div><?php echo htmlspecialchars($student['guardian_office_address'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Guardian Contact Phone Number</div>
                                        <div><?php echo htmlspecialchars($student['guardian_contact_phone_number'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="field-label">Child Lives With</div>
                                        <div><?php echo htmlspecialchars($student['child_lives_with'] ?? 'N/A'); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Medical Background -->
                                    <?php if (!empty($student['allergies']) || !empty($student['blood_group']) || !empty($student['genotype'])): ?>
                                    <div class="col-12 mt-3 mb-3">
                                        <h5>Medical Background</h5>
                                        <hr>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Blood Group</div>
                                        <div><?php echo htmlspecialchars($student['blood_group'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Genotype</div>
                                        <div><?php echo htmlspecialchars($student['genotype'] ?? 'N/A'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="field-label">Allergies</div>
                                        <div><?php echo htmlspecialchars($student['allergies'] ?? 'N/A'); ?></div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Display any other dynamic fields -->
                                    <?php foreach ($categorizedFields as $category => $categoryFields):
                                        // Skip categories that we've already handled above
                                        if (in_array($category, ['student_info', 'parent_info', 'guardian_info', 'medical_info'])) continue; 
                                        
                                        // Skip if no fields in this category
                                        if (empty($categoryFields)) continue;
                                    ?>
                                    <div class="col-12 mt-3 mb-3">
                                        <h5><?php 
                                        $categoryDisplayName = isset($fieldCategories[$category]) ? $fieldCategories[$category] : ucfirst(str_replace('_', ' ', $category));
                                        echo $categoryDisplayName;
                                        ?></h5>
                                        <hr>
                                    </div>
                                    
                                    <?php 
                                    foreach ($categoryFields as $key => $fieldInfo): 
                                        if (is_array($fieldInfo)) {
                                            $field_name = $fieldInfo['name'];
                                            $field_label = $fieldInfo['label'];
                                            $field_value = $student[$field_name] ?? $fieldInfo['value'] ?? 'N/A';
                                        } else {
                                            $field_name = $key;
                                            $field_label = $fieldInfo;
                                            $field_value = $student[$field_name] ?? 'N/A';
                                        }
                                    ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="field-label"><?php echo htmlspecialchars($field_label); ?></div>
                                            <div><?php echo htmlspecialchars($field_value); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['debug'])) addDebugSection(); ?>

                <!-- Payment History -->
                <div class="card detail-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Payment History</h4>
                        <a href="update_student_payment.php?student_id=<?php echo $student_id; ?>&redirect=student" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle"></i> Record New Payment
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($payments->num_rows > 0): ?>
                                        <?php $payments->data_seek(0); // Reset result pointer ?>
                                        <?php while ($payment = $payments->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                                <td><?php echo formatPaymentType($payment['payment_type'] ?? ''); ?></td>
                                                <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['reference_number'] ?? $payment['reference'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php echo formatPaymentStatus($payment['status']); ?>
                                                </td>
                                                <td>
                                                    <a href="payment_details.php?id=<?php echo $payment['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($payment['status'] === 'pending'): ?>
                                                    <a href="update_payment_status.php?id=<?php echo $payment['id']; ?>&student_id=<?php echo $student_id; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No payment history found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="update_student_payment.php?student_id=<?php echo $student_id; ?>&redirect=student" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Record New Payment
                        </a>
                        <?php if ($payments->num_rows > 0): ?>
                        <a href="payments.php?student_id=<?php echo $student_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-list"></i> View All Payments
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Exam Results -->
                <div class="card detail-card">
                    <div class="card-header">
                        <h4 class="mb-0">Exam Results</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Exam Date</th>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($exam_results->num_rows > 0): ?>
                                        <?php while ($result = $exam_results->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d', strtotime($result['exam_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($result['subject']); ?></td>
                                                <td><?php echo $result['score']; ?></td>
                                                <td><?php echo htmlspecialchars($result['grade']); ?></td>
                                                <td><?php echo htmlspecialchars($result['remarks']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No exam results found</td>
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
                    imgElement.src = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiB2aWV3Qm94PSIwIDAgMjQgMjQiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzZjNzU3ZCIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik0yMCAyMXYtMmE0IDQgMCAwIDAtNC00SDhhNCA0IDAgMCAwLTQgNHYyIj48L3BhdGg+PGNpcmNsZSBjeD0iMTIiIGN5PSI3IiByPSI0Ij48L2NpcmNsZT48L3N2Zz4=';
                }
            };
            
            tryNextPath();
        }
    </script>
</body>
</html> 