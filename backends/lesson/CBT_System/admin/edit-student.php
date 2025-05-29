<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

session_start();

// Check admin authentication
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit();
// }

$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$session = filter_input(INPUT_GET, 'session', FILTER_SANITIZE_STRING);

if (!$student_id || !in_array($session, ['morning', 'afternoon'])) {
    header('Location: students.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$message = '';

// Get student details from appropriate table
$table = $session . '_students';
$query = "SELECT * FROM $table WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: students.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if email is being changed and if it already exists
        if ($student['email'] !== $_POST['email']) {
            $query = "SELECT id FROM $table WHERE email = :email AND id != :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':email' => $_POST['email'],
                ':id' => $student_id
            ]);
            if ($stmt->fetch()) {
                throw new Exception('Email address already exists.');
            }
        }

        $query = "UPDATE $table 
                 SET fullname = :fullname,
                     email = :email,
                     department = :department,
                     phone = :phone,
                     expiration_date = :expiration_date
                 WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            ':id' => $student_id,
            ':fullname' => $_POST['fullname'],
            ':email' => $_POST['email'],
            ':department' => $_POST['department'],
            ':phone' => $_POST['phone'],
            ':expiration_date' => $_POST['expiration_date']
        ]);

        if ($result) {
            // Handle password reset if requested
            if (!empty($_POST['reset_password'])) {
                $new_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $query = "UPDATE $table SET password = :password WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':id' => $student_id,
                    ':password' => $hashed_password
                ]);

                // Send new password via email
                $to = $_POST['email'];
                $subject = "Your " . SITE_NAME . " Password Reset";
                $message = "Hello " . $_POST['fullname'] . ",\n\n"
                        . "Your password has been reset.\n"
                        . "Your new password is: " . $new_password . "\n\n"
                        . "Please change your password after logging in.\n\n"
                        . "Best regards,\n"
                        . SITE_NAME . " Team";
                
                $headers = "From: " . SMTP_USER;
                mail($to, $subject, $message, $headers);
            }

            $_SESSION['message'] = 'Student updated successfully.';
            header('Location: students.php');
            exit();
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .main-content {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Edit Student</h1>
                        <p class="text-muted mb-0">
                            <?php echo htmlspecialchars($student['fullname']); ?> 
                            (<?php echo ucfirst($session); ?> Session)
                        </p>
                    </div>
                </div>

                <?php echo $message; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="fullname" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="fullname" name="fullname" 
                                       value="<?php echo htmlspecialchars($student['fullname']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" 
                                       value="<?php echo htmlspecialchars($student['department']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($student['phone']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="expiration_date" class="form-label">Account Expiration Date</label>
                                <input type="date" class="form-control" id="expiration_date" name="expiration_date" 
                                       value="<?php echo date('Y-m-d', strtotime($student['expiration_date'])); ?>" required>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="reset_password" 
                                           name="reset_password" value="1">
                                    <label class="form-check-label" for="reset_password">
                                        Reset password and send new credentials via email
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="students.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Student</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 