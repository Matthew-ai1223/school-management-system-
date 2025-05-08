<?php
// Utility functions for School Management System
require_once 'config.php';

/**
 * Sanitize user input to prevent XSS attacks
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a secure password hash
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random string (for tokens, etc.)
 */
function generateRandomString($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

/**
 * Set a flash message for the next request
 */
function setFlashMessage($key, $message, $type = 'info') {
    if (!isset($_SESSION[SESSION_PREFIX . 'flash_messages'])) {
        $_SESSION[SESSION_PREFIX . 'flash_messages'] = [];
    }
    
    $_SESSION[SESSION_PREFIX . 'flash_messages'][$key] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear a flash message
 */
function getFlashMessage($key) {
    if (isset($_SESSION[SESSION_PREFIX . 'flash_messages'][$key])) {
        $message = $_SESSION[SESSION_PREFIX . 'flash_messages'][$key];
        unset($_SESSION[SESSION_PREFIX . 'flash_messages'][$key]);
        return $message;
    }
    
    return null;
}

/**
 * Display all flash messages
 */
function displayFlashMessages() {
    if (!isset($_SESSION[SESSION_PREFIX . 'flash_messages'])) {
        return '';
    }
    
    $output = '';
    
    foreach ($_SESSION[SESSION_PREFIX . 'flash_messages'] as $key => $data) {
        $output .= '<div class="alert alert-' . $data['type'] . ' alert-dismissible fade show" role="alert">';
        $output .= $data['message'];
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $output .= '</div>';
        
        unset($_SESSION[SESSION_PREFIX . 'flash_messages'][$key]);
    }
    
    return $output;
}

/**
 * Upload a file to the server
 */
function uploadFile($file, $destination = '', $allowedTypes = null, $maxSize = null) {
    // Set defaults if not provided
    if ($maxSize === null) {
        $maxSize = MAX_UPLOAD_SIZE;
    }
    
    if ($allowedTypes === null) {
        $allowedExtensions = explode(',', ALLOWED_EXTENSIONS);
        $allowedTypes = [];
        
        foreach ($allowedExtensions as $ext) {
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $allowedTypes[] = 'image/jpeg';
                    break;
                case 'png':
                    $allowedTypes[] = 'image/png';
                    break;
                case 'gif':
                    $allowedTypes[] = 'image/gif';
                    break;
                case 'pdf':
                    $allowedTypes[] = 'application/pdf';
                    break;
                case 'doc':
                case 'docx':
                    $allowedTypes[] = 'application/msword';
                    $allowedTypes[] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                    break;
                case 'xls':
                case 'xlsx':
                    $allowedTypes[] = 'application/vnd.ms-excel';
                    $allowedTypes[] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    break;
                case 'txt':
                    $allowedTypes[] = 'text/plain';
                    break;
            }
        }
    }
    
    // Check if file was uploaded properly
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['status' => false, 'message' => 'Invalid file parameter'];
    }
    
    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['status' => false, 'message' => 'File exceeded size limit'];
        case UPLOAD_ERR_PARTIAL:
            return ['status' => false, 'message' => 'File only partially uploaded'];
        case UPLOAD_ERR_NO_FILE:
            return ['status' => false, 'message' => 'No file was uploaded'];
        case UPLOAD_ERR_NO_TMP_DIR:
            return ['status' => false, 'message' => 'Missing a temporary folder'];
        case UPLOAD_ERR_CANT_WRITE:
            return ['status' => false, 'message' => 'Failed to write file to disk'];
        case UPLOAD_ERR_EXTENSION:
            return ['status' => false, 'message' => 'A PHP extension stopped the file upload'];
        default:
            return ['status' => false, 'message' => 'Unknown upload error'];
    }
    
    // Check filesize
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'File size exceeds the limit'];
    }
    
    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $fileType = $finfo->file($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['status' => false, 'message' => 'File type not allowed'];
    }
    
    // Create destination directory if it doesn't exist
    $uploadPath = UPLOAD_DIR . trim($destination, '/');
    
    if (!is_dir($uploadPath)) {
        if (!mkdir($uploadPath, 0755, true)) {
            return ['status' => false, 'message' => 'Failed to create upload directory'];
        }
    }
    
    // Generate a unique filename
    $fileInfo = pathinfo($file['name']);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileInfo['filename']);
    $filename = $filename . '_' . time() . '.' . $fileInfo['extension'];
    $filepath = $uploadPath . '/' . $filename;
    
    // Move the uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
    
    return [
        'status' => true,
        'message' => 'File uploaded successfully',
        'data' => [
            'filename' => $filename,
            'filepath' => $filepath,
            'relpath' => trim($destination, '/') . '/' . $filename,
            'filesize' => $file['size'],
            'filetype' => $fileType
        ]
    ];
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Convert percentage to grade
 */
function getGrade($percentage) {
    global $GLOBALS;
    
    foreach ($GLOBALS['GRADING_SCALE'] as $grade) {
        if ($percentage >= $grade['min'] && $percentage <= $grade['max']) {
            return $grade;
        }
    }
    
    // Default to F grade
    return $GLOBALS['GRADING_SCALE'][count($GLOBALS['GRADING_SCALE']) - 1];
}

/**
 * Calculate GPA from grades
 */
function calculateGPA($grades) {
    $totalPoints = 0;
    $totalCredits = 0;
    
    foreach ($grades as $grade) {
        $totalPoints += $grade['gpa'] * $grade['credit_hours'];
        $totalCredits += $grade['credit_hours'];
    }
    
    if ($totalCredits == 0) {
        return 0;
    }
    
    return round($totalPoints / $totalCredits, 2);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION[SESSION_PREFIX . 'user_id']);
}

/**
 * Get current user's role
 */
function getUserRole() {
    return isset($_SESSION[SESSION_PREFIX . 'user_role']) ? $_SESSION[SESSION_PREFIX . 'user_role'] : null;
}

/**
 * Check if user has a specific role
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = getUserRole();
    
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    
    return $userRole === $roles;
}

/**
 * Redirect to another page
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Check if it's an AJAX request
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get current academic year/session
 */
function getCurrentAcademicSession($conn) {
    $stmt = $conn->prepare("SELECT * FROM academic_sessions WHERE is_current = 1 LIMIT 1");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return null;
}

/**
 * Log user activity
 * 
 * @param PDO $conn Database connection
 * @param string $action Action performed
 * @param string $description Description of the action
 * @param int|null $userId User ID (null if not logged in)
 * @return bool Success status
 */
function logActivity($conn, $action, $description, $userId = null) {
    try {
        // Check if the activity_logs table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($stmt->rowCount() == 0) {
            // Table doesn't exist, so we can't log
            return false;
        }
        
        // Get IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Prepare statement
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                              VALUES (:user_id, :action, :description, :ip_address, :user_agent)");
        
        // Bind parameters
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':ip_address', $ipAddress);
        $stmt->bindValue(':user_agent', $userAgent);
        
        // Execute statement
        return $stmt->execute();
    } catch (PDOException $e) {
        // Log to error log but don't interrupt the user flow
        error_log("Activity logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get pagination links
 */
function getPaginationLinks($totalItems, $itemsPerPage, $currentPage, $baseUrl) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    if ($totalPages <= 1) {
        return '';
    }
    
    $output = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . 'page=' . ($currentPage - 1) . '">&laquo; Previous</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . 'page=1">1</a></li>';
        if ($startPage > 2) {
            $output .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $output .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $output .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $output .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        $output .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . 'page=' . ($currentPage + 1) . '">Next &raquo;</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
    }
    
    $output .= '</ul></nav>';
    
    return $output;
}

// Add activity_logs table if missing in db_setup.php
function createActivityLogsTable($conn) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'activity_logs'")->rowCount() > 0;
    
    if (!$tableExists) {
        $conn->exec("CREATE TABLE activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");
    }
}
?> 