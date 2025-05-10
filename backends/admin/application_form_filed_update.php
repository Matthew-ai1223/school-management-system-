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
        .field-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .options-field {
            display: none;
        }
        .type-select:has(option[value="select"]:checked) + .options-field {
            display: block;
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
                    <h2>Manage <?php echo ucfirst($applicationType); ?> Application Form Fields</h2>
                    <div class="btn-group">
                        <a href="?type=kiddies" class="btn btn-<?php echo $applicationType === 'kiddies' ? 'primary' : 'outline-primary'; ?>">Kiddies Form</a>
                        <a href="?type=college" class="btn btn-<?php echo $applicationType === 'college' ? 'primary' : 'outline-primary'; ?>">College Form</a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
                        <h5 class="card-title mb-0">Add New Field</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Field Label</label>
                                <input type="text" name="field_label" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Field Type</label>
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
                            <div class="col-md-2">
                                <label class="form-label">Order</label>
                                <input type="number" name="field_order" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Required</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="required" class="form-check-input" id="newFieldRequired">
                                    <label class="form-check-label" for="newFieldRequired">Make Required</label>
                                </div>
                            </div>
                            <div class="col-md-12 options-field">
                                <label class="form-label">Options (comma-separated)</label>
                                <input type="text" name="options" class="form-control" placeholder="Option1,Option2,Option3">
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_field" class="btn btn-primary">Add Field</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Existing Fields -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Current Form Fields</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php foreach ($fields as $field): ?>
                                <div class="field-card">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Field Label</label>
                                            <input type="text" name="fields[<?php echo $field['id']; ?>][label]" 
                                                   class="form-control" value="<?php echo htmlspecialchars($field['field_label']); ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Field Type</label>
                                            <select name="fields[<?php echo $field['id']; ?>][type]" class="form-select type-select" required>
                                                <?php
                                                $types = ['text', 'email', 'number', 'date', 'textarea', 'select', 'file'];
                                                foreach ($types as $type):
                                                    $selected = $field['field_type'] === $type ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $type; ?>" <?php echo $selected; ?>><?php echo ucfirst($type); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Order</label>
                                            <input type="number" name="fields[<?php echo $field['id']; ?>][order]" 
                                                   class="form-control" value="<?php echo $field['field_order']; ?>" min="0">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Required</label>
                                            <div class="form-check mt-2">
                                                <input type="checkbox" name="fields[<?php echo $field['id']; ?>][required]" 
                                                       class="form-check-input" <?php echo $field['required'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Make Required</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Actions</label>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                                <button type="submit" name="delete_field" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to remove this field?')">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-12 options-field" style="display: <?php echo $field['field_type'] === 'select' ? 'block' : 'none'; ?>;">
                                            <label class="form-label">Options (comma-separated)</label>
                                            <input type="text" name="fields[<?php echo $field['id']; ?>][options]" 
                                                   class="form-control" value="<?php echo htmlspecialchars($field['options']); ?>" 
                                                   placeholder="Option1,Option2,Option3">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($fields) > 0): ?>
                                <div class="text-end mt-3">
                                    <button type="submit" name="update_fields" class="btn btn-primary">Save Changes</button>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No fields have been added yet.</p>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide options field based on field type selection
        document.querySelectorAll('.type-select').forEach(select => {
            select.addEventListener('change', function() {
                const optionsField = this.closest('.row').querySelector('.options-field');
                if (optionsField) {
                    optionsField.style.display = this.value === 'select' ? 'block' : 'none';
                }
            });
        });
    </script>
</body>
</html>
