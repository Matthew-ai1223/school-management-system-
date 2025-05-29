<?php
session_start();
include 'config/config.php';
require_once 'includes/Database.php';

$message = '';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $user = null;
    $table = null;

    $db = Database::getInstance()->getConnection();

    // Check morning_students
    $stmt = $db->prepare("SELECT * FROM morning_students WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $table = 'morning_students';
    }

    // If not found, check afternoon_students
    if (!$user) {
        $stmt = $db->prepare("SELECT * FROM afternoon_students WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $table = 'afternoon_students';
        }
    }

    if ($user && password_verify($password, $user['password'])) {
        // Check if account is active and not expired
        $is_expired = strtotime($user['expiration_date']) < strtotime('today');
        
        if (!$user['is_active'] || $is_expired) {
            // If account is expired, update is_active to false
            if ($is_expired && $user['is_active']) {
                $update_stmt = $db->prepare("UPDATE $table SET is_active = :status WHERE email = :email");
                $update_stmt->execute([
                    ':status' => false,
                    ':email' => $email
                ]);
            }
            
            // Redirect to reactivation page
            header('Location: reactivate.php?email=' . urlencode($email) . '&table=' . urlencode($table));
            exit();
        }

        // Login success
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_table'] = $table;
        header('Location: dashboard.php');
        exit();
    } else {
        $message = '<div class="alert alert-danger">Invalid email or password.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            padding: 30px;
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
    </style>
</head>
<body class="bg-light">
    <div class="login-container">
        <h3 class="text-center mb-4">Student Login</h3>
        <?php echo $message; ?>
        <form method="POST" action="" autocomplete="off">
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
        <div class="text-center mt-3">
            <a href="forgot-password.php">Forgot Password?</a>
            <br>
            <a href="register.php" class="mt-2 d-inline-block">Don't have an account? Register</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 