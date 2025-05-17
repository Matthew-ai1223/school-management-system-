<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Define field categories
$fieldCategories = [
    'student_info' => 'Student Information',
    'parent_info' => 'Parent/Guardian Information',
    'guardian_info' => 'Guardian Info (Optional)',
    'medical_info' => 'Medical Background (Optional)'
];

// Handle form submission
if (isset($_POST['submit_registration'])) {
    try {
        // Get registration type
        $registrationType = $_POST['registration_type'] ?? 'kiddies';
        if (!in_array($registrationType, ['kiddies', 'college'])) {
            throw new Exception("Invalid registration type");
        }

        // Generate registration number
        $year = date('Y');
        $type_prefix = ($registrationType === 'kiddies') ? 'KID' : 'COL';
        
        // Get the last registration number for this year and type
        $query = "SELECT registration_number FROM students WHERE registration_number LIKE '{$year}{$type_prefix}%' ORDER BY registration_number DESC LIMIT 1";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $last_reg = $result->fetch_assoc()['registration_number'];
            $last_number = intval(substr($last_reg, -4));
            $new_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $new_number = '0001';
        }
        
        $registration_number = $year . $type_prefix . $new_number;

        // Get all active fields for this registration type
        $sql = "SELECT * FROM registration_form_fields WHERE is_active = 1 AND registration_type = ? ORDER BY field_order";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $registrationType);
        $stmt->execute();
        $fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Prepare data for insertion
        $field_data = [];
        $uploads_dir = '../uploads/student_files/';
        if (!file_exists($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);
        }

        // Process each field
        foreach ($fields as $field) {
            $field_id = $field['id'];
            $field_name = "field_" . $field_id;
            
            if ($field['field_type'] === 'file' && isset($_FILES[$field_name])) {
                // Handle file upload
                $file = $_FILES[$field_name];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $registration_number . '_' . $field_id . '.' . $ext;
                    $filepath = $uploads_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $field_data[$field['field_label']] = $filename;
                    } else {
                        throw new Exception("Error uploading file for " . $field['field_label']);
                    }
                } elseif ($field['required']) {
                    throw new Exception("Required file missing for " . $field['field_label']);
                }
            } elseif ($field['field_type'] === 'checkbox' && isset($_POST[$field_name])) {
                // Handle checkbox fields (multiple values as comma-separated)
                if (is_array($_POST[$field_name])) {
                    $field_data[$field['field_label']] = implode(', ', $_POST[$field_name]);
                } else {
                    $field_data[$field['field_label']] = $_POST[$field_name];
                }
            } else {
                // Handle other field types
                $value = $_POST[$field_name] ?? '';
                if ($field['required'] && empty($value)) {
                    throw new Exception("Required field missing: " . $field['field_label']);
                }
                $field_data[$field['field_label']] = $value;
            }
        }

        // Insert into students table
        $field_data['registration_number'] = $registration_number;
        $field_data['registration_type'] = $registrationType;
        $field_data['status'] = 'pending';
        $field_data['created_at'] = date('Y-m-d H:i:s');

        // Build the INSERT query
        $columns = implode(', ', array_map(function($key) {
            return '`' . str_replace(' ', '_', strtolower($key)) . '`';
        }, array_keys($field_data)));
        
        $values = implode(', ', array_fill(0, count($field_data), '?'));
        $sql = "INSERT INTO students ($columns) VALUES ($values)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }

        $types = str_repeat('s', count($field_data));
        $stmt->bind_param($types, ...array_values($field_data));
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Registration successful! Your registration number is: " . $registration_number;
            header("Location: ../../student/registration/success.php?reg=" . $registration_number);
            exit;
        } else {
            throw new Exception("Error saving registration: " . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: ../../student/registration/reg_form.php?type=" . $registrationType);
        exit;
    }
}

// If not a form submission, include the form field management interface
require_once 'registration_form_management.php';
