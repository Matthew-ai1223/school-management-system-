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

// Handle application submission
if (isset($_POST['submit_application'])) {
    $fields = getFormFields($conn, $applicationType);
    $application_data = [
        'application_type' => $applicationType
    ];
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../../../uploads/' . $applicationType . '/' . date('Y/m/d');
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $has_error = false;
    $error_message = '';
    
    foreach ($fields as $field) {
        $field_name = "field_" . $field['id'];
        
        if ($field['field_type'] === 'file' && isset($_FILES[$field_name])) {
            $file = $_FILES[$field_name];
            
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                if ($field['required']) {
                    $has_error = true;
                    $error_message = "Please upload file for " . $field['field_label'];
                    break;
                }
                continue;
            }
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $has_error = true;
                $error_message = "Error uploading file for " . $field['field_label'];
                break;
            }
            
            // Generate unique filename
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . '/' . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Store relative path in database
                $relative_path = str_replace('../../../', '', $upload_path);
                $application_data[$field_name] = $relative_path;
            } else {
                $has_error = true;
                $error_message = "Failed to save uploaded file for " . $field['field_label'];
                break;
            }
        } else if (isset($_POST[$field_name])) {
            $application_data[$field_name] = $_POST[$field_name];
        } else if ($field['required']) {
            $has_error = true;
            $error_message = "Please fill in " . $field['field_label'];
            break;
        }
    }
    
    if (!$has_error) {
        // Save application data to database
        $sql = "INSERT INTO applications (applicant_data, application_type, submission_date) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $json_data = json_encode($application_data);
        $stmt->bind_param("ss", $json_data, $applicationType);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Application submitted successfully!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?type=" . $applicationType);
            exit();
        } else {
            $error_message = "Failed to submit application. Please try again.";
        }
    }
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
        .file-preview {
            margin-top: 10px;
        }
        .file-preview img {
            max-width: 200px;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
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

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="application-type-switch">
            <div class="btn-group">
                <a href="?type=kiddies" class="btn btn-<?php echo $applicationType === 'kiddies' ? 'primary' : 'outline-primary'; ?>">Kiddies Application</a>
                <a href="?type=college" class="btn btn-<?php echo $applicationType === 'college' ? 'primary' : 'outline-primary'; ?>">College Application</a>
            </div>
        </div>

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
                    <input type="file" class="form-control" id="<?php echo $field_id; ?>" name="<?php echo $field_id; ?>" <?php echo $required; ?> 
                           onchange="previewFile(this)">
                    <div id="<?php echo $field_id; ?>_preview" class="file-preview"></div>
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
        function previewFile(input) {
            const preview = document.getElementById(input.id + '_preview');
            const file = input.files[0];
            
            if (!file) {
                preview.innerHTML = '';
                return;
            }
            
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="File preview">`;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = `
                    <div class="mt-2">
                        <i class="bi bi-file-earmark"></i>
                        ${file.name} (${formatFileSize(file.size)})
                    </div>
                `;
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html> 