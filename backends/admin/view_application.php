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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Application Details</h2>
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
                        <h5 class="card-title mb-0">Application Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Application ID:</strong>
                                <p>#<?php echo $application['id']; ?></p>
                            </div>
                            <div class="col-md-3">
                                <strong>Type:</strong>
                                <p><?php echo ucfirst($application['application_type']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <strong>Status:</strong>
                                <p>
                                    <span class="badge bg-<?php echo $application['status'] === 'pending' ? 'warning' : ($application['status'] === 'approved' ? 'success' : 'danger'); ?>">
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <strong>Submission Date:</strong>
                                <p><?php echo date('Y-m-d H:i', strtotime($application['submission_date'])); ?></p>
                            </div>
                        </div>
                        <?php if ($application['reviewed_by']): ?>
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <strong>Reviewed By:</strong>
                                <p><?php echo htmlspecialchars($reviewer_name); ?></p>
                            </div>
                            <div class="col-md-3">
                                <strong>Review Date:</strong>
                                <p><?php echo date('Y-m-d H:i', strtotime($application['review_date'])); ?></p>
                            </div>
                            <?php if ($application['comments']): ?>
                            <div class="col-md-6">
                                <strong>Comments:</strong>
                                <p><?php echo nl2br(htmlspecialchars($application['comments'])); ?></p>
                            </div>
                            <?php endif; ?>
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
                            <?php foreach ($form_fields as $field): ?>
                            <div class="col-md-6 mb-3">
                                <strong><?php echo htmlspecialchars($field['field_label']); ?>:</strong>
                                <?php 
                                $field_value = $applicant_data['field_' . $field['id']] ?? '';
                                if ($field['field_type'] === 'file' && $field_value): ?>
                                    <p><a href="<?php echo htmlspecialchars($field_value); ?>" target="_blank">View File</a></p>
                                <?php else: ?>
                                    <p><?php echo nl2br(htmlspecialchars($field_value)); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
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