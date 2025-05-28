<?php
class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function register($data) {
        try {
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }

            // Check if email already exists
            $query = "SELECT id FROM users WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':email' => $data['email']]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }

            $query = "INSERT INTO users (name, email, password, department, phone, gender) 
                     VALUES (:name, :email, :password, :department, :phone, :gender)";
            
            $stmt = $this->db->prepare($query);
            
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $result = $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':password' => $password,
                ':department' => $data['department'],
                ':phone' => $data['phone'],
                ':gender' => $data['gender']
            ]);

            if (!$result) {
                throw new Exception('Database error: ' . implode(', ', $stmt->errorInfo()));
            }

            return true;
        } catch(PDOException $e) {
            error_log("Registration PDO error: " . $e->getMessage());
            throw new Exception('Database error occurred');
        } catch(Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            throw $e;
        }
    }

    public function login($email, $password) {
        try {
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':email' => $email]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];

                // Check if this is an admin login attempt from admin area
                $is_admin_login = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
                if ($is_admin_login && !in_array($user['role'], ['admin', 'super_admin'])) {
                    session_destroy();
                    return false;
                }
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function logout() {
        session_destroy();
        return true;
    }
} 