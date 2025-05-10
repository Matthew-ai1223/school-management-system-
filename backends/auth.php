<?php
require_once 'config.php';
require_once 'database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login($username, $password) {
        $username = $this->db->escape($username);
        $query = "SELECT * FROM users WHERE username = '$username' AND status = 'active'";
        $result = $this->db->query($query);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                return true;
            }
        }
        return false;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'name' => $_SESSION['name']
            ];
        }
        return null;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }

    public function requireRole($roles) {
        $this->requireLogin();
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        if (!in_array($_SESSION['role'], $roles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }

    public function logout() {
        session_destroy();
        header('Location: login.php');
        exit();
    }
}
