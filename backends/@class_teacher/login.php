<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once 'class_teacher_auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect them
if (isset($_SESSION['class_teacher_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'class_teacher') {
    // Redirect based on where they're coming from
    $redirect = isset($_GET['dest']) && $_GET['dest'] === 'cbt' ? 'create_cbt_exam.php' : 'dashboard.php';
    header("Location: $redirect");
    exit;
}

// Process login form submissions
$error = '';
$success = '';

// Check for error messages from failed CBT login redirects
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Initialize auth class
$auth = new ClassTeacherAuth();

// Get classes for dropdown
$db = Database::getInstance();
$conn = $db->getConnection();

// Get unique class values from students table
$classesQuery = "SELECT DISTINCT class as name FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class";
$classesResult = $conn->query($classesQuery);
$classes = [];

if ($classesResult && $classesResult->num_rows > 0) {
    while ($row = $classesResult->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Get teacher employee IDs
$teachersQuery = "SELECT DISTINCT employee_id FROM teachers WHERE employee_id IS NOT NULL AND employee_id != '' ORDER BY employee_id";
$teachersResult = $conn->query($teachersQuery);
$teachers = [];

if ($teachersResult && $teachersResult->num_rows > 0) {
    while ($row = $teachersResult->fetch_assoc()) {
        $teachers[] = $row;
    }
}

// Process CBT login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cbt_login'])) {
    $employee_id = trim($_POST['employee_id'] ?? '');
    
    if (empty($employee_id)) {
        $error = 'Please enter your Employee ID';
    } else {
        $result = $auth->loginWithEmployeeIDForCBT($employee_id);
        
        if ($result['success']) {
            $success = $result['message'];
            // Force redirect to create_cbt_exam.php regardless of what the auth method returned
            header("Location: create_cbt_exam.php");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Process class teacher login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_login'])) {
    $employee_id = trim($_POST['teacher_employee_id'] ?? '');
    $class = trim($_POST['class'] ?? '');
    
    if (empty($employee_id)) {
        $error = 'Please enter your Employee ID';
    } elseif (empty($class)) {
        $error = 'Please select your class';
    } else {
        $result = $auth->loginWithEmployeeID($employee_id, $class);
        
        if ($result['success']) {
            $success = $result['message'];
            header("Location: {$result['redirect']}");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Include header with minimal styling
$pageTitle = "Login - ACE MODEL COLLEGE";
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
            max-width: 900px;
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
        
        .tab-content {
            padding: 30px;
        }
        
        .nav-tabs {
            border-bottom: none;
            background: linear-gradient(to right, #f8f9fa 0%, #ffffff 100%);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #5a5c69;
            font-weight: 600;
            padding: 15px 25px;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link.active {
            background-color: transparent;
            border-bottom: 3px solid #4e73df;
            color: #4e73df;
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
        
        .logo {
            max-width: 150px;
            margin-bottom: 15px;
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
            <!-- School logo could be placed here -->
            <h1 class="h3 text-dark">ACE MODEL COLLEGE</h1>
            <p class="text-muted">Teacher Portal</p>
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
            
            <ul class="nav nav-tabs" id="loginTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="teacher-tab" data-toggle="tab" href="#teacher" role="tab" aria-controls="teacher" aria-selected="true">
                        <i class="fas fa-chalkboard-teacher mr-2"></i>Class Teacher
                    </a>
                </li>
                <!-- <li class="nav-item" role="presentation">
                    <a class="nav-link" id="cbt-tab" data-toggle="tab" href="#cbt" role="tab" aria-controls="cbt" aria-selected="false">
                        <i class="fas fa-laptop-code mr-2"></i>CBT System
                    </a>
                </li> -->
            </ul>
            
            <div class="tab-content" id="loginTabsContent">
                <!-- Class Teacher Login Form -->
                <div class="tab-pane fade show active" id="teacher" role="tabpanel" aria-labelledby="teacher-tab">
                    <form method="post" action="login.php">
                        <div class="form-group">
                            <label for="teacher_employee_id"><i class="fas fa-id-card mr-2"></i>Employee ID</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-lock"></i></span>
                                </div>
                                <input type="password" class="form-control" id="teacher_employee_id" name="teacher_employee_id" placeholder="Enter your employee ID" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="class"><i class="fas fa-chalkboard mr-2"></i>Select Class</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-users"></i></span>
                                </div>
                                <select class="form-control" id="class" name="class" required>
                                    <option value="">Select your class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class['name']); ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="teacher_login" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login to Dashboard
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- CBT Login Form -->
                <!-- <div class="tab-pane fade" id="cbt" role="tabpanel" aria-labelledby="cbt-tab">
                    <form method="post" action="create_cbt_exam.php">
                        <div class="form-group">
                            <label for="employee_id"><i class="fas fa-id-card mr-2"></i>Employee ID</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-lock"></i></span>
                                </div>
                                <input type="password" class="form-control" id="employee_id" name="employee_id" placeholder="Enter your employee ID" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="cbt_login" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login to CBT System
                            </button>
                        </div>
                        <!-- Add a hidden field to indicate this is a CBT login attempt -->
                        <input type="hidden" name="direct_cbt_login" value="1">
                    </form>
                </div> -->
            </div>
            
            <div class="card-footer bg-light text-center py-3">
                <p class="mb-0 text-muted">
                    <small>&copy; <?php echo date('Y'); ?> ACE MODEL COLLEGE. All rights reserved.</small>
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
        
        // Set active tab based on URL parameter
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'cbt') {
            $('#cbt-tab').tab('show');
        }
    });
    </script>
</body>
</html>
