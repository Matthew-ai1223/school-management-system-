<?php
require_once '../confg.php';

// Verify token and email
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    header('Location: forgot_password.php?status=error&message=Invalid reset link');
    exit();
}

// Check if token exists and is valid
$stmt = $conn->prepare("SELECT email, student_type, expires_at FROM password_reset_tokens WHERE token = ? AND email = ? AND used = 0");
$stmt->bind_param("ss", $token, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: forgot_password.php?status=error&message=Invalid or expired reset link');
    exit();
}

$token_data = $result->fetch_assoc();
if (strtotime($token_data['expires_at']) < time()) {
    header('Location: forgot_password.php?status=error&message=Reset link has expired');
    exit();
}

// Verify email exists in the appropriate students table
$student_type = $token_data['student_type'];
$table = $student_type . '_students';
$stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: forgot_password.php?status=error&message=Invalid email address');
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-password-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        .btn-primary {
            background-color: #4a90e2;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #357abd;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }
        .password-toggle {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <h2 class="text-center mb-4">Reset Password</h2>
        
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'error'): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($_GET['message'] ?? 'An error occurred. Please try again.'); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form action="process_reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <div class="password-toggle">
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                    <span class="toggle-password" onclick="togglePassword('new_password')">👁️</span>
                </div>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="password-toggle">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                    <span class="toggle-password" onclick="togglePassword('confirm_password')">👁️</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 