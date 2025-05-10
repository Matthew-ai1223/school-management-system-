<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

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

// Get form fields
$fields_query = "SELECT * FROM form_fields WHERE application_type = ? AND is_active = 1 ORDER BY field_order";
$stmt = $mysqli->prepare($fields_query);
$stmt->bind_param("s", $application['application_type']);
$stmt->execute();
$fields_result = $stmt->get_result();
$form_fields = [];
while ($field = $fields_result->fetch_assoc()) {
    $form_fields[] = $field;
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

$applicant_data = json_decode($application['applicant_data'], true);

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
        .field-label {
            font-weight: 600;
            color: #495057;
        }
        .field-value {
            margin-top: 0.25rem;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <?php echo ucfirst($application['application_type']); ?> Application Details
                        <span class="badge bg-<?php 
                            echo $application['status'] === 'pending' ? 'warning' : 
                                ($application['status'] === 'approved' ? 'success' : 'danger'); 
                            ?> status-badge">
                            <?php echo ucfirst($application['status']); ?>
                        </span>
                    </h2>
                    <div>
                        <a href="applications.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Applications
                        </a>
                        <?php if ($application['status'] === 'pending'): ?>
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
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Application Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($form_fields as $field): 
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
                </div>

                <?php if ($application['status'] === 'pending'): ?>
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
        function updateStatus(id, status) {
            const comments = document.getElementById('comments')?.value || '';
            if (confirm('Are you sure you want to ' + status + ' this application?')) {
                fetch('update_application_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id + '&status=' + status + '&comments=' + encodeURIComponent(comments)
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