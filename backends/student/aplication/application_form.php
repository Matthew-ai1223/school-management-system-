<?php
require_once '../../../backends/config.php';
require_once '../../../backends/database.php';
require_once '../../../backends/auth.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize authentication
$auth = new Auth();

// Get application type from URL parameter
$applicationType = isset($_GET['type']) ? $_GET['type'] : 'kiddies';
if (!in_array($applicationType, ['kiddies', 'college'])) {
    $applicationType = 'kiddies';
}

// Function to get all form fields from database
function getFormFields($conn, $applicationType) {
    $sql = "SELECT * FROM form_fields WHERE is_active = 1 AND application_type = ? ORDER BY field_order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $applicationType);
    $stmt->execute();
    $result = $stmt->get_result();
    $fields = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $fields[] = $row;
        }
    }
    return $fields;
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Handle form field addition (Admin only)
if (isset($_POST['add_field']) && isAdmin()) {
    $field_label = $_POST['field_label'];
    $field_type = $_POST['field_type'];
    $required = isset($_POST['required']) ? 1 : 0;
    $field_order = isset($_POST['field_order']) ? $_POST['field_order'] : 0;
    $options = isset($_POST['field_options']) ? $_POST['field_options'] : '';
    
    $sql = "INSERT INTO form_fields (field_label, field_type, required, field_order, options, application_type) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiiss", $field_label, $field_type, $required, $field_order, $options, $applicationType);
    $stmt->execute();
}

// Handle form field deletion (Admin only)
if (isset($_POST['delete_field']) && isAdmin()) {
    $field_id = $_POST['field_id'];
    $sql = "UPDATE form_fields SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $field_id);
    $stmt->execute();
}

// Handle application submission
if (isset($_POST['submit_application'])) {
    $fields = getFormFields($conn, $applicationType);
    $application_data = [
        'application_type' => $applicationType
    ];
    
    foreach ($fields as $field) {
        $field_name = "field_" . $field['id'];
        if (isset($_POST[$field_name])) {
            $application_data[$field_name] = $_POST[$field_name];
        }
    }
    
    // Save application data to database
    $sql = "INSERT INTO applications (applicant_data, application_type, submission_date) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $json_data = json_encode($application_data);
    $stmt->bind_param("ss", $json_data, $applicationType);
    $stmt->execute();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACE <?php echo ucfirst($applicationType); ?> - Application Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .admin-controls {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        .form-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .school-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .school-logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
        .application-type-switch {
            text-align: center;
            margin-bottom: 20px;
        }
        .application-type-switch .btn-group {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container form-container">
        <div class="school-header">
            <img src="../../../assets/images/logo.png" alt="ACE Kiddies and College Logo" class="school-logo">
            <h1>ACE MODEL COLLEGE</h1>
            <h2><?php echo ucfirst($applicationType); ?> Application Form</h2>
        </div>

        <div class="application-type-switch">
            <div class="btn-group">
                <a href="?type=kiddies" class="btn btn-<?php echo $applicationType === 'kiddies' ? 'primary' : 'outline-primary'; ?>">Kiddies Application</a>
                <a href="?type=college" class="btn btn-<?php echo $applicationType === 'college' ? 'primary' : 'outline-primary'; ?>">College Application</a>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <div class="admin-controls">
            <h3>Admin Controls - <?php echo ucfirst($applicationType); ?> Form Fields</h3>
            <form method="POST" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="field_label" class="form-control" placeholder="Field Label" required>
                    </div>
                    <div class="col-md-2">
                        <select name="field_type" class="form-select" required>
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
                        <input type="number" name="field_order" class="form-control" placeholder="Order" value="0">
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="field_options" class="form-control" placeholder="Options (comma-separated)">
                    </div>
                    <div class="col-md-1">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="required" class="form-check-input" id="fieldRequired">
                            <label class="form-check-label" for="fieldRequired">Required</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="add_field" class="btn btn-primary">Add Field</button>
                    </div>
                </div>
            </form>

            <div class="current-fields">
                <h4>Current Form Fields</h4>
                <?php
                $fields = getFormFields($conn, $applicationType);
                foreach ($fields as $field):
                ?>
                <div class="row g-2 mb-2 align-items-center">
                    <div class="col">
                        <span class="badge bg-secondary"><?php echo $field['field_order']; ?></span>
                        <strong><?php echo htmlspecialchars($field['field_label']); ?></strong>
                        <small class="text-muted">(<?php echo $field['field_type']; ?>)</small>
                        <?php if ($field['required']): ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-auto">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                            <button type="submit" name="delete_field" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="application-form" enctype="multipart/form-data">
            <?php
            $fields = getFormFields($conn, $applicationType);
            foreach ($fields as $field):
                $field_id = "field_" . $field['id'];
                $required = $field['required'] ? 'required' : '';
            ?>
            <div class="mb-3">
                <label for="<?php echo $field_id; ?>" class="form-label">
                    <?php echo htmlspecialchars($field['field_label']); ?>
                    <?php if ($field['required']): ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                
                <?php if ($field['field_type'] === 'textarea'): ?>
                    <textarea class="form-control" id="<?php echo $field_id; ?>" name="<?php echo $field_id; ?>" <?php echo $required; ?>></textarea>
                <?php elseif ($field['field_type'] === 'select'): ?>
                    <select class="form-select" id="<?php echo $field_id; ?>" name="<?php echo $field_id; ?>" <?php echo $required; ?>>
                        <option value="">Select an option</option>
                        <?php
                        $options = explode(',', $field['options']);
                        foreach ($options as $option):
                            $option = trim($option);
                            if (!empty($option)):
                        ?>
                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </select>
                <?php elseif ($field['field_type'] === 'file'): ?>
                    <input type="file" class="form-control" id="<?php echo $field_id; ?>" name="<?php echo $field_id; ?>" <?php echo $required; ?>>
                <?php else: ?>
                    <input type="<?php echo $field['field_type']; ?>" 
                           class="form-control" 
                           id="<?php echo $field_id; ?>" 
                           name="<?php echo $field_id; ?>"
                           <?php echo $required; ?>>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="text-center mt-4">
                <button type="submit" name="submit_application" class="btn btn-primary btn-lg">Submit Application</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide options field based on field type selection
        document.querySelector('select[name="field_type"]').addEventListener('change', function() {
            const optionsField = document.querySelector('input[name="field_options"]');
            optionsField.style.display = this.value === 'select' ? 'block' : 'none';
        });
    </script>
</body>
</html> 