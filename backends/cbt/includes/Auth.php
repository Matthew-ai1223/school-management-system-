<?php
class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function loginWithTeacherId($teacher_id) {
        try {
            // Check if the teacher ID exists and get teacher details
            $stmt = $this->db->prepare("
                SELECT t.*, u.username, u.email, u.role 
                FROM teachers t 
                JOIN users u ON t.user_id = u.id 
                WHERE t.employee_id = :teacher_id AND u.role = 'teacher'
            ");
            $stmt->execute([':teacher_id' => $teacher_id]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($teacher) {
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['user_id'] = $teacher['user_id'];
                $_SESSION['username'] = $teacher['username'];
                $_SESSION['name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
                $_SESSION['email'] = $teacher['email'];
                $_SESSION['role'] = 'teacher';
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['teacher_id']) && isset($_SESSION['role']);
    }

    public function logout() {
        session_destroy();
        session_start();
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.username, u.email, u.role 
                FROM teachers t 
                JOIN users u ON t.user_id = u.id 
                WHERE t.id = :teacher_id
            ");
            $stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting current user: " . $e->getMessage());
            return null;
        }
    }
} 