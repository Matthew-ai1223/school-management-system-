<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

class ClassTeacherAuth {
    private $db;
    private $conn;
    
    public function __construct() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        
        // Check if user is logged in as class teacher
        $this->checkAuth();
    }
    
    public function checkAuth() {
        // Get the current script name
        $current_script = basename($_SERVER['SCRIPT_NAME']);
        
        // Skip check for login page and other public pages
        $public_pages = ['login.php', 'cbt_login.php', 'forgot_password.php', 'reset_password.php'];
        if (in_array($current_script, $public_pages)) {
            return true;
        }
        
        // Skip check if this is a direct login attempt to CBT system
        if ($current_script === 'create_cbt_exam.php' && isset($_POST['direct_cbt_login']) && isset($_POST['employee_id'])) {
            return true;
        }
        
        // List of CBT-specific pages
        $cbt_pages = ['create_cbt_exam.php', 'manage_cbt_exams.php', 'manage_cbt_questions.php', 'view_cbt_results.php'];
        
        // Check if user is logged in
        if (!isset($_SESSION['class_teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'class_teacher') {
            // Store the requested page for redirect after login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            
            // Redirect to appropriate login page based on the current script
            if (in_array($current_script, $cbt_pages)) {
                header('Location: login.php?tab=cbt');  // Changed to use the main login page with tab parameter
                exit;
            } else {
                header('Location: login.php');
                exit;
            }
        }
        
        // For CBT pages, check if user has CBT access
        if (in_array($current_script, $cbt_pages) && (!isset($_SESSION['cbt_access']) || $_SESSION['cbt_access'] !== true)) {
            // If user is logged in but doesn't have CBT access, redirect to specific CBT login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            
            // Log them out of regular session
            $this->logout();
            
            // Redirect to CBT login
            header('Location: login.php?tab=cbt&error=no_cbt_access');  // Changed to use the main login page with tab parameter
            exit;
        }
        
        // User is authenticated
        return true;
    }
    
    public function login($email, $password, $destination = 'dashboard') {
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Please enter both email and password.'
            ];
        }
        
        // Get the teacher with the provided email
        $query = "SELECT u.id as user_id, u.password, t.id as teacher_id, ct.id as class_teacher_id, 
                t.first_name, t.last_name, u.email
                FROM users u 
                JOIN teachers t ON u.id = t.user_id
                JOIN class_teachers ct ON t.id = ct.teacher_id
                WHERE u.email = ? AND u.role = 'teacher' AND u.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password (assuming passwords are hashed)
            if (password_verify($password, $user['password'])) {
                // For demonstration purposes - if no hashing used yet
                // Replace this condition with your actual password verification
                // if ($password === $user['password']) {
                
                // Set session variables
                $_SESSION['class_teacher_id'] = $user['class_teacher_id'];
                $_SESSION['teacher_id'] = $user['teacher_id'];
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = 'class_teacher';
                
                // Determine redirect based on destination
                $redirect = 'dashboard.php';
                if ($destination === 'cbt') {
                    $redirect = 'create_cbt_exam.php';
                } elseif (!empty($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                }
                
                return [
                    'success' => true,
                    'message' => 'Login successful.',
                    'redirect' => $redirect
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid password. Please try again.'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'No class teacher account found with this email.'
            ];
        }
    }
    
    public function loginWithEmployeeID($employee_id, $class) {
        if (empty($employee_id) || empty($class)) {
            return [
                'success' => false,
                'message' => 'Please enter both Employee ID and Class.'
            ];
        }
        
        // Get the teacher with the provided employee ID who is assigned to the given class
        $query = "SELECT u.id as user_id, t.id as teacher_id, ct.id as class_teacher_id, 
                 t.first_name, t.last_name, t.employee_id, u.email, ct.class_name
                 FROM teachers t 
                 JOIN class_teachers ct ON t.id = ct.teacher_id
                 JOIN users u ON t.user_id = u.id
                 WHERE t.employee_id = ? AND ct.class_name = ? AND ct.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $employee_id, $class);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Set session variables
            $_SESSION['class_teacher_id'] = $user['class_teacher_id'];
            $_SESSION['teacher_id'] = $user['teacher_id'];
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['class_name'] = $user['class_name'];
            $_SESSION['role'] = 'class_teacher';
            
            // Always redirect to dashboard.php for class teacher login
            // Clear any stored redirect to ensure consistent behavior
            if (isset($_SESSION['redirect_after_login'])) {
                unset($_SESSION['redirect_after_login']);
            }
            
            return [
                'success' => true,
                'message' => 'Login successful.',
                'redirect' => 'dashboard.php'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid Employee ID or Class. Please check your credentials and try again.'
            ];
        }
    }
    
    public function loginWithEmployeeIDForCBT($employee_id) {
        if (empty($employee_id)) {
            return [
                'success' => false,
                'message' => 'Please enter your Employee ID.'
            ];
        }
        
        // Get the teacher with the provided employee ID
        $query = "SELECT u.id as user_id, t.id as teacher_id, t.first_name, t.last_name, 
                 t.employee_id, u.email 
                 FROM teachers t 
                 JOIN users u ON t.user_id = u.id
                 WHERE t.employee_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Now check if they are a class teacher
            $ctQuery = "SELECT ct.id as class_teacher_id, ct.class_name 
                       FROM class_teachers ct 
                       WHERE ct.teacher_id = ? AND ct.is_active = 1";
            
            $stmt = $this->conn->prepare($ctQuery);
            $stmt->bind_param("i", $user['teacher_id']);
            $stmt->execute();
            $ctResult = $stmt->get_result();
            
            // Always allow access to CBT system - bypass permission check
            $canManageCbt = true;
            
            // Set session variables
            $_SESSION['teacher_id'] = $user['teacher_id'];
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['role'] = 'teacher';
            
            // Add class teacher info if they are a class teacher
            if ($ctResult->num_rows > 0) {
                $ctData = $ctResult->fetch_assoc();
                $_SESSION['class_teacher_id'] = $ctData['class_teacher_id'];
                $_SESSION['class_name'] = $ctData['class_name'];
                $_SESSION['role'] = 'class_teacher';
            }
            
            // Set CBT access flag to true for all teachers
            $_SESSION['cbt_access'] = true;
            
            // Always redirect to create_cbt_exam.php for CBT login
            // Clear any stored redirect to ensure consistent behavior
            if (isset($_SESSION['redirect_after_login'])) {
                unset($_SESSION['redirect_after_login']);
            }
            
            return [
                'success' => true,
                'message' => 'Login successful.',
                'redirect' => 'create_cbt_exam.php'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid Employee ID. Please check your credentials and try again.'
            ];
        }
    }
    
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // If it's desired to kill the session, also delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Finally, destroy the session
        session_destroy();
        
        return true;
    }
}

// Create an instance of the auth class to automatically check authentication
$auth = new ClassTeacherAuth();

/**
 * Get teacher's full name
 */
function getTeacherName() {
    $first_name = $_SESSION['first_name'] ?? '';
    $last_name = $_SESSION['last_name'] ?? '';
    
    return $first_name . ' ' . $last_name;
}

/**
 * Get teacher's employee ID
 */
function getTeacherEmployeeID() {
    return $_SESSION['employee_id'] ?? '';
}

/**
 * Check teacher permissions for specific action
 */
function checkTeacherPermission($permission) {
    // Placeholder for permission system
    // In a real system, you would check against a permissions database
    return true;
} 