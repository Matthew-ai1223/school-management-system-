<?php
require_once '../../config.php';
require_once '../../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get registration type from URL parameter (default to kiddies if not specified)
$registrationType = isset($_GET['type']) ? $_GET['type'] : 'kiddies';
if (!in_array($registrationType, ['kiddies', 'college'])) {
    $registrationType = 'kiddies';
}

// Fetch form fields for the current registration type
$sql = "SELECT * FROM registration_form_fields WHERE is_active = 1 AND registration_type = ? ORDER BY field_order";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $registrationType);
$stmt->execute();
$result = $stmt->get_result();
$fields = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Student Registration Form</h3>
                        <div class="text-center mt-2">
                            <div class="btn-group">
                                <a href="?type=kiddies" class="btn btn-<?php echo $registrationType === 'kiddies' ? 'primary' : 'outline-primary'; ?>">
                                    Ace Kiddies
                                </a>
                                <a href="?type=college" class="btn btn-<?php echo $registrationType === 'college' ? 'primary' : 'outline-primary'; ?>">
                                    Ace College
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php 
                                echo $_SESSION['error_message'];
                                unset($_SESSION['error_message']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="save_registration.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="registration_type" value="<?php echo $registrationType; ?>">
                            
                            <?php if (empty($fields)): ?>
                                <div class="alert alert-warning">
                                    No form fields have been configured for this registration type.
                                </div>
                            <?php else: ?>
                                <?php foreach ($fields as $field): ?>
                                    <div class="mb-3">
                                        <label class="form-label <?php echo $field['required'] ? 'required-field' : ''; ?>">
                                            <?php echo htmlspecialchars($field['field_label']); ?>
                                        </label>
                                        
                                        <?php switch($field['field_type']): 
                                            case 'text': 
                                            case 'email':
                                            case 'number':
                                            case 'date': ?>
                                                <input type="<?php echo $field['field_type']; ?>" 
                                                       name="field_<?php echo $field['id']; ?>" 
                                                       class="form-control"
                                                       <?php echo $field['required'] ? 'required' : ''; ?>>
                                                <?php break; ?>
                                            
                                            <?php case 'textarea': ?>
                                                <textarea name="field_<?php echo $field['id']; ?>" 
                                                          class="form-control" 
                                                          rows="3"
                                                          <?php echo $field['required'] ? 'required' : ''; ?>></textarea>
                                                <?php break; ?>
                                            
                                            <?php case 'select': ?>
                                                <select name="field_<?php echo $field['id']; ?>" 
                                                        class="form-select"
                                                        <?php echo $field['required'] ? 'required' : ''; ?>>
                                                    <option value="">Select <?php echo $field['field_label']; ?></option>
                                                    <?php 
                                                    $options = explode(',', $field['options']);
                                                    foreach ($options as $option):
                                                        $option = trim($option);
                                                        if (!empty($option)):
                                                    ?>
                                                        <option value="<?php echo htmlspecialchars($option); ?>">
                                                            <?php echo htmlspecialchars($option); ?>
                                                        </option>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </select>
                                                <?php break; ?>
                                            
                                            <?php case 'file': ?>
                                                <input type="file" 
                                                       name="field_<?php echo $field['id']; ?>" 
                                                       class="form-control"
                                                       accept="image/*"
                                                       <?php echo $field['required'] ? 'required' : ''; ?>>
                                                <?php break; ?>
                                        <?php endswitch; ?>
                                        
                                    </div>
                                <?php endforeach; ?>

                                <div class="text-center mt-4">
                                    <button type="submit" name="submit_registration" class="btn btn-primary">
                                        Submit Registration
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 