<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Initialize authentication and database
$auth = new Auth();
$auth->requireRole('admin');
$db = Database::getInstance();
$conn = $db->getConnection();

// Get current user
$user = $auth->getCurrentUser();

// Get application type from URL parameter
$applicationType = isset($_GET['type']) ? $_GET['type'] : 'kiddies';
if (!in_array($applicationType, ['kiddies', 'college'])) {
    $applicationType = 'kiddies';
}

// Handle form field updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_fields'])) {
        // Update existing fields
        foreach ($_POST['fields'] as $field_id => $field_data) {
            $sql = "UPDATE form_fields SET 
                    field_label = ?,
                    field_type = ?,
                    field_order = ?,
                    required = ?,
                    options = ?
                    WHERE id = ? AND application_type = ?";
            
            $stmt = $conn->prepare($sql);
            $required = isset($field_data['required']) ? 1 : 0;
            $stmt->bind_param("ssissis", 
                $field_data['label'],
                $field_data['type'],
                $field_data['order'],
                $required,
                $field_data['options'],
                $field_id,
                $applicationType
            );
            $stmt->execute();
        }
        $_SESSION['success_message'] = "Form fields updated successfully!";
    }
    
    if (isset($_POST['add_field'])) {
        // Add new field
        $sql = "INSERT INTO form_fields (field_label, field_type, field_order, required, options, application_type) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $required = isset($_POST['required']) ? 1 : 0;
        $stmt->bind_param("ssisss", 
            $_POST['field_label'],
            $_POST['field_type'],
            $_POST['field_order'],
            $required,
            $_POST['options'],
            $applicationType
        );
        $stmt->execute();
        $_SESSION['success_message'] = "New field added successfully!";
    }
    
    if (isset($_POST['delete_field']) && isset($_POST['field_id'])) {
        // Delete field
        $field_id = intval($_POST['field_id']);
        
        // First check if the field exists and belongs to the current application type
        $check_sql = "SELECT id FROM form_fields WHERE id = ? AND application_type = ? AND is_active = 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $field_id, $applicationType);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Field exists and belongs to current application type, proceed with deletion
            $sql = "UPDATE form_fields SET is_active = 0 WHERE id = ? AND application_type = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $field_id, $applicationType);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Field removed successfully!";
            } else {
                $_SESSION['error_message'] = "Error removing field. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid field or field does not belong to this application type.";
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: application_form_filed_update.php?type=" . $applicationType);
    exit();
}

// Get all active fields for the current application type
$sql = "SELECT * FROM form_fields WHERE is_active = 1 AND application_type = ? ORDER BY field_order";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $applicationType);
$stmt->execute();
$result = $stmt->get_result();
$fields = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Application Form Fields - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Main Layout */
        body {
            background-color: #f8f9fa;
        }

        .main-content {
            min-height: 100vh;
            padding: 2rem;
            transition: all 0.3s;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            background: white;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(145deg, #f8f9fa, #ffffff);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 15px 15px 0 0 !important;
            padding: 1.25rem;
        }

        .card-header h5 {
            color: #2c3338;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form Field Card */
        .field-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .field-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 0.625rem 1rem;
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(145deg, #0d6efd, #0b5ed7);
            border: none;
            box-shadow: 0 2px 6px rgba(13, 110, 253, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
        }

        .btn-danger {
            background: linear-gradient(145deg, #dc3545, #bb2d3b);
            border: none;
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.2);
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }

        .btn-group .btn {
            border-radius: 8px;
            margin: 0 2px;
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            background: linear-gradient(145deg, #d1e7dd, #badbcc);
            color: #0f5132;
        }

        .alert-danger {
            background: linear-gradient(145deg, #f8d7da, #f5c2c7);
            color: #842029;
        }

        /* Checkboxes */
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            margin-top: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .form-check-label {
            padding-left: 0.5rem;
            user-select: none;
        }

        /* Options Field */
        .options-field {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .field-card {
                padding: 1rem;
            }

            .btn-group {
                width: 100%;
                margin-bottom: 1rem;
            }

            .btn-group .btn {
                flex: 1;
            }
        }

        /* Loading States */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Drag Handle */
        .drag-handle {
            cursor: move;
            padding: 0.5rem;
            color: #6c757d;
            transition: color 0.2s;
        }

        .drag-handle:hover {
            color: #0d6efd;
        }

        /* Field Order Number */
        .field-number {
            position: absolute;
            top: -10px;
            left: -10px;
            width: 24px;
            height: 24px;
            background: #0d6efd;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
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
                    <h2 class="h3 fw-bold text-dark mb-0">
                        <i class="bi bi-list-check me-2"></i>
                        Manage <?php echo ucfirst($applicationType); ?> Application Form Fields
                    </h2>
                    <div class="btn-group">
                        <a href="?type=kiddies" class="btn btn-<?php echo $applicationType === 'kiddies' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-person-heart me-1"></i> Kiddies Form
                        </a>
                        <a href="?type=college" class="btn btn-<?php echo $applicationType === 'college' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-mortarboard me-1"></i> College Form
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Add New Field Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-plus-circle me-2"></i>
                            Add New Field
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Field Label</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-tag"></i>
                                    </span>
                                    <input type="text" name="field_label" class="form-control" required 
                                           placeholder="Enter field label">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Field Type</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-input-cursor-text"></i>
                                    </span>
                                    <select name="field_type" class="form-select type-select" required>
                                        <option value="text">Text</option>
                                        <option value="email">Email</option>
                                        <option value="number">Number</option>
                                        <option value="date">Date</option>
                                        <option value="textarea">Text Area</option>
                                        <option value="select">Select</option>
                                        <option value="file">File Upload</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Order</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-sort-numeric-down"></i>
                                    </span>
                                    <input type="number" name="field_order" class="form-control" 
                                           value="<?php echo count($fields); ?>" min="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Required</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="checkbox" name="required" class="form-check-input" 
                                           id="newFieldRequired" role="switch">
                                    <label class="form-check-label" for="newFieldRequired">Make Required</label>
                                </div>
                            </div>
                            <div class="col-md-12 options-field">
                                <label class="form-label">Options</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-list-ul"></i>
                                    </span>
                                    <input type="text" name="options" class="form-control" 
                                           placeholder="Option1,Option2,Option3">
                                    <span class="input-group-text text-muted">Comma separated</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_field" class="btn btn-primary">
                                    <i class="bi bi-plus-lg me-2"></i>
                                    Add Field
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Existing Fields -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title">
                            <i class="bi bi-list-check me-2"></i>
                            Current Form Fields
                        </h5>
                        <?php if (count($fields) > 0): ?>
                            <span class="badge bg-primary rounded-pill">
                                <?php echo count($fields); ?> Fields
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php foreach ($fields as $index => $field): ?>
                                <div class="field-card">
                                    <span class="field-number"><?php echo $index + 1; ?></span>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Field Label</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-tag"></i>
                                                </span>
                                                <input type="text" name="fields[<?php echo $field['id']; ?>][label]" 
                                                       class="form-control" value="<?php echo htmlspecialchars($field['field_label']); ?>" 
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Field Type</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-input-cursor-text"></i>
                                                </span>
                                                <select name="fields[<?php echo $field['id']; ?>][type]" 
                                                        class="form-select type-select" required>
                                                    <?php
                                                    $types = [
                                                        'text' => 'Text',
                                                        'email' => 'Email',
                                                        'number' => 'Number',
                                                        'date' => 'Date',
                                                        'textarea' => 'Text Area',
                                                        'select' => 'Select',
                                                        'file' => 'File Upload'
                                                    ];
                                                    foreach ($types as $value => $label):
                                                        $selected = $field['field_type'] === $value ? 'selected' : '';
                                                    ?>
                                                    <option value="<?php echo $value; ?>" <?php echo $selected; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Order</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-sort-numeric-down"></i>
                                                </span>
                                                <input type="number" name="fields[<?php echo $field['id']; ?>][order]" 
                                                       class="form-control" value="<?php echo $field['field_order']; ?>" 
                                                       min="0">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Required</label>
                                            <div class="form-check form-switch mt-2">
                                                <input type="checkbox" name="fields[<?php echo $field['id']; ?>][required]" 
                                                       class="form-check-input" role="switch"
                                                       <?php echo $field['required'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Make Required</label>
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Actions</label>
                                            <button type="submit" name="delete_field" class="btn btn-danger btn-sm w-100" 
                                                    onclick="return confirm('Are you sure you want to remove this field?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                        </div>
                                        <div class="col-md-12 options-field" 
                                             style="display: <?php echo $field['field_type'] === 'select' ? 'block' : 'none'; ?>;">
                                            <label class="form-label">Options</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-list-ul"></i>
                                                </span>
                                                <input type="text" name="fields[<?php echo $field['id']; ?>][options]" 
                                                       class="form-control" 
                                                       value="<?php echo htmlspecialchars($field['options']); ?>" 
                                                       placeholder="Option1,Option2,Option3">
                                                <span class="input-group-text text-muted">Comma separated</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($fields) > 0): ?>
                                <div class="text-end mt-4">
                                    <button type="submit" name="update_fields" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>
                                        Save Changes
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-clipboard-x display-4 text-muted"></i>
                                    <p class="text-muted mt-3">No fields have been added yet.</p>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show/hide options field based on field type selection
            document.querySelectorAll('.type-select').forEach(select => {
                select.addEventListener('change', function() {
                    const optionsField = this.closest('.row').querySelector('.options-field');
                    if (optionsField) {
                        if (this.value === 'select') {
                            optionsField.style.display = 'block';
                            optionsField.querySelector('input').required = true;
                        } else {
                            optionsField.style.display = 'none';
                            optionsField.querySelector('input').required = false;
                        }
                    }
                });
            });

            // Add loading state to buttons
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const button = this.querySelector('button[type="submit"]');
                    if (button) {
                        button.classList.add('loading');
                        button.disabled = true;
                    }
                });
            });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>
