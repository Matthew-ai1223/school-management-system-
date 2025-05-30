<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../payment_config.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();

$id = $_GET['id'] ?? 0;

// Get application details
$stmt = $mysqli->prepare("SELECT * FROM applications WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();

if (!$application) {
    die("Application not found");
}

// Get payment details
$payment_data = null;
$applicant_data = json_decode($application['applicant_data'], true);
if (isset($applicant_data['payment_reference'])) {
    $stmt = $mysqli->prepare("SELECT * FROM application_payments WHERE reference = ?");
    $stmt->bind_param("s", $applicant_data['payment_reference']);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    $payment_data = $payment_result->fetch_assoc();
}

// Get reviewer details if application was reviewed
$reviewer_name = '';
if ($application['reviewed_by']) {
    $stmt = $mysqli->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $application['reviewed_by']);
    $stmt->execute();
    $reviewer_result = $stmt->get_result();
    if ($reviewer = $reviewer_result->fetch_assoc()) {
        $reviewer_name = $reviewer['first_name'] . ' ' . $reviewer['last_name'];
    }
}

// Define application form fields
function getApplicationFields($applicationType) {
    $fields = [
        // Student Information
        [
            'id' => 'full_name',
            'field_label' => 'Full Name',
            'field_type' => 'text',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'dob',
            'field_label' => 'Date of Birth',
            'field_type' => 'date',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'gender',
            'field_label' => 'Gender',
            'field_type' => 'select',
            'options' => 'Male,Female',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'previous_school',
            'field_label' => 'Previous School Attended',
            'field_type' => 'text',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'class_admission',
            'field_label' => 'Class Seeking Admission Into',
            'field_type' => 'select',
            'options' => $applicationType === 'kiddies' ? 
                'Pre-Nursery,Nursery 1,Nursery 2,Primary 1,Primary 2,Primary 3,Primary 4,Primary 5,Primary 6' : 
                'JSS 1,JSS 2,JSS 3,SS 1,SS 2,SS 3',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        [
            'id' => 'passport_photo',
            'field_label' => 'Passport Photograph',
            'field_type' => 'file',
            'required' => true,
            'field_group' => 'Student Information'
        ],
        
        // Parent Information
        [
            'id' => 'parent_name',
            'field_label' => 'Parent/Guardian Name',
            'field_type' => 'text',
            'required' => true,
            'field_group' => 'Parent Information'
        ],
        [
            'id' => 'parent_phone',
            'field_label' => 'Parent/Guardian Phone Number',
            'field_type' => 'text',
            'required' => true,
            'field_group' => 'Parent Information'
        ],
        [
            'id' => 'parent_email',
            'field_label' => 'Parent/Guardian Email',
            'field_type' => 'email',
            'required' => true,
            'field_group' => 'Parent Information'
        ],
        [
            'id' => 'home_address',
            'field_label' => 'Home Address',
            'field_type' => 'textarea',
            'required' => true,
            'field_group' => 'Parent Information'
        ]
    ];
    
    return $fields;
}

// Helper function to format field value based on type
function formatFieldValue($field, $value) {
    switch ($field['field_type']) {
        case 'file':
            if ($value) {
                // Handle both absolute and relative paths
                $file_path = $value;
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    // If it's not a URL, assume it's a relative path
                    $file_path = '../../' . ltrim($value, '/');
                }
                $file_name = basename($value);
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Get icon based on file type
                $icon_class = 'bi-file-earmark';
                switch ($file_extension) {
                    case 'pdf':
                        $icon_class = 'bi-file-earmark-pdf';
                        break;
                    case 'doc':
                    case 'docx':
                        $icon_class = 'bi-file-earmark-word';
                        break;
                    case 'xls':
                    case 'xlsx':
                        $icon_class = 'bi-file-earmark-excel';
                        break;
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                        $icon_class = 'bi-file-earmark-image';
                        
                        // For passport photo, display the image directly
                        if ($field['id'] === 'passport_photo') {
                            return sprintf(
                                '<div class="passport-preview">
                                    <img src="%s" alt="Passport" class="img-fluid rounded">
                                    <div class="mt-2">
                                        <a href="%s" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bi %s"></i> View Full Size
                                        </a>
                                    </div>
                                </div>',
                                htmlspecialchars($file_path),
                                htmlspecialchars($file_path),
                                $icon_class
                            );
                        }
                        
                        break;
                }
                
                return sprintf(
                    '<div class="file-preview mb-2">
                        <a href="%s" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi %s"></i> View %s
                        </a>
                        <small class="text-muted d-block mt-1">%s</small>
                    </div>',
                    htmlspecialchars($file_path),
                    $icon_class,
                    htmlspecialchars($file_name),
                    formatFileSize($file_path)
                );
            }
            return '<span class="text-muted">No file uploaded</span>';
        
        case 'select':
            // Highlight the class admission field with a badge
            if ($field['id'] === 'class_admission') {
                return sprintf('<span class="badge bg-primary">%s</span>', htmlspecialchars($value));
            }
            return htmlspecialchars($value);
            
        case 'date':
            return $value ? date('Y-m-d', strtotime($value)) : '';
            
        case 'textarea':
            return nl2br(htmlspecialchars($value));
            
        case 'email':
            return $value ? '<a href="mailto:' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</a>' : '';
            
        default:
            return htmlspecialchars($value);
    }
}

// Helper function to format file size
function formatFileSize($file_path) {
    if (!file_exists($file_path)) {
        return 'File not found';
    }
    
    $size = filesize($file_path);
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --transition-speed: 0.3s;
            --sidebar-width: 260px;
        }
        
        body {
            background-color: #f5f8fa;
            color: var(--dark-color);
            padding-left: 0;
            overflow-x: hidden;
        }
        
        @media (min-width: 768px) {
            body {
                padding-left: var(--sidebar-width);
            }
            
            .container-fluid {
                padding-left: 0;
                padding-right: 0;
                width: 100%;
            }
            
            .main-content {
                width: calc(100% - var(--sidebar-width));
                margin-left: auto;
                padding-left: 30px;
                padding-right: 30px;
            }
        }
        
        @media (max-width: 767.98px) {
            body {
                padding-left: 0;
            }
            
            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 15px;
            }
            
            .container-fluid {
                padding: 0;
            }
        }
        
        .field-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .field-value {
            margin-bottom: 0.5rem;
        }
        
        .card {
            box-shadow: var(--card-shadow);
            border: none;
            border-radius: 0.5rem;
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.12);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 1rem 1.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            margin-left: 0.75rem;
            border-radius: 30px;
            font-weight: 500;
        }
        
        .passport-preview {
            text-align: center;
            padding: 1rem;
            background: var(--light-color);
            border-radius: 0.5rem;
            transition: transform var(--transition-speed);
        }
        
        .passport-preview:hover {
            transform: scale(1.03);
        }
        
        .passport-preview img {
            max-width: 200px;
            max-height: 200px;
            border: 5px solid #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 0.25rem;
            transition: box-shadow var(--transition-speed);
        }
        
        .passport-preview img:hover {
            box-shadow: 0 0 20px rgba(0,0,0,0.18);
        }
        
        .field-group {
            background: var(--light-color);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            transition: background-color var(--transition-speed);
        }
        
        .field-group:hover {
            background: #f0f4f8;
        }
        
        .field-group-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .btn {
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all var(--transition-speed);
        }
        
        .btn-primary, .btn-success, .btn-danger, .btn-secondary {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-primary:hover, .btn-success:hover, .btn-danger:hover, .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .badge {
            padding: 0.5em 0.75em;
            border-radius: 30px;
            font-weight: 500;
        }
        
        .user-info {
            margin-bottom: 1.5rem;
        }
        
        /* Improved comment area */
        textarea.form-control {
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 0.375rem;
            padding: 0.75rem;
            min-height: 100px;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        
        textarea.form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4 ms-auto main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <?php echo ucfirst((string)$application['application_type']); ?> Application Details
                        <span class="badge bg-<?php 
                            echo $application['status'] === 'pending' ? 'warning' : 
                                ($application['status'] === 'approved' ? 'success' : 'danger'); 
                            ?> status-badge">
                            <?php echo ucfirst((string)$application['status']); ?>
                        </span>
                    </h2>
                    <div>
                        <a href="applications.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Applications
                        </a>
                        <?php if (trim(strtolower((string)$application['status'])) === 'pending'): ?>
                        <button class="btn btn-success" onclick="updateStatus(<?php echo $id; ?>, 'approved')">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button class="btn btn-danger" onclick="updateStatus(<?php echo $id; ?>, 'rejected')">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Application Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="field-label">Application ID</div>
                                <div class="field-value">#<?php echo $application['id']; ?></div>
                            </div>
                            <div class="col-md-3">
                                <div class="field-label">Submission Date</div>
                                <div class="field-value">
                                    <?php echo date('M d, Y H:i', strtotime($application['submission_date'])); ?>
                                </div>
                            </div>
                            <?php if ($application['reviewed_by']): ?>
                            <div class="col-md-3">
                                <div class="field-label">Reviewed By</div>
                                <div class="field-value"><?php echo htmlspecialchars($reviewer_name); ?></div>
                            </div>
                            <div class="col-md-3">
                                <div class="field-label">Review Date</div>
                                <div class="field-value">
                                    <?php echo date('M d, Y H:i', strtotime($application['review_date'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($application['comments']): ?>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="field-label">Review Comments</div>
                                <div class="field-value">
                                    <?php echo nl2br(htmlspecialchars($application['comments'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="row mt-3">
                            <div class="col-12">
                                <a href="download_application_pdf.php?id=<?php echo $application['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-download"></i> Download Application Details (PDF)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Applicant Information Summary Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Applicant Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <?php 
                                $passport_path = '';
                                if (isset($applicant_data['field_passport_photo'])) {
                                    $passport_path = '../../' . ltrim($applicant_data['field_passport_photo'], '/');
                                    echo '<img src="' . htmlspecialchars($passport_path) . '" alt="Passport" class="img-fluid rounded" style="max-height: 150px; max-width: 150px; border: 5px solid #fff; box-shadow: 0 0 20px rgba(0,0,0,0.1);">';
                                } else {
                                    echo '<div class="p-5 bg-light rounded"><i class="bi bi-person" style="font-size: 3rem; color: #6c757d;"></i></div>';
                                }
                                ?>
                            </div>
                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="field-label">Full Name</div>
                                        <div class="field-value">
                                            <h5><?php echo htmlspecialchars($applicant_data['field_full_name'] ?? 'N/A'); ?></h5>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="field-label">Class Seeking Admission Into</div>
                                        <div class="field-value">
                                            <h5><span class="badge bg-primary"><?php echo htmlspecialchars($applicant_data['field_class_admission'] ?? 'N/A'); ?></span></h5>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="field-label">Parent/Guardian Name</div>
                                        <div class="field-value">
                                            <?php echo htmlspecialchars($applicant_data['field_parent_name'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="field-label">Parent/Guardian Contact</div>
                                        <div class="field-value">
                                            <?php 
                                            $phone = $applicant_data['field_parent_phone'] ?? '';
                                            $email = $applicant_data['field_parent_email'] ?? '';
                                            echo $phone ? htmlspecialchars($phone) : '';
                                            echo $phone && $email ? ' | ' : '';
                                            echo $email ? '<a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a>' : '';
                                            echo !$phone && !$email ? 'N/A' : '';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Information Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($payment_data): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="field-label">Payment Reference</div>
                                <div class="field-value">
                                    <?php echo htmlspecialchars($payment_data['reference']); ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="field-label">Amount Paid</div>
                                <div class="field-value">
                                    ₦<?php echo number_format($payment_data['amount'], 2); ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="field-label">Payment Status</div>
                                <div class="field-value">
                                    <span class="badge bg-<?php 
                                        echo $payment_data['status'] === 'completed' ? 'success' : 
                                            ($payment_data['status'] === 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst((string)$payment_data['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="field-label">Payment Date</div>
                                <div class="field-value">
                                    <?php echo $payment_data['payment_date'] ? 
                                        date('M d, Y H:i', strtotime($payment_data['payment_date'])) : 
                                        '<span class="text-muted">Not completed</span>'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <div class="field-label">Payment Method</div>
                                <div class="field-value">
                                    <?php echo ucfirst((string)$payment_data['payment_method']); ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="field-label">Email Used</div>
                                <div class="field-value">
                                    <a href="mailto:<?php echo htmlspecialchars($payment_data['email']); ?>">
                                        <?php echo htmlspecialchars($payment_data['email']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="field-label">Phone Number</div>
                                <div class="field-value">
                                    <?php echo htmlspecialchars($payment_data['phone']); ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($payment_data['transaction_reference']): ?>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="field-label">Transaction Reference</div>
                                <div class="field-value">
                                    <?php echo htmlspecialchars($payment_data['transaction_reference']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle"></i>
                            No payment information found for this application.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Application Details Section -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Application Details</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Get fields for the application type
                        $form_fields = getApplicationFields($application['application_type']);
                        
                        // Group fields by their group
                        $field_groups = [];
                        foreach ($form_fields as $field) {
                            $group = $field['field_group'] ?? 'Other Information';
                            if (!isset($field_groups[$group])) {
                                $field_groups[$group] = [];
                            }
                            $field_groups[$group][] = $field;
                        }
                        
                        // Display fields by group
                        foreach ($field_groups as $group_name => $group_fields):
                        ?>
                        <div class="field-group">
                            <h4 class="field-group-title"><?php echo htmlspecialchars($group_name); ?></h4>
                            <div class="row">
                                <?php foreach ($group_fields as $field): 
                                    $field_value = $applicant_data['field_' . $field['id']] ?? '';
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="field-label">
                                        <?php echo htmlspecialchars($field['field_label']); ?>
                                        <?php if ($field['required']): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="field-value">
                                        <?php echo formatFieldValue($field, $field_value); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (trim(strtolower((string)$application['status'])) === 'pending'): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Add Review Comments</h5>
                    </div>
                    <div class="card-body">
                        <form id="reviewForm" class="row g-3">
                            <div class="col-12">
                                <textarea class="form-control" id="comments" rows="3" 
                                    placeholder="Enter your comments about this application"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-success" onclick="updateStatus(<?php echo $id; ?>, 'approved')">
                                    <i class="bi bi-check-circle"></i> Approve with Comments
                                </button>
                                <button type="button" class="btn btn-danger" onclick="updateStatus(<?php echo $id; ?>, 'rejected')">
                                    <i class="bi bi-x-circle"></i> Reject with Comments
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scrolling to all cards
            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger for clicks on buttons, links, or form elements
                    if (e.target.closest('a, button, input, textarea, select')) {
                        return;
                    }
                    // Apply a subtle animation
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 200);
                });
            });
            
            // Add tooltip initialization if Bootstrap 5 is used
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
            
            // Ensure sidebar toggle works correctly with the new layout
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            if (sidebarToggle && sidebar) {
                // Initially check screen size and adjust main content
                adjustMainContentForSidebar();
                
                // Listen for window resize events
                window.addEventListener('resize', adjustMainContentForSidebar);
                
                function adjustMainContentForSidebar() {
                    const mainContent = document.querySelector('.main-content');
                    if (window.innerWidth >= 768) {
                        if (mainContent) {
                            mainContent.style.marginLeft = 'auto';
                            mainContent.style.width = 'calc(100% - var(--sidebar-width))';
                        }
                    } else {
                        if (mainContent) {
                            mainContent.style.marginLeft = '0';
                            mainContent.style.width = '100%';
                        }
                    }
                }
            }
        });
        
        function updateStatus(id, status) {
            const comments = document.getElementById('comments')?.value || '';
            
            if (confirm('Are you sure you want to ' + status + ' this application?')) {
                // Show loading state
                const statusBtn = event.target.closest('button');
                const originalContent = statusBtn.innerHTML;
                statusBtn.disabled = true;
                statusBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
                
                // Call API to update status
                fetch('update_application_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id + '&status=' + status + '&comments=' + encodeURIComponent(comments)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success notification
                        showNotification('Application ' + status + ' successfully!', 'success');
                        
                        // Reload after a short delay to show the notification
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Reset button
                        statusBtn.disabled = false;
                        statusBtn.innerHTML = originalContent;
                        
                        // Show error
                        showNotification(data.message || 'An error occurred while updating the status.', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    statusBtn.disabled = false;
                    statusBtn.innerHTML = originalContent;
                    showNotification('An error occurred while processing your request.', 'danger');
                });
            }
        }
        
        // Function to show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} notification-toast`;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Style for the notification
            Object.assign(notification.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: '9999',
                minWidth: '300px',
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                borderRadius: '4px',
                opacity: '0',
                transform: 'translateY(-20px)',
                transition: 'opacity 0.3s, transform 0.3s'
            });
            
            // Add to body
            document.body.appendChild(notification);
            
            // Trigger animation
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateY(0)';
            }, 10);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                
                // Remove from DOM after animation
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 5000);
        }
    </script>
</body>
</html> 