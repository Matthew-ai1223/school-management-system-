<?php
require_once 'config.php';
require_once 'database.php';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Process form submissions
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and process teacher registration
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $employeeId = $_POST['employee_id'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $joiningDate = $_POST['joining_date'] ?? '';
    
    // Handle passport image upload
    $passportImage = null;
    if (isset($_FILES['passport_image']) && $_FILES['passport_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['passport_image']['type'], $allowedTypes)) {
            $errorMessage = "Invalid file type. Only JPG, JPEG, and PNG files are allowed.";
        } elseif ($_FILES['passport_image']['size'] > $maxSize) {
            $errorMessage = "File is too large. Maximum size is 5MB.";
        } else {
            $uploadDir = 'uploads/passport_images/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = uniqid('passport_') . '_' . basename($_FILES['passport_image']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['passport_image']['tmp_name'], $targetPath)) {
                $passportImage = $fileName;
            } else {
                $errorMessage = "Failed to upload image.";
            }
        }
    }
    
    // Basic validation
    if (empty($firstName) || empty($lastName) || empty($employeeId) || empty($email)) {
        $errorMessage = "Required fields cannot be empty.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Check if email already exists in users table
            $checkEmail = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($checkEmail);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Email already exists in the system.");
            }
            
            // Check if employee ID already exists
            $checkEmployeeId = "SELECT id FROM teachers WHERE employee_id = ?";
            $stmt = $conn->prepare($checkEmployeeId);
            $stmt->bind_param("s", $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Employee ID already exists.");
            }
            
            // Create user account for teacher
            $username = strtolower($firstName . '.' . $lastName);
            $baseUsername = $username;
            $counter = 1;
            
            // Make sure username is unique
            while (true) {
                $checkUsername = "SELECT id FROM users WHERE username = ?";
                $stmt = $conn->prepare($checkUsername);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    break;
                }
                
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            // Generate a temporary password
            $tempPassword = substr(md5(uniqid(rand(), true)), 0, 8);
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Insert user with pending status
            $insertUser = "INSERT INTO users (username, password, email, role, status, first_name, last_name, created_at) VALUES (?, ?, ?, 'teacher', 'pending', ?, ?, NOW())";
            $stmt = $conn->prepare($insertUser);
            $stmt->bind_param("sssss", $username, $hashedPassword, $email, $firstName, $lastName);
            $stmt->execute();
            $userId = $conn->insert_id;
            
            // Get subjects from form
            $subjects = $_POST['subjects'] ?? '';

            // Insert teacher with passport image and subjects
            $insertTeacher = "INSERT INTO teachers (user_id, first_name, last_name, employee_id, joining_date, qualification, subjects, phone, passport_image) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertTeacher);
            $stmt->bind_param("issssssss", $userId, $firstName, $lastName, $employeeId, $joiningDate, $qualification, $subjects, $phone, $passportImage);
            
            if ($stmt->execute()) {
                // Notify admin (you could send an email here)
                
                $conn->commit();
                $successMessage = "Registration submitted successfully! Your account is pending admin approval. You will be notified when your account is activated.";
            } else {
                throw new Exception("Error registering teacher: " . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --background-color: #f1f5f9;
            --card-background: #ffffff;
            --text-color: #1e293b;
        }

        body {
            background-color: var(--background-color);
            padding: 40px 0;
            color: var(--text-color);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
        }

        .registration-container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-background);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 2rem;
        }

        .school-logo {
            text-align: center;
            margin-bottom: 2.5rem;
            padding: 1.5rem 0;
            border-bottom: 2px solid #e2e8f0;
        }

        .school-logo h2 {
            color: var(--primary-color);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .school-logo h4 {
            color: var(--secondary-color);
            font-size: 1.25rem;
            font-weight: 500;
        }

        .form-control {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .required-field::after {
            content: "*";
            color: var(--danger-color);
            margin-left: 4px;
        }

        label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
            color: var(--text-color);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .alert {
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border: none;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background-color: #fef2f2;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-info {
            background-color: #eff6ff;
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.25em;
        }

        .form-check-label {
            padding-left: 0.5rem;
        }

        .text-muted {
            color: var(--secondary-color) !important;
            font-size: 0.875rem;
        }

        #image_preview img {
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .mb-3 {
            margin-bottom: 1.5rem !important;
        }

        .mb-4 {
            margin-bottom: 2rem !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="registration-container">
            <div class="school-logo">
                <h2><?php echo SCHOOL_NAME; ?></h2>
                <h4>Teacher Registration</h4>
            </div>
            
            <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo $successMessage; ?>
                <p class="mt-3">
                    <a href="" class="btn btn-primary">Return to Homepage</a>
                </p>
            </div>
            <?php elseif (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <?php echo $errorMessage; ?>
            </div>
            <?php else: ?>
            
            <div class="alert alert-info">
                <p>Complete the form below to register as a teacher. Your account will be reviewed by an administrator before activation.</p>
            </div>
            
            <form method="post" action="" enctype="multipart/form-data">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="passport_image" class="required-field">Passport Photo</label>
                                <input type="file" class="form-control" id="passport_image" name="passport_image" accept="image/jpeg,image/png,image/jpg" required>
                                <small class="text-muted">Max size: 5MB. Formats: JPG, JPEG, PNG</small>
                                <div id="image_preview" class="mt-2 d-none">
                                    <img src="" alt="Preview" class="img-thumbnail" style="max-width: 150px;">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="required-field">Surname</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="last_name" class="required-field">Other Names</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="required-field">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="qualification"> Highest Qualification with graduation year</label>
                                <input type="text" class="form-control" id="qualification" name="qualification">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="subjects" class="required-field">List Of Subject(s) Teaching</label>
                                <input type="text" class="form-control" id="subjects" name="subjects" placeholder="e.g. Mathematics, English, Physics" required>
                                <small class="text-muted">Separate multiple subjects with commas</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="joining_date">Joining Date</label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="employee_id" class="required-field">Password</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" required>
                                <small class="text-muted">Choose a strong password that you'll remember</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I confirm that all the information provided is accurate and complete.
                    </label>
                </div>
                
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary">Submit Registration</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('passport_image').addEventListener('change', function(e) {
        const preview = document.getElementById('image_preview');
        const img = preview.querySelector('img');
        const file = e.target.files[0];
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                preview.classList.remove('d-none');
            }
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('d-none');
        }
    });
    </script>
</body>
</html> 