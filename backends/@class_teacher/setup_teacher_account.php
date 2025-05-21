<?php
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$error_message = '';
$success_message = '';

// Database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate form data
    if (empty($employee_id) || empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error_message = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Check if employee ID already exists
            $checkStmt = $conn->prepare("SELECT * FROM teachers WHERE employee_id = ?");
            $checkStmt->bind_param("s", $employee_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error_message = "This Employee ID is already registered.";
            } else {
                // Check if email is already used
                $checkEmailStmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
                $checkEmailStmt->bind_param("s", $email);
                $checkEmailStmt->execute();
                $checkEmailResult = $checkEmailStmt->get_result();
                
                if ($checkEmailResult->num_rows > 0) {
                    $error_message = "This email is already registered.";
                } else {
                    // Create user account
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $userStmt = $conn->prepare("INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'teacher', 1)");
                    $userStmt->bind_param("ss", $email, $hashedPassword);
                    $userStmt->execute();
                    $userId = $conn->insert_id;
                    
                    // Create teacher record
                    $teacherStmt = $conn->prepare("INSERT INTO teachers (user_id, first_name, last_name, employee_id) VALUES (?, ?, ?, ?)");
                    $teacherStmt->bind_param("isss", $userId, $first_name, $last_name, $employee_id);
                    $teacherStmt->execute();
                    $teacherId = $conn->insert_id;
                    
                    // Create class teacher record
                    $classTeacherStmt = $conn->prepare("INSERT INTO class_teachers (teacher_id) VALUES (?)");
                    $classTeacherStmt->bind_param("i", $teacherId);
                    $classTeacherStmt->execute();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success_message = "Account created successfully. You can now log in with your Employee ID and password.";
                }
            }
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = "Error creating account: " . $e->getMessage();
        }
    }
}

// Include header without authentication
include 'includes/header_login.php';
?>

<div class="register-box" style="width: 600px;">
    <div class="register-logo">
        <a href="../index.php"><b><?php echo SCHOOL_NAME; ?></b> - Teacher Registration</a>
    </div>
    
    <div class="card">
        <div class="card-body register-card-body">
            <p class="login-box-msg">Register a new teacher account for CBT</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                    <p><a href="login.php" class="btn btn-primary btn-sm mt-2">Go to Login</a></p>
                </div>
            <?php endif; ?>
            
            <form action="" method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <input type="text" name="employee_id" class="form-control" placeholder="Employee ID" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-id-card"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <input type="email" name="email" class="form-control" placeholder="Email" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-envelope"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <input type="text" name="first_name" class="form-control" placeholder="First name" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-user"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-user"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-lock"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-lock"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">Register</button>
                    </div>
                </div>
            </form>
            
            <p class="mt-3 mb-0">
                <a href="login.php" class="text-center">I already have an account</a>
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer_login.php'; ?> 