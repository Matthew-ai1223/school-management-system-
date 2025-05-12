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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $query = "SELECT registration_number FROM students WHERE registration_number LIKE ? ORDER BY registration_number DESC LIMIT 1";
        $pattern = $year . $type_prefix . '%';
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
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

        // Initialize student data with required fields
        $student_data = [
            'registration_number' => $registration_number,
            'application_type' => $registrationType,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'file_id' => null // Initialize file_id as null
        ];

        // Process each field
        foreach ($fields as $field) {
            $field_id = $field['id'];
            $field_name = "field_" . $field_id;
            $column_name = strtolower(str_replace(' ', '_', $field['field_label']));
            
            if ($field['field_type'] === 'file' && isset($_FILES[$field_name])) {
                // Handle file upload
                $file = $_FILES[$field_name];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $registration_number . '_' . $column_name . '.' . $ext;
                    $uploads_dir = '../../uploads/student_files/';
                    
                    if (!file_exists($uploads_dir)) {
                        mkdir($uploads_dir, 0777, true);
                    }
                    
                    $filepath = $uploads_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Store file information in the files table
                        if (strtolower($field['field_label']) === 'profile picture' || 
                            strtolower($field['field_label']) === 'photo' || 
                            strtolower($field['field_label']) === 'image') {
                            
                            $file_sql = "INSERT INTO files (file_name, file_path, file_type, uploaded_by, entity_type, entity_id, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, NOW())";
                            $file_stmt = $conn->prepare($file_sql);
                            $file_type = $ext;
                            $entity_type = 'student';
                            $uploaded_by = 0; // System upload
                            $entity_id = 0; // Will be updated after student insertion
                            
                            $file_stmt->bind_param("sssssi", 
                                $filename, 
                                $filepath, 
                                $file_type,
                                $uploaded_by,
                                $entity_type,
                                $entity_id
                            );
                            
                            if (!$file_stmt->execute()) {
                                throw new Exception("Error saving file information: " . $file_stmt->error);
                            }
                            
                            // Store the file ID
                            $student_data['file_id'] = $conn->insert_id;
                        }
                    } else {
                        throw new Exception("Error uploading file for " . $field['field_label']);
                    }
                } elseif ($field['required']) {
                    throw new Exception("Required file missing for " . $field['field_label']);
                }
            } else {
                // Handle other field types
                $value = $_POST[$field_name] ?? '';
                if ($field['required'] && empty($value)) {
                    throw new Exception("Required field missing: " . $field['field_label']);
                }

                // Map common field names to database columns
                switch (strtolower($field['field_label'])) {
                    case 'first name':
                        $student_data['first_name'] = $value;
                        break;
                    case 'last name':
                        $student_data['last_name'] = $value;
                        break;
                    case 'date of birth':
                        $student_data['date_of_birth'] = $value;
                        break;
                    case 'gender':
                        $student_data['gender'] = $value;
                        break;
                    case 'phone':
                    case 'phone number':
                        $student_data['phone'] = $value;
                        break;
                    case 'address':
                        $student_data['address'] = $value;
                        break;
                    case 'parent name':
                        $student_data['parent_name'] = $value;
                        break;
                    case 'parent phone':
                    case 'parent phone number':
                        $student_data['parent_phone'] = $value;
                        break;
                    case 'parent email':
                        $student_data['parent_email'] = $value;
                        break;
                    case 'parent address':
                        $student_data['parent_address'] = $value;
                        break;
                    case 'previous school':
                        $student_data['previous_school'] = $value;
                        break;
                    default:
                        // Skip fields that don't map to database columns
                        continue 2;
                }
            }
        }

        // Start transaction
        $conn->begin_transaction();

        try {
            // Build the INSERT query for student
            $columns = implode(', ', array_map(function($key) {
                return '`' . $key . '`';
            }, array_keys($student_data)));
            
            $placeholders = implode(', ', array_fill(0, count($student_data), '?'));
            $sql = "INSERT INTO students ($columns) VALUES ($placeholders)";
            
            // Debug log
            error_log("SQL Query: " . $sql);
            error_log("Data: " . print_r($student_data, true));
            
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }

            $types = str_repeat('s', count($student_data));
            $stmt->bind_param($types, ...array_values($student_data));
            
            if ($stmt->execute()) {
                $student_id = $conn->insert_id;

                // Update file record with student ID if there was a file upload
                if (isset($student_data['file_id'])) {
                    $update_file_sql = "UPDATE files SET entity_id = ? WHERE id = ?";
                    $update_file_stmt = $conn->prepare($update_file_sql);
                    $update_file_stmt->bind_param("ii", $student_id, $student_data['file_id']);
                    
                    if (!$update_file_stmt->execute()) {
                        throw new Exception("Error updating file record: " . $update_file_stmt->error);
                    }
                }

                // Commit transaction
                $conn->commit();

                $_SESSION['success_message'] = "Registration successful! Your registration number is: " . $registration_number;
                header("Location: success.php?reg=" . urlencode($registration_number));
                exit;
            } else {
                throw new Exception("Error saving registration: " . $stmt->error);
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        error_log("Registration Error: " . $e->getMessage());
        header("Location: reg_form.php?type=" . urlencode($registrationType));
        exit;
    }
} else {
    // If not POST request, redirect to registration form
    header("Location: reg_form.php");
    exit;
} 