<?php
// Student Self-Registration Form
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';
require_once '../auth.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize messages
$errorMessage = '';

// Get classes for select options
try {
    $stmt = $conn->query("SELECT id, name, section FROM classes ORDER BY name, section");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = 'Error fetching classes: ' . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Get form data
        $firstName = sanitize($_POST['first_name']);
        $lastName = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $dateOfBirth = $_POST['date_of_birth'];
        $gender = sanitize($_POST['gender']);
        $bloodGroup = sanitize($_POST['blood_group'] ?? '');
        
        // Process class_id - handle both numeric and string identifiers
        $classId = $_POST['class_id'];
        $classIdType = sanitize($_POST['class_id']);
        
        // If class_id is not numeric, store it separately and set class_id to NULL
        // to avoid foreign key constraint errors
        $classType = null;
        if (!is_numeric($classId)) {
            $classType = $classId; // Store the class type (e.g., "creche", "jss")
            $classId = null; // Set class_id to NULL to avoid foreign key error
        } else {
            $classId = (int)$classId; // Convert to integer for numeric IDs
        }
        
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $parentName = sanitize($_POST['parent_name'] ?? '');
        $parentPhone = sanitize($_POST['parent_phone'] ?? '');
        $parentEmail = sanitize($_POST['parent_email'] ?? '');
        $parentAddress = sanitize($_POST['parent_address'] ?? '');
        $previousSchool = sanitize($_POST['previous_school'] ?? '');
        
        // Validate data
        if (empty($firstName) || empty($lastName) || empty($email) || empty($dateOfBirth)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Generate username (firstname.lastname + random number if needed)
        $baseUsername = strtolower($firstName . '.' . $lastName);
        $username = $baseUsername;
        
        // Check if username exists, if so, add sequential number
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $counter = 1;
        while ($stmt->rowCount() > 0) {
            $username = $baseUsername . $counter;
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $counter++;
        }
        
        // Generate a serial number based on timestamp last 4 digits
        $serialNumber = substr(time(), -4);
        
        // Generate admission number first
        $admissionNumber = generateAdmissionNumber($conn);
        
        // Use admission number as the password instead of firstname+serialnumber
        $password = $admissionNumber;
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Email already exists. Please use a different email address.');
        }
        
        // Set default profile image
        $profileImage = null;
        
        // Insert user record
        $userData = [
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'role' => 'student',
            'profile_image' => $profileImage
        ];
        
        // Insert student profile
        $profileData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            'blood_group' => $bloodGroup,
            'class_id' => $classId,
            'class_type' => $classType,
            'phone' => $phone,
            'address' => $address,
            'parent_name' => $parentName,
            'parent_phone' => $parentPhone,
            'parent_email' => $parentEmail,
            'parent_address' => $parentAddress,
            'previous_school' => $previousSchool,
        ];
        
        // Generate admission number
        $admissionNumber = generateAdmissionNumber($conn);
        $profileData['admission_number'] = $admissionNumber;
        $profileData['registration_number'] = $admissionNumber; // Ensure registration number is the same
        
        // Register the student
        $result = registerUser($userData, $profileData, $conn);
        
        if (!$result['status']) {
            throw new Exception($result['message']);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Store registration data in session
        $_SESSION['registration_success'] = true;
        $_SESSION['success_message'] = "Registration successful! You can now login with your credentials.";
        $_SESSION['generated_username'] = $username;
        $_SESSION['generated_password'] = $admissionNumber; // Use admission number as the password
        
        // Store all student data for receipt generation
        $_SESSION['student_data'] = array_merge(
            $profileData,
            [
                'email' => $email,
                'username' => $username,
                'password' => $admissionNumber // Also set password to admission number
            ]
        );
        
        // Try to get class name if numeric ID
        if (is_numeric($classId)) {
            try {
                $stmtClass = $conn->prepare("SELECT name, section FROM classes WHERE id = :id");
                $stmtClass->bindParam(':id', $classId);
                $stmtClass->execute();
                if ($classData = $stmtClass->fetch(PDO::FETCH_ASSOC)) {
                    $_SESSION['student_data']['class_name'] = $classData['name'] . ' ' . $classData['section'];
                }
            } catch (PDOException $e) {
                // Ignore error, will use class ID instead
            }
        }
        
        // Redirect to confirmation page
        header('Location: reg_confirm.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction if active
        try {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
        } catch (PDOException $pdoEx) {
            // Log the error but don't display to user
            error_log("Transaction rollback error: " . $pdoEx->getMessage());
        }
        
        // Set error message
        $errorMessage = $e->getMessage();
    }
}

// Page title
$pageTitle = "Student Registration";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 20px;
        }
        
        .registration-form {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            color: #007bff;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="text-center mb-4">
                    <img src="../../images/logo.png" alt="<?php echo APP_NAME; ?> Logo" class="img-fluid mb-3" style="max-height: 100px;">
                    <h2><?php echo APP_NAME; ?></h2>
                    <h4>Student Registration Form</h4>
                    <p class="text-muted">Fill in the form below to register as a student</p>
                </div>
                
                <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="registration-form">
                    <form method="post" action="" class="needs-validation" novalidate>
                        
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-user me-2"></i>Basic Information</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">First name is required.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Last name is required.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                    <input type="text" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo $_POST['date_of_birth'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Date of birth is required.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <!-- <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option> -->
                                    </select>
                                    <div class="invalid-feedback">Please select a gender.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="previous_school" class="form-label">Previous School</label>
                                    <input type="text" class="form-control" id="previous_school" name="previous_school" value="<?php echo $_POST['previous_school'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Academic Information -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h4>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="class_id" class="form-label">Class *</label>
                                    <select class="form-select" id="class_id" name="class_id" required>
                                        <option value="">Select Class</option>
                                        <!-- Standard Class Options -->
                                        <option value="creche" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == 'creche') ? 'selected' : ''; ?>>Creche</option>
                                        <option value="playgroup" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == 'playgroup') ? 'selected' : ''; ?>>Playgroup</option>
                                        <option value="nursery" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == 'nursery') ? 'selected' : ''; ?>>Nursery</option>
                                        <option value="primary" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == 'primary') ? 'selected' : ''; ?>>Primary</option>
                                        <option value="jss" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == 'jss') ? 'selected' : ''; ?>>Junior Secondary School</option>
                                        <option value="sss" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == 'sss') ? 'selected' : ''; ?>>Senior Secondary School</option>
                                        <!-- Database Classes -->
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                                <?php echo $class['name'] . ' ' . $class['section']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a class.</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-address-card me-2"></i>Contact Information</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $_POST['phone'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo $_POST['address'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Parent/Guardian Information -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-users me-2"></i>Parent/Guardian Information</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="parent_name" class="form-label">Parent/Guardian Name *</label>
                                    <input type="text" class="form-control" id="parent_name" name="parent_name" value="<?php echo $_POST['parent_name'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Parent/Guardian name is required.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_phone" class="form-label">Parent/Guardian Phone *</label>
                                    <input type="tel" class="form-control" id="parent_phone" name="parent_phone" value="<?php echo $_POST['parent_phone'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Parent/Guardian phone is required.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="parent_email" class="form-label">Parent/Guardian Email</label>
                                    <input type="email" class="form-control" id="parent_email" name="parent_email" value="<?php echo $_POST['parent_email'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_address" class="form-label">Parent/Guardian Address</label>
                                    <textarea class="form-control" id="parent_address" name="parent_address" rows="2"><?php echo $_POST['parent_address'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms and Agreement -->
                        <div class="form-section">
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agreement" required>
                                <label class="form-check-label" for="agreement">
                                    I confirm that all information provided is accurate, and I agree to the terms and conditions of <?php echo APP_NAME; ?>.
                                </label>
                                <div class="invalid-feedback">
                                    You must agree before submitting.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i> Register
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="../../login.php">Login here</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- add @https://api.web3forms.com apt to the from 

here is my key  b94b6499-c9ac-47e1-9e08-be5e7f56a70f -->
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        // Initialize Flatpickr for date picker
        flatpickr("#date_of_birth", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
        
        // Form validation
        (() => {
            'use strict';
            
            // Fetch all the forms we want to apply custom validation to
            const forms = document.querySelectorAll('.needs-validation');
            
            // Loop over them and prevent submission
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
