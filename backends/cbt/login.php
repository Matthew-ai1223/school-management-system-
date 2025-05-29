<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['student_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_number = trim($_POST['registration_number'] ?? '');
    $class = trim($_POST['class'] ?? '');
    
    error_log("=== Login Process Start ===");
    error_log("POST data received - Registration: $registration_number, Class: $class");
    
    if (empty($registration_number) || empty($class)) {
        $error = 'Please fill in all fields.';
        error_log("Empty fields detected");
    } else {
        try {
            // Test database connection first
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            if (!$conn) {
                throw new Exception("Database connection failed");
            }
            
            error_log("Database connection successful");
            
            // First check if the student exists
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE registration_number = :reg_no");
            $check_stmt->execute([':reg_no' => $registration_number]);
            $student_exists = $check_stmt->fetchColumn();
            error_log("Student exists check: " . ($student_exists ? 'Yes' : 'No'));
            
            if (!$student_exists) {
                $error = 'Invalid registration number. Please check your credentials and try again.';
                error_log("Student not found: $registration_number");
            } else {
                // Get student details with class check
                $stmt = $conn->prepare("
                    SELECT id, first_name, last_name, registration_number, class, status 
                    FROM students 
                    WHERE registration_number = :reg_no 
                    AND class = :class 
                    AND status IN ('active', 'registered')
                ");
                
                $stmt->execute([
                    ':reg_no' => $registration_number,
                    ':class' => $class
                ]);
                
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Student query result: " . print_r($student, true));
                
                if ($student) {
                    // Clear any existing session data
                    session_unset();
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['student_id'] = $student['id'];
                    $_SESSION['student_name'] = ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '');
                    $_SESSION['registration_number'] = $student['registration_number'];
                    $_SESSION['class'] = $student['class'];
                    $_SESSION['last_activity'] = time();
                    
                    error_log("Session variables set: " . print_r($_SESSION, true));
                    
                    // Ensure clean output before redirect
                    if (ob_get_length()) ob_clean();
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid class or account not active. Please check your credentials and try again.';
                    error_log("No matching active student found for reg_no: $registration_number and class: $class");
                }
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $error = 'A system error occurred. Please try again later.';
        }
    }
}

// Get unique classes for dropdown
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->query("SELECT DISTINCT class FROM students WHERE status IN ('active', 'registered') AND class IS NOT NULL ORDER BY class");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("Available classes fetched: " . implode(", ", $classes));
} catch (Exception $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 90%;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .school-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 1rem;
        }
        .login-title {
            color: #2c3e50;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 5px;
        }
        .btn-login {
            padding: 0.75rem;
            font-weight: 600;
            background: #1a73e8;
            border: none;
        }
        .btn-login:hover {
            background: #1557b0;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #6c757d;
            text-decoration: none;
        }
        .back-link:hover {
            color: #1a73e8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <img src="../../../images/logo.png" alt="School Logo" class="school-logo">
            <h4 class="login-title">CBT Login Portal</h4>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger" role="alert">
            <?php 
            switch($_GET['error']) {
                case 'no_session':
                    echo 'Please log in to access the CBT system.';
                    break;
                case 'not_found':
                    echo 'Student record not found.';
                    break;
                case 'inactive':
                    echo 'Your account is not active.';
                    break;
                case 'system':
                    echo 'A system error occurred. Please try again.';
                    break;
                default:
                    echo 'An error occurred. Please try again.';
            }
            ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="registration_number" class="form-label">Registration Number</label>
                <input type="text" class="form-control" id="registration_number" name="registration_number" required>
            </div>
            <div class="mb-3">
                <label for="class" class="form-label">Class</label>
                <select class="form-control" id="class" name="class" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-login w-100">Login</button>
        </form>
        
        <a href="../student/registration/student_dashboard.php" class="back-link">
            <i class='bx bx-arrow-back'></i> Back to Student Portal
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 