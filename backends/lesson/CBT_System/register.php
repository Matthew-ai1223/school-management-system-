<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

session_start();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $data = [
            'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING),
            'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'department' => filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING),
            'phone' => filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING),
            'gender' => filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING),
            'session' => filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING),
            'expiration_date' => date('Y-m-d', strtotime('+1 year')),
            'is_active' => true
        ];

        // Validate required fields
        foreach ($data as $key => $value) {
            if (empty($value)) {
                throw new Exception("$key is required");
            }
        }

        $db = Database::getInstance()->getConnection();

        // Check if email already exists in either table
        $stmt = $db->prepare("SELECT email FROM morning_students WHERE email = ? UNION SELECT email FROM afternoon_students WHERE email = ?");
        $stmt->bind_param("ss", $data['email'], $data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already registered");
        }
        $stmt->close();

        // Determine which table to insert into based on session
        $table = ($data['session'] === 'morning') ? 'morning_students' : 'afternoon_students';
        
        $sql = "INSERT INTO $table (name, email, password, department, phone, gender, expiration_date, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssssssi", 
            $data['name'], 
            $data['email'], 
            $data['password'],
            $data['department'],
            $data['phone'],
            $data['gender'],
            $data['expiration_date'],
            $data['is_active']
        );
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Registration successful! Please <a href="login.php">login</a>.</div>';
        } else {
            throw new Exception("Registration failed");
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Registration failed: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .register-container {
            max-width: 600px;
            margin: 40px auto;
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
<body>
    <div class="register-container">
        <h3 class="text-center mb-4">Student Registration</h3>
        <?php echo $message; ?>
        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="department" class="form-label">Department</label>
                <input type="text" class="form-control" id="department" name="department" required>
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Gender</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="gender" id="male" value="male" required>
                        <label class="form-check-label" for="male">Male</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="gender" id="female" value="female">
                        <label class="form-check-label" for="female">Female</label>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Session</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="session" id="morning" value="morning" required>
                        <label class="form-check-label" for="morning">Morning</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="session" id="afternoon" value="afternoon">
                        <label class="form-check-label" for="afternoon">Afternoon</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
        <div class="text-center mt-3">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 