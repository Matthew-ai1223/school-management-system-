<?php
// Admin - Add Student
session_start();

// Include required files
require_once '../database.php';
require_once '../config.php';
require_once '../utils.php';
require_once '../auth.php';

// Require admin login
requireLogin('admin', '../../login.php');

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize messages
$successMessage = '';
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
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $dateOfBirth = $_POST['date_of_birth'];
        $gender = sanitize($_POST['gender']);
        $bloodGroup = sanitize($_POST['blood_group'] ?? '');
        $classId = (int)$_POST['class_id'];
        $rollNumber = (int)$_POST['roll_number'];
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $parentName = sanitize($_POST['parent_name'] ?? '');
        $parentPhone = sanitize($_POST['parent_phone'] ?? '');
        $parentEmail = sanitize($_POST['parent_email'] ?? '');
        $parentAddress = sanitize($_POST['parent_address'] ?? '');
        
        // Validate data
        if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Validate password
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long.');
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match.');
        }
        
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Username or email already exists.');
        }
        
        // Check if roll number is unique within the class
        if ($rollNumber > 0) {
            $stmt = $conn->prepare("SELECT id FROM students WHERE class_id = :class_id AND roll_number = :roll_number LIMIT 1");
            $stmt->bindParam(':class_id', $classId);
            $stmt->bindParam(':roll_number', $rollNumber);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("Roll number $rollNumber is already taken in this class.");
            }
        }
        
        // Handle profile image upload
        $profileImage = null;
        if (!empty($_FILES['profile_image']['name'])) {
            $uploadResult = uploadFile($_FILES['profile_image'], 'profile_images/students', ['image/jpeg', 'image/png', 'image/gif']);
            
            if (!$uploadResult['status']) {
                throw new Exception('Profile image upload failed: ' . $uploadResult['message']);
            }
            
            $profileImage = $uploadResult['data']['relpath'];
        }
        
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
            'roll_number' => $rollNumber,
            'phone' => $phone,
            'address' => $address,
            'parent_name' => $parentName,
            'parent_phone' => $parentPhone,
            'parent_email' => $parentEmail,
            'parent_address' => $parentAddress
        ];
        
        // Register the student
        $result = registerUser($userData, $profileData, $conn);
        
        if (!$result['status']) {
            throw new Exception($result['message']);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $successMessage = "Student {$firstName} {$lastName} has been added successfully!";
        
        // Log activity
        logActivity($conn, 'add_student', "Added student: {$firstName} {$lastName}");
        
        // Clear form data
        $_POST = [];
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        
        // Set error message
        $errorMessage = $e->getMessage();
    }
}

// Page title
$pageTitle = "Add New Student";
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
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: #333;
            padding: 0.5rem 1rem;
        }
        
        .sidebar .nav-link.active {
            color: #2470dc;
        }
        
        .sidebar .nav-link:hover {
            color: #007bff;
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="#"><?php echo APP_NAME; ?></a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <input class="form-control form-control-dark w-100" type="text" placeholder="Search" aria-label="Search">
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="../../logout.php">Sign out</a>
            </div>
        </div>
    </header>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="students.php">
                                <i class="fas fa-user-graduate"></i> Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teachers.php">
                                <i class="fas fa-chalkboard-teacher"></i> Teachers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="classes.php">
                                <i class="fas fa-school"></i> Classes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="subjects.php">
                                <i class="fas fa-book"></i> Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="timetable.php">
                                <i class="fas fa-calendar-alt"></i> Timetable
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="attendance.php">
                                <i class="fas fa-clipboard-list"></i> Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exams.php">
                                <i class="fas fa-file-alt"></i> Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="fees.php">
                                <i class="fas fa-money-bill-wave"></i> Fees
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="announcements.php">
                                <i class="fas fa-bullhorn"></i> Announcements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="messages.php">
                                <i class="fas fa-envelope"></i> Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $pageTitle; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="students.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Students
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Success and Error Messages -->
                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-1"></i> <?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i> <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Student Registration Form -->
                <div class="card shadow">
                    <div class="card-body">
                        <form method="post" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <h4 class="mb-3 text-primary">Basic Information</h4>
                                </div>
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
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo $_POST['username'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Username is required.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="showPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Password is required.</div>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback">Please confirm your password.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="text" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo $_POST['date_of_birth'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="blood_group" class="form-label">Blood Group</label>
                                    <select class="form-select" id="blood_group" name="blood_group">
                                        <option value="">Select Blood Group</option>
                                        <option value="A+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="profile_image" class="form-label">Profile Image</label>
                                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                    <div class="form-text">Upload a square image for best results (max 2MB).</div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h4 class="mb-3 text-primary">Academic Information</h4>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="class_id" class="form-label">Class *</label>
                                    <select class="form-select" id="class_id" name="class_id" required>
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                                <?php echo $class['name'] . ' ' . $class['section']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a class.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="roll_number" class="form-label">Roll Number</label>
                                    <input type="number" class="form-control" id="roll_number" name="roll_number" value="<?php echo $_POST['roll_number'] ?? ''; ?>" min="1">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h4 class="mb-3 text-primary">Contact Information</h4>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $_POST['phone'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo $_POST['address'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h4 class="mb-3 text-primary">Parent/Guardian Information</h4>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_name" class="form-label">Parent/Guardian Name</label>
                                    <input type="text" class="form-control" id="parent_name" name="parent_name" value="<?php echo $_POST['parent_name'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_phone" class="form-label">Parent/Guardian Phone</label>
                                    <input type="tel" class="form-control" id="parent_phone" name="parent_phone" value="<?php echo $_POST['parent_phone'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="parent_email" class="form-label">Parent/Guardian Email</label>
                                    <input type="email" class="form-control" id="parent_email" name="parent_email" value="<?php echo $_POST['parent_email'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_address" class="form-label">Parent/Guardian Address</label>
                                    <textarea class="form-control" id="parent_address" name="parent_address" rows="3"><?php echo $_POST['parent_address'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Register Student
                                    </button>
                                    <a href="students.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
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
        
        // Show/hide password
        document.getElementById('showPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
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
                    
                    // Password match validation
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (password !== confirmPassword) {
                        document.getElementById('confirm_password').setCustomValidity('Passwords do not match');
                        event.preventDefault();
                        event.stopPropagation();
                    } else {
                        document.getElementById('confirm_password').setCustomValidity('');
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html> 