<?php
require_once 'config.php';
require_once 'database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login($username, $password, $role = null) {
        $username = $this->db->escape($username);
        
        // Base query
        $query = "SELECT * FROM users WHERE username = '$username' AND status = 'active'";
        
        // If role is specified, add it to the query
        if ($role) {
            $role = $this->db->escape($role);
            $query .= " AND role = '$role'";
        }
        
        $result = $this->db->query($query);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Start session if not already started
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Set all necessary session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'] ?? '';
                $_SESSION['last_name'] = $user['last_name'] ?? '';
                $_SESSION['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                
                // Try to update last login time but don't fail if column doesn't exist
                try {
                    $this->updateLastLogin($user['id']);
                } catch (Exception $e) {
                    // Silently fail if column doesn't exist - login still succeeds
                }
                
                return $user;
            }
        }
        return false;
    }
    
    public function adminLogin($username, $password) {
        return $this->login($username, $password, 'admin');
    }
    
    public function classTeacherLogin($username, $password) {
        return $this->login($username, $password, 'class_teacher');
    }
    
    private function updateLastLogin($userId) {
        // Check if the last_login column exists before attempting to update it
        $checkQuery = "SHOW COLUMNS FROM users LIKE 'last_login'";
        $columnExists = $this->db->query($checkQuery);
        
        if ($columnExists && $columnExists->num_rows > 0) {
            $userId = $this->db->escape($userId);
            $this->db->query("UPDATE users SET last_login = NOW() WHERE id = '$userId'");
        }
    }
    
    public function redirectToDashboard() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: /backends/admin/dashboard.php');
                break;
            case 'class_teacher':
                header('Location: /backends/@class_teacher/dashboard.php');
                break;
            default:
                // Fallback dashboard
                header('Location: /index.php');
                break;
        }
        exit();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'role' => $_SESSION['role'] ?? null,
                'name' => $_SESSION['name'] ?? null
            ];
        }
        return null;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            $this->redirectToLogin();
        }
    }
    
    public function redirectToLogin() {
        // Determine which login page to go to based on the request URL
        $currentPath = $_SERVER['REQUEST_URI'];
        
        if (strpos($currentPath, '/admin/') !== false) {
            header('Location: /backends/admin/login.php');
        } elseif (strpos($currentPath, '/@class_teacher/') !== false) {
            header('Location: /backends/@class_teacher/login.php');
        } else {
            // Default login page
            header('Location: /login.php');
        }
        exit();
    }

    public function requireRole($roles) {
        $this->requireLogin();
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        if (!in_array($_SESSION['role'], $roles)) {
            header('Location: ACE MODEL COLLEGE/backends/admin/login.php');
            exit();
        }
    }

    public function logout() {
        // Get role before destroying session
        $role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
        
        // Clear session data
        session_destroy();
        
        // Redirect based on the role
        if ($role === 'admin') {
            header('Location: /backends/admin/login.php');
        } elseif ($role === 'class_teacher') {
            header('Location: /backends/@class_teacher/login.php');
        } else {
            header('Location: /login.php');
        }
        exit();
    }
}
