<?php
require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If teacher is already logged in, redirect to the CBT dashboard
if (isset($_SESSION['teacher_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
    header("Location: dashboard.php");
    exit;
}

// Process login form submission
$error = '';
$success = '';

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $employee_id = trim($_POST['employee_id'] ?? '');
    
    if (empty($employee_id)) {
        $error = 'Please enter your Employee ID';
    } else {
        // Check if the employee ID exists in the teachers table (including class teachers)
        $query = "SELECT t.id, t.first_name, t.last_name, t.user_id, u.username, u.email, u.role
                  FROM teachers t 
                  JOIN users u ON t.user_id = u.id 
                  WHERE t.employee_id = ? AND (u.role = 'teacher' OR u.role = 'class_teacher')";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $teacher = $result->fetch_assoc();
            
            // Set session variables
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['user_id'] = $teacher['user_id'];
            $_SESSION['username'] = $teacher['username'];
            $_SESSION['name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
            $_SESSION['email'] = $teacher['email'];
            $_SESSION['role'] = $teacher['role']; // Store the actual role from the database
            $_SESSION['is_cbt'] = true;
            
            // Redirect to CBT dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Invalid Employee ID. Please try again.';
        }
    }
}

// Page title
$pageTitle = "Teacher Login - CBT System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Custom styles -->
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #3a56b8;
            --secondary: #f6c23e;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            border-bottom: none;
            padding: 20px;
            text-align: center;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-control {
            border-radius: 6px;
            height: 50px;
            font-size: 16px;
            padding: 10px 15px;
            border: 1px solid #e3e6f0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
            border-color: #bac8f3;
        }
        
        .btn {
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a56b8 0%, #1a3a9c 100%);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .alert {
            border-radius: 6px;
        }
        
        .input-group-text {
            background-color: #e9ecef;
            border: 1px solid #e3e6f0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <h1 class="h3 text-dark">ACE COLLEGE</h1>
            <p class="text-muted">Computer-Based Test (CBT) System</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h4 class="m-0"><i class="fas fa-user-shield mr-2"></i>Teacher Login</h4>
            </div>
            
            <div class="card-body">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="employee_id"><i class="fas fa-id-card mr-2"></i>Employee ID/Password</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user-lock"></i></span>
                            </div>
                            <input type="password" class="form-control" id="employee_id" name="employee_id" placeholder="Enter your employee ID" required>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <button type="submit" name="login" class="btn btn-primary btn-block">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login to CBT System
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="../../index.php" class="text-decoration-none">
                            <i class="fas fa-home mr-1"></i> Back to Home
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="card-footer bg-light text-center py-3">
                <p class="mb-0 text-muted">
                    <small>&copy; <?php echo date('Y'); ?> ACE COLLEGE. All rights reserved.</small>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });
    </script>
</body>
</html> 