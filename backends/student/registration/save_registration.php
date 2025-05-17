<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../utils.php';

// Enable error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log initial request information
error_log("save_registration.php started - Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if payment is verified
if (!isset($_SESSION['verified_payment_reference'])) {
    error_log("Payment verification missing - redirecting to reg_form.php");
    $_SESSION['error_message'] = 'Payment verification required before registration';
    header('Location: reg_form.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration'])) {
    error_log("Form submission validated - proceeding with registration");
    try {
        $db = Database::getInstance();
        $mysqli = $db->getConnection();
        
        // Get registration type and payment reference
        $registrationType = $_POST['registration_type'] ?? '';
        $paymentReference = $_SESSION['verified_payment_reference'];
        
        error_log("Registration type: " . $registrationType);
        error_log("Payment reference: " . $paymentReference);
        
        // Validate registration type
        if (!in_array($registrationType, ['kiddies', 'college'])) {
            throw new Exception('Invalid registration type');
        }
        
        // Start transaction
        $mysqli->begin_transaction();
        
        // Check the structure of the students table
        $tableColumns = [];
        $columnsResult = $mysqli->query("SHOW COLUMNS FROM students");
        if ($columnsResult) {
            while ($column = $columnsResult->fetch_assoc()) {
                $tableColumns[] = $column['Field'];
            }
        }
        
        // Generate registration number using the dynamic function
        $registration_number = generateRegistrationNumber($registrationType);
        
        // Get all fields for this registration type
        $sql = "SELECT * FROM registration_form_fields WHERE is_active = 1 AND registration_type = ? ORDER BY field_order";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $registrationType);
        $stmt->execute();
        $fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Prepare data for insertion
        $field_data = [];
        $uploads_dir = '../uploads/student_files/';

        // Ensure uploads directory exists with proper permissions
        if (!file_exists($uploads_dir)) {
            if (!mkdir($uploads_dir, 0777, true)) {
                error_log("Failed to create upload directory: " . $uploads_dir);
                throw new Exception("Failed to create upload directory. Please contact the administrator.");
            }
            chmod($uploads_dir, 0777); // Ensure directory is writable
        }

        // Make sure the parent directories are also writable
        $parent_dir = dirname($uploads_dir);
        if (!is_writable($parent_dir)) {
            error_log("Parent directory is not writable: " . $parent_dir);
            chmod($parent_dir, 0777);
        }

        // Additional check to verify directory is writable
        if (!is_writable($uploads_dir)) {
            error_log("Upload directory is not writable: " . $uploads_dir);
            chmod($uploads_dir, 0777); // Try to make it writable
            
            // Check again after attempting to change permissions
            if (!is_writable($uploads_dir)) {
                // Try an alternative method as last resort
                error_log("Still not writable, trying exec command as last resort");
                if (function_exists('exec')) {
                    @exec('mkdir -p ' . escapeshellarg($uploads_dir));
                    @exec('chmod -R 777 ' . escapeshellarg($uploads_dir));
                    
                    if (is_writable($uploads_dir)) {
                        error_log("Directory made writable using system commands");
                    } else {
                        throw new Exception("Upload directory is not writable. Please contact the administrator.");
                    }
                } else {
                    throw new Exception("Upload directory is not writable. Please contact the administrator.");
                }
            }
        }

        // Log upload directory info for debugging
        error_log("Upload directory path: " . realpath($uploads_dir));
        error_log("Upload directory exists: " . (file_exists($uploads_dir) ? 'Yes' : 'No'));
        error_log("Upload directory is writable: " . (is_writable($uploads_dir) ? 'Yes' : 'No'));
        
        // Add registration info (only if the columns exist)
        if (in_array('registration_number', $tableColumns)) {
            $field_data['registration_number'] = $registration_number;
        }
        
        // Store registration type either as a column or field based on existence
        $reg_type_field_id = null;
        foreach ($fields as $field) {
            if (strtolower($field['field_label']) === 'registration type' || 
                strtolower($field['field_label']) === 'registration_type') {
                $reg_type_field_id = $field['id'];
                break;
            }
        }
        
        // Only add registration_type if the column exists
        if (in_array('registration_type', $tableColumns)) {
            $field_data['registration_type'] = $registrationType;
        }
        
        // Only add payment_reference if the column exists
        if (in_array('payment_reference', $tableColumns)) {
            $field_data['payment_reference'] = $paymentReference;
        }
        
        // Add status and created_at if they exist
        if (in_array('status', $tableColumns)) {
            $field_data['status'] = 'pending';
        }
        
        if (in_array('created_at', $tableColumns)) {
            $field_data['created_at'] = date('Y-m-d H:i:s');
        }
        
        // Process each field
        foreach ($fields as $field) {
            $field_id = $field['id'];
            $field_name = "field_" . $field_id;
            $db_field_name = str_replace(' ', '_', strtolower($field['field_label']));
            
            // Skip if we're handling registration type via a dedicated field
            if ($field_id === $reg_type_field_id) {
                $_POST[$field_name] = $registrationType; // Force the correct value
            }
            
            if ($field['field_type'] === 'file' && isset($_FILES[$field_name])) {
                // Handle file upload
                $file = $_FILES[$field_name];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $registration_number . '_' . $field_id . '.' . $ext;
                    $filepath = $uploads_dir . $filename;
                
                    // Log upload details for debugging
                    error_log("Attempting to upload file: " . $file['name']);
                    error_log("Upload destination: " . $filepath);
                    error_log("Upload tmp_name: " . $file['tmp_name']);
                    error_log("Upload size: " . $file['size']);
                    error_log("Upload is_uploaded_file check: " . (is_uploaded_file($file['tmp_name']) ? 'Yes' : 'No'));
                    error_log("Field label: " . $field['field_label'] . ", db_field_name: " . $db_field_name);
                    
                    // Try to move the uploaded file
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Special handling for Image/Photo field - map to common field names
                        if (strtolower($field['field_label']) == 'image' || strtolower($field['field_label']) == 'photo' || strtolower($field['field_label']) == 'passport') {
                            // Try multiple common field names for images
                            if (in_array('profile_picture', $tableColumns)) {
                                $field_data['profile_picture'] = $filename;
                            } else if (in_array('photo', $tableColumns)) {
                                $field_data['photo'] = $filename;
                            } else if (in_array('passport', $tableColumns)) {
                                $field_data['passport'] = $filename;
                            } else if (in_array('student_photo', $tableColumns)) {
                                $field_data['student_photo'] = $filename;
                            } else if (in_array('image', $tableColumns)) {
                                $field_data['image'] = $filename;
                            } else {
                                // Fallback to the standard field name mapping
                                if (in_array($db_field_name, $tableColumns)) {
                                    $field_data[$db_field_name] = $filename;
                                }
                            }
                            
                            // Also store in a file_path field if it exists
                            if (in_array('file_path', $tableColumns)) {
                                $field_data['file_path'] = $filepath;
                            }
                            
                            error_log("Image field handled with special mapping. Using fields: " . implode(", ", array_keys($field_data)));
                        } else {
                            // Only add if the column exists
                            if (in_array($db_field_name, $tableColumns)) {
                                $field_data[$db_field_name] = $filename;
                            }
                        }
                        
                        // Log successful upload
                        error_log("File upload successful: " . $filepath);
                        error_log("File exists after upload: " . (file_exists($filepath) ? 'Yes' : 'No'));
                        error_log("File size after upload: " . (file_exists($filepath) ? filesize($filepath) : 'N/A'));
                    } else {
                        // Get more details about the error
                        $phpFileUploadErrors = array(
                            0 => 'There is no error, the file uploaded with success',
                            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                            3 => 'The uploaded file was only partially uploaded',
                            4 => 'No file was uploaded',
                            6 => 'Missing a temporary folder',
                            7 => 'Failed to write file to disk',
                            8 => 'A PHP extension stopped the file upload',
                        );
                        
                        // Log detailed error information
                        error_log("File upload failed. PHP error code: " . $file['error']);
                        error_log("PHP error message: " . ($phpFileUploadErrors[$file['error']] ?? 'Unknown error'));
                        error_log("PHP last error: " . (error_get_last() ? json_encode(error_get_last()) : 'No error message'));
                        error_log("Upload directory path: " . realpath($uploads_dir));
                        error_log("Target filepath: " . $filepath);
                        error_log("Directory permissions: " . substr(sprintf('%o', fileperms($uploads_dir)), -4));
                        error_log("is_writable check: " . (is_writable($uploads_dir) ? 'Yes' : 'No'));
                        error_log("is_uploaded_file check: " . (is_uploaded_file($file['tmp_name']) ? 'Yes' : 'No'));
                        error_log("File temp name exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No'));
                        
                        // Attempt to use alternative file upload approach
                        $uploadSuccess = false;
                        
                        // Try direct file copy as alternative
                        if (copy($file['tmp_name'], $filepath)) {
                            error_log("File copy successful as alternative: " . $filepath);
                            if (in_array($db_field_name, $tableColumns)) {
                                $field_data[$db_field_name] = $filename;
                            }
                            $uploadSuccess = true;
                        } 
                        
                        // Try file_put_contents as another alternative
                        if (!$uploadSuccess && file_exists($file['tmp_name'])) {
                            $fileContent = file_get_contents($file['tmp_name']);
                            if ($fileContent !== false && file_put_contents($filepath, $fileContent)) {
                                error_log("File write successful using file_put_contents: " . $filepath);
                                if (in_array($db_field_name, $tableColumns)) {
                                    $field_data[$db_field_name] = $filename;
                                }
                                $uploadSuccess = true;
                            }
                        }
                        
                        if (!$uploadSuccess) {
                            throw new Exception("Error uploading file for " . $field['field_label'] . ": " . 
                                ($phpFileUploadErrors[$file['error']] ?? 'Unknown error'));
                        }
                    }
                } elseif ($field['required']) {
                    $errorMessage = "Required file missing for " . $field['field_label'];
                    if ($file['error'] > 0) {
                        $errorMessage .= " (Error code: " . $file['error'] . ")";
                    }
                    throw new Exception($errorMessage);
                }
            } else {
                // Handle other field types
                $value = $_POST[$field_name] ?? '';
                
                // Special handling for checkbox fields which might be arrays
                if ($field['field_type'] === 'checkbox' && isset($_POST[$field_name])) {
                    // Handle checkbox fields (multiple values as comma-separated)
                    if (is_array($_POST[$field_name])) {
                        $value = implode(', ', $_POST[$field_name]);
                        
                        // Special handling for "Child Lives With" checkbox
                        if (strtolower($field['field_label']) === 'child lives with' && in_array('child_lives_with', $tableColumns)) {
                            $field_data['child_lives_with'] = $value;
                            error_log("Child Lives With checkbox mapped: $value");
                        }
                    }
                }
                
                if ($field['required'] && empty($value)) {
                    throw new Exception("Required field missing: " . $field['field_label']);
                }
                
                // Only add if the column exists in the database
                if (in_array($db_field_name, $tableColumns)) {
                    $field_data[$db_field_name] = $value;
                }
                
                // Special handling for specific parent/guardian fields 
                // This ensures these fields are stored regardless of how they're named in the form
                if ($field['field_category'] === 'parent_info' || $field['field_category'] === 'guardian_info') {
                    // Map parent and guardian fields directly by their standardized db column names
                    $parentFieldMap = [
                        'father\'s name' => 'father_s_name',
                        'father\'s occupation' => 'father_s_occupation',
                        'father\'s office address' => 'father_s_office_address',
                        'father\'s contact phone number' => 'father_s_contact_phone_number_s_',
                        'father\'s contact phone number(s)' => 'father_s_contact_phone_number_s_',
                        'mother\'s name' => 'mother_s_name',
                        'mother\'s occupation' => 'mother_s_occupation',
                        'mother\'s office address' => 'mother_s_office_address',
                        'mother\'s contact phone number' => 'mother_s_contact_phone_number_s_',
                        'mother\'s contact phone number(s)' => 'mother_s_contact_phone_number_s_',
                        'guardian name' => 'guardian_name',
                        'guardian occupation' => 'guardian_occupation',
                        'guardian office address' => 'guardian_office_address',
                        'guardian contact phone number' => 'guardian_contact_phone_number',
                        'child lives with' => 'child_lives_with'
                    ];
                    
                    // Try to match the field label to a standard parent/guardian field
                    $normalizedLabel = strtolower($field['field_label']);
                    foreach ($parentFieldMap as $fieldPattern => $dbField) {
                        if (strpos($normalizedLabel, $fieldPattern) !== false || $normalizedLabel === $fieldPattern) {
                            // Check if the field exists in the database
                            if (in_array($dbField, $tableColumns)) {
                                $field_data[$dbField] = $value;
                                error_log("Parent/guardian field mapped: {$field['field_label']} -> $dbField = $value");
                            } else {
                                error_log("Parent/guardian field column not found: $dbField");
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // Auto-fill email and phone from payment verification if present and columns exist
        if (isset($_SESSION['verified_payment_email']) && !isset($field_data['email']) && in_array('email', $tableColumns)) {
            $field_data['email'] = $_SESSION['verified_payment_email'];
        }
        
        if (isset($_SESSION['verified_payment_phone']) && !isset($field_data['phone']) && in_array('phone', $tableColumns)) {
            $field_data['phone'] = $_SESSION['verified_payment_phone'];
        }
        
        // Handle the manually added Class/Level field if it's not already set from form fields
        if (isset($_POST['student_class']) && !empty($_POST['student_class'])) {
            // Check for different possible column names for the class/level
            $class_field_names = ['class', 'level', 'grade', 'student_class'];
            $class_field_set = false;
            
            foreach ($class_field_names as $column) {
                if (in_array($column, $tableColumns)) {
                    $field_data[$column] = $_POST['student_class'];
                    error_log("Setting student class to column: $column = {$_POST['student_class']}");
                    $class_field_set = true;
                    break;
                }
            }
            
            // If no matching column was found, add it to a common column as fallback
            if (!$class_field_set && in_array('class', $tableColumns)) {
                $field_data['class'] = $_POST['student_class'];
                error_log("Setting student class to default column: class = {$_POST['student_class']}");
            }
        }
        
        // Make sure we have at least one field to insert
        if (empty($field_data)) {
            throw new Exception("No valid fields to insert. Please contact the administrator.");
        }
        
        // Log final field data for debugging
        error_log("Final field data for insertion: " . print_r($field_data, true));
        
        // Check if parent fields are included
        $parent_fields = ['father_s_name', 'mother_s_name', 'guardian_name', 'child_lives_with'];
        $missing_parent_fields = array_filter($parent_fields, function($field) use ($field_data) {
            return !isset($field_data[$field]);
        });
        
        if (!empty($missing_parent_fields)) {
            error_log("Warning: Missing parent fields: " . implode(", ", $missing_parent_fields));
        }
        
        // Build the INSERT query
        $columns = array_map(function($key) {
            return '`' . $key . '`';
        }, array_keys($field_data));
        
        $placeholders = array_fill(0, count($field_data), '?');
        
        $sql = "INSERT INTO students (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $mysqli->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error preparing statement: " . $mysqli->error . " SQL: " . $sql);
        }
        
        $types = str_repeat('s', count($field_data));
        $bind_params = array_merge([$types], array_values($field_data));
        
        // Convert array to references for bind_param
        $bind_refs = [];
        foreach($bind_params as $key => $value) {
            $bind_refs[$key] = &$bind_params[$key];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $bind_refs);
                
                if (!$stmt->execute()) {
            throw new Exception("Error executing statement: " . $stmt->error);
        }
        
        // Commit transaction
        $mysqli->commit();
        
        // Clear payment verification session
        unset($_SESSION['verified_payment_reference']);
        unset($_SESSION['verified_payment_email']);
        unset($_SESSION['verified_payment_phone']);
        unset($_SESSION['payment_verified_time']);
        
        // Set success message
        $_SESSION['success_message'] = 'Registration completed successfully! Your registration number is: ' . $registration_number;
        header('Location: registration_success.php?reg=' . $registration_number);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
        
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: reg_form.php?type=' . ($registrationType ?? 'kiddies'));
        exit;
    }
} else {
    header('Location: reg_form.php');
    exit;
} 