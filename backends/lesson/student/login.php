<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files with error handling
$config_file = '../confg.php';
if (!file_exists($config_file)) {
    die("Error: Configuration file not found. Please make sure config.php exists in the correct location.");
}
require_once $config_file;

// Database connection with error handling
try {
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? "Connection not established"));
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

require_once 'check_account_status.php';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $user = null;
    $table = null;

    try {
        // Check morning_students
        $stmt = $conn->prepare("SELECT * FROM morning_students WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $table = 'morning_students';
        }
        $stmt->close();

        // If not found, check afternoon_students
        if (!$user) {
            $stmt = $conn->prepare("SELECT * FROM afternoon_students WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $table = 'afternoon_students';
            }
            $stmt->close();
        }

        if ($user && password_verify($password, $user['password'])) {
            // Check if account is active and not expired
            $is_expired = strtotime($user['expiration_date']) < strtotime('today');
            
            if (!$user['is_active'] || $is_expired) {
                // If account is expired, update is_active to false
                if ($is_expired && $user['is_active']) {
                    $update_stmt = $conn->prepare("UPDATE $table SET is_active = FALSE WHERE email = ?");
                    if (!$update_stmt) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    $update_stmt->bind_param("s", $email);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                // Redirect to reactivation page
                header('Location: reactivate.php?email=' . urlencode($email) . '&table=' . urlencode($table));
                exit();
            }

            // Login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_table'] = $table;
            
            // Ensure no output has been sent before redirect
            if (!headers_sent()) {
                header('Location: dashboard.php');
                exit();
            } else {
                echo '<script>window.location.href = "dashboard.php";</script>';
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    } catch (Exception $e) {
        $error = "System error: " . $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .login-container {
            max-width: 400px;
            margin: 60px auto;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px 30px 20px 30px;
        }
        .form-label {
            color: #2c3e50;
            font-weight: 500;
        }
        .btn-primary {
            background-color: #4a90e2;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #357abd;
        }
        .error-message {
            color: #e74c3c;
            margin-bottom: 15px;
            text-align: center;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h3 class="text-center mb-4">Student Login</h3>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'expired'): ?>
            <div class="alert alert-warning">Your account has expired. Please reactivate your account to continue.</div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="mt-3 text-center">
            <a href="reg.php">Don't have an account? Register</a>
            <br>
            <a href="forgot_password.php" class="mt-2 d-inline-block">Forgot Password?</a>
        </div>
    </div>
</body>
</html>
