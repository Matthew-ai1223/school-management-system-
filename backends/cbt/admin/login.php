<?php
session_start();
require_once '../config/config.php';
require_once '../includes/Database.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $db = Database::getInstance()->getConnection();
    
    // Check admin credentials
    $stmt = $db->prepare("SELECT * FROM admins WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name'] = $admin['name'];
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
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 400px;
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
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">Admin Login</h2>
        <?php echo $message; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="../" class="text-decoration-none">Back to Main Site</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>