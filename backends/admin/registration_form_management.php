<?php
// Check authentication
$auth = new Auth();
$auth->requireRole('admin');

// Get current user
$user = $auth->getCurrentUser();

// Get registration type from URL parameter
$registrationType = isset($_GET['type']) ? $_GET['type'] : 'kiddies';
if (!in_array($registrationType, ['kiddies', 'college'])) {
    $registrationType = 'kiddies';
}

// Handle form field updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_fields'])) {
        // Update existing fields
        foreach ($_POST['fields'] as $field_id => $field_data) {
            $sql = "UPDATE registration_form_fields SET 
                    field_label = ?,
                    field_type = ?,
                    field_order = ?,
                    required = ?,
                    options = ?
                    WHERE id = ? AND registration_type = ?";
            
            $stmt = $conn->prepare($sql);
            $required = isset($field_data['required']) ? 1 : 0;
            $stmt->bind_param("ssissis", 
                $field_data['label'],
                $field_data['type'],
                $field_data['order'],
                $required,
                $field_data['options'],
                $field_id,
                $registrationType
            );
            $stmt->execute();
        }
        $_SESSION['success_message'] = "Form fields updated successfully!";
    }
    
    if (isset($_POST['add_field'])) {
        // Add new field
        $sql = "INSERT INTO registration_form_fields (field_label, field_type, field_order, required, options, registration_type) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $required = isset($_POST['required']) ? 1 : 0;
        $stmt->bind_param("ssisss", 
            $_POST['field_label'],
            $_POST['field_type'],
            $_POST['field_order'],
            $required,
            $_POST['options'],
            $registrationType
        );
        $stmt->execute();
        $_SESSION['success_message'] = "New field added successfully!";
    }
    
    if (isset($_POST['delete_field']) && isset($_POST['field_id'])) {
        // Delete field
        $field_id = intval($_POST['field_id']);
        
        // First check if the field exists and belongs to the current registration type
        $check_sql = "SELECT id FROM registration_form_fields WHERE id = ? AND registration_type = ? AND is_active = 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $field_id, $registrationType);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Field exists and belongs to current registration type, proceed with deletion
            $sql = "UPDATE registration_form_fields SET is_active = 0 WHERE id = ? AND registration_type = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $field_id, $registrationType);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Field removed successfully!";
            } else {
                $_SESSION['error_message'] = "Error removing field. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid field or field does not belong to this registration type.";
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: registration_form_filed_update.php?type=" . $registrationType);
    exit();
}

// Get all active fields for the current registration type
$sql = "SELECT * FROM registration_form_fields WHERE is_active = 1 AND registration_type = ? ORDER BY field_order";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $registrationType);
$stmt->execute();
$result = $stmt->get_result();
$fields = $result->fetch_all(MYSQLI_ASSOC);

// Include the form field management interface HTML
require_once __DIR__ . '/views/registration_form_management.php'; 