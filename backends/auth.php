<?php
// Authentication System
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'database.php';
require_once 'utils.php';

/**
 * Attempt to login a user
 * 
 * @param string $username Username
 * @param string $password Password
 * @param PDO $conn Database connection
 * @return array Result with status and data/message
 */
function login($username, $password, $conn) {
    // Sanitize input
    $username = sanitize($username);
    
    try {
        // Find user by username
        $stmt = $conn->prepare("SELECT id, username, password, email, role FROM users WHERE username = :username LIMIT 1");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            return ['status' => false, 'message' => 'Invalid username or password'];
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if (!verifyPassword($password, $user['password'])) {
            return ['status' => false, 'message' => 'Invalid username or password'];
        }
        
        // Get profile information based on role
        $profileData = getUserProfileData($user['id'], $user['role'], $conn);
        
        if (!$profileData) {
            return ['status' => false, 'message' => 'User profile not found'];
        }
        
        // Set session variables
        $_SESSION[SESSION_PREFIX . 'user_id'] = $user['id'];
        $_SESSION[SESSION_PREFIX . 'username'] = $user['username'];
        $_SESSION[SESSION_PREFIX . 'email'] = $user['email'];
        $_SESSION[SESSION_PREFIX . 'role'] = $user['role'];
        $_SESSION[SESSION_PREFIX . 'profile_id'] = $profileData['id'];
        $_SESSION[SESSION_PREFIX . 'name'] = $profileData['first_name'] . ' ' . $profileData['last_name'];
        
        if (isset($profileData['profile_image']) && $profileData['profile_image']) {
            $_SESSION[SESSION_PREFIX . 'profile_image'] = $profileData['profile_image'];
        } else {
            // Set default profile image based on role
            switch ($user['role']) {
                case 'admin':
                    $_SESSION[SESSION_PREFIX . 'profile_image'] = DEFAULT_ADMIN_IMAGE;
                    break;
                case 'teacher':
                    $_SESSION[SESSION_PREFIX . 'profile_image'] = DEFAULT_TEACHER_IMAGE;
                    break;
                case 'student':
                    $_SESSION[SESSION_PREFIX . 'profile_image'] = DEFAULT_STUDENT_IMAGE;
                    break;
                case 'parent':
                    $_SESSION[SESSION_PREFIX . 'profile_image'] = DEFAULT_PARENT_IMAGE;
                    break;
                default:
                    $_SESSION[SESSION_PREFIX . 'profile_image'] = DEFAULT_ADMIN_IMAGE;
            }
        }
        
        // Log successful login
        logActivity($conn, 'login', 'User logged in successfully', $user['id']);
        
        return [
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'profile' => $profileData
            ]
        ];
        
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get user profile data based on role
 * 
 * @param int $userId User ID
 * @param string $role User role
 * @param PDO $conn Database connection
 * @return array|false Profile data or false if not found
 */
function getUserProfileData($userId, $role, $conn) {
    $tableName = '';
    
    switch ($role) {
        case 'admin':
            $tableName = 'admins';
            break;
        case 'teacher':
            $tableName = 'teachers';
            break;
        case 'student':
            $tableName = 'students';
            break;
        case 'parent':
            $tableName = 'parents';
            break;
        default:
            return false;
    }
    
    // Join with users table to get profile_image
    $stmt = $conn->prepare("SELECT p.*, u.profile_image 
                           FROM {$tableName} p 
                           JOIN users u ON p.user_id = u.id 
                           WHERE p.user_id = :user_id LIMIT 1");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return false;
}

/**
 * Register a new user
 * 
 * @param array $userData User data (username, password, email, role)
 * @param array $profileData Profile data (first_name, last_name, etc.)
 * @param PDO $conn Database connection
 * @return array Result with status and data/message
 */
function registerUser($userData, $profileData, $conn) {
    try {
        // Only start a transaction if one isn't already active
        $startedTransaction = false;
        if (!$conn->inTransaction()) {
            $conn->beginTransaction();
            $startedTransaction = true;
        }
        
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
        $stmt->bindValue(':username', $userData['username']);
        $stmt->bindValue(':email', $userData['email']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            if ($startedTransaction) {
                $conn->rollBack();
            }
            return ['status' => false, 'message' => 'Username or email already exists'];
        }
        
        // Create user record
        $hashedPassword = hashPassword($userData['password']);
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, profile_image) 
                              VALUES (:username, :password, :email, :role, :profile_image)");
        
        $stmt->bindValue(':username', $userData['username']);
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':email', $userData['email']);
        $stmt->bindValue(':role', $userData['role']);
        $stmt->bindValue(':profile_image', $userData['profile_image'] ?? null);
        
        $stmt->execute();
        
        $userId = $conn->lastInsertId();
        
        // Create profile record based on role
        $tableName = '';
        $additionalFields = '';
        $additionalValues = '';
        $bindParams = [];
        
        switch ($userData['role']) {
            case 'admin':
                $tableName = 'admins';
                break;
                
            case 'teacher':
                $tableName = 'teachers';
                $additionalFields = ', employee_id, joining_date, qualification, experience';
                $additionalValues = ', :employee_id, :joining_date, :qualification, :experience';
                $bindParams = [
                    'employee_id' => $profileData['employee_id'] ?? generateEmployeeId($conn),
                    'joining_date' => $profileData['joining_date'] ?? date('Y-m-d'),
                    'qualification' => $profileData['qualification'] ?? null,
                    'experience' => $profileData['experience'] ?? 0
                ];
                break;
                
            case 'student':
                $tableName = 'students';
                $additionalFields = ', admission_number, admission_date, date_of_birth, gender, blood_group, class_id, class_type, roll_number, parent_name, parent_phone, parent_email, parent_address, previous_school, registration_number';
                $additionalValues = ', :admission_number, :admission_date, :date_of_birth, :gender, :blood_group, :class_id, :class_type, :roll_number, :parent_name, :parent_phone, :parent_email, :parent_address, :previous_school, :registration_number';
                $bindParams = [
                    'admission_number' => $profileData['admission_number'] ?? generateAdmissionNumber($conn),
                    'admission_date' => $profileData['admission_date'] ?? date('Y-m-d'),
                    'date_of_birth' => $profileData['date_of_birth'] ?? null,
                    'gender' => $profileData['gender'] ?? null,
                    'blood_group' => $profileData['blood_group'] ?? null,
                    'class_id' => $profileData['class_id'] ?? null,
                    'class_type' => $profileData['class_type'] ?? null,
                    'roll_number' => $profileData['roll_number'] ?? null,
                    'parent_name' => $profileData['parent_name'] ?? null,
                    'parent_phone' => $profileData['parent_phone'] ?? null,
                    'parent_email' => $profileData['parent_email'] ?? null,
                    'parent_address' => $profileData['parent_address'] ?? null,
                    'previous_school' => $profileData['previous_school'] ?? null,
                    'registration_number' => $profileData['registration_number'] ?? generateRegistrationNumber($conn)
                ];
                break;
                
            case 'parent':
                $tableName = 'parents';
                $additionalFields = ', occupation, relation_with_student';
                $additionalValues = ', :occupation, :relation_with_student';
                $bindParams = [
                    'occupation' => $profileData['occupation'] ?? null,
                    'relation_with_student' => $profileData['relation_with_student'] ?? null
                ];
                break;
                
            default:
                if ($startedTransaction) {
                    $conn->rollBack();
                }
                return ['status' => false, 'message' => 'Invalid role specified'];
        }
        
        $sql = "INSERT INTO {$tableName} (user_id, first_name, last_name, phone, address{$additionalFields}) 
                VALUES (:user_id, :first_name, :last_name, :phone, :address{$additionalValues})";
        
        $stmt = $conn->prepare($sql);
        
        // Bind base parameters
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':first_name', $profileData['first_name']);
        $stmt->bindValue(':last_name', $profileData['last_name']);
        $stmt->bindValue(':phone', $profileData['phone'] ?? null);
        $stmt->bindValue(':address', $profileData['address'] ?? null);
        
        // Bind additional parameters
        foreach ($bindParams as $param => $value) {
            $stmt->bindValue(':' . $param, $value);
        }
        
        $stmt->execute();
        
        // If it's a parent, link to student(s) if provided
        if ($userData['role'] == 'parent' && isset($profileData['student_ids']) && is_array($profileData['student_ids'])) {
            $parentId = $conn->lastInsertId();
            
            foreach ($profileData['student_ids'] as $studentId) {
                $stmt = $conn->prepare("INSERT INTO parent_student (parent_id, student_id) VALUES (:parent_id, :student_id)");
                $stmt->bindValue(':parent_id', $parentId);
                $stmt->bindValue(':student_id', $studentId);
                $stmt->execute();
            }
        }
        
        // Only commit if we started the transaction
        if ($startedTransaction) {
            $conn->commit();
        }
        
        // Log registration
        logActivity($conn, 'register', 'New ' . $userData['role'] . ' registered', $userId);
        
        return [
            'status' => true,
            'message' => ucfirst($userData['role']) . ' registered successfully',
            'data' => [
                'user_id' => $userId,
                'role' => $userData['role']
            ]
        ];
        
    } catch (PDOException $e) {
        // Only rollback if we started the transaction
        if (isset($startedTransaction) && $startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Generate a unique employee ID
 * 
 * @param PDO $conn Database connection
 * @return string Unique employee ID
 */
function generateEmployeeId($conn) {
    $prefix = 'EMP';
    $year = date('y');
    
    $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(employee_id, 8) AS UNSIGNED)) as max_id FROM teachers WHERE employee_id LIKE '{$prefix}{$year}%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextId = 1;
    if ($result && $result['max_id']) {
        $nextId = $result['max_id'] + 1;
    }
    
    return $prefix . $year . str_pad($nextId, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate a unique admission number for students
 * 
 * @param PDO $conn Database connection
 * @return string Unique admission number
 */
function generateAdmissionNumber($conn) {
    $year = date('Y');
    $prefix = 'ADM' . $year;
    
    $stmt = $conn->prepare("SELECT MAX(SUBSTRING(admission_number, 8)) as max_num FROM students WHERE admission_number LIKE :prefix");
    $searchPrefix = $prefix . '%';
    $stmt->bindParam(':prefix', $searchPrefix);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $maxNum = $result['max_num'] ? (int)$result['max_num'] : 0;
    $nextNum = $maxNum + 1;
    
    return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate a unique registration number for students
 * 
 * @param PDO $conn Database connection
 * @return string Unique registration number
 */
function generateRegistrationNumber($conn) {
    // Call generateAdmissionNumber to ensure both numbers are the same
    return generateAdmissionNumber($conn);
}

/**
 * Logout the current user
 */
function logout() {
    // Start a new session if one isn't already active
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Require login to access a page
 * 
 * @param string|array $roles Allowed role(s)
 * @param string $redirectUrl URL to redirect to if not logged in
 */
function requireLogin($roles = null, $redirectUrl = 'login.php') {
    if (!isLoggedIn()) {
        setFlashMessage('auth', 'Please login to access this page', 'warning');
        redirect($redirectUrl);
    }
    
    if ($roles !== null && !hasRole($roles)) {
        setFlashMessage('auth', 'You do not have permission to access this page', 'danger');
        redirect($redirectUrl);
    }
}

/**
 * Update a user's password
 * 
 * @param int $userId User ID
 * @param string $currentPassword Current password
 * @param string $newPassword New password
 * @param PDO $conn Database connection
 * @return array Result with status and message
 */
function updatePassword($userId, $currentPassword, $newPassword, $conn) {
    try {
        // Get current password hash
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id LIMIT 1");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            return ['status' => false, 'message' => 'User not found'];
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify current password
        if (!verifyPassword($currentPassword, $user['password'])) {
            return ['status' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Update password
        $hashedPassword = hashPassword($newPassword);
        
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        logActivity($conn, 'password_change', 'User changed their password', $userId);
        
        return ['status' => true, 'message' => 'Password updated successfully'];
        
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Request a password reset
 * 
 * @param string $email User email
 * @param PDO $conn Database connection
 * @return array Result with status and message
 */
function requestPasswordReset($email, $conn) {
    try {
        // Find user by email
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            return ['status' => false, 'message' => 'Email not found'];
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generate reset token
        $token = generateRandomString(32);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Check if table exists, create it if not
        $tableExists = $conn->query("SHOW TABLES LIKE 'password_resets'")->rowCount() > 0;
        
        if (!$tableExists) {
            $conn->exec("CREATE TABLE password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
        }
        
        // Delete any existing reset tokens for this user
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->execute();
        
        // Create new reset token
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expires);
        $stmt->execute();
        
        // Build reset URL
        $resetUrl = BASE_URL . '/reset_password.php?token=' . $token;
        
        // In a real application, send email with reset link
        // For now, just return the token
        
        return [
            'status' => true,
            'message' => 'Password reset link has been sent to your email',
            'data' => [
                'token' => $token,
                'reset_url' => $resetUrl
            ]
        ];
        
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Reset a user's password with a valid token
 * 
 * @param string $token Reset token
 * @param string $newPassword New password
 * @param PDO $conn Database connection
 * @return array Result with status and message
 */
function resetPassword($token, $newPassword, $conn) {
    try {
        // Find valid token
        $stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            return ['status' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $reset['user_id'];
        
        // Update password
        $hashedPassword = hashPassword($newPassword);
        
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        // Delete used token
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        logActivity($conn, 'password_reset', 'User reset their password', $userId);
        
        return ['status' => true, 'message' => 'Password has been reset successfully'];
        
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
?> 