<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
//     header('Location: login.php');
//     exit();
// }

$db = Database::getInstance()->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['students_file']) || $_FILES['students_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please select a valid CSV file.');
        }

        $file = $_FILES['students_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            throw new Exception('Error opening file.');
        }

        // Skip header row
        $header = fgetcsv($handle);
        $expected_headers = ['name', 'email', 'department', 'phone', 'gender'];
        if ($header !== $expected_headers) {
            throw new Exception('Invalid CSV format. Please use the template provided.');
        }

        $db->beginTransaction();
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        $row_number = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            
            // Validate row data
            if (count($row) !== 5) {
                $errors[] = "Row $row_number: Invalid number of columns";
                $error_count++;
                continue;
            }

            // Validate email format
            if (!filter_var($row[1], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row $row_number: Invalid email format";
                $error_count++;
                continue;
            }

            // Check if email already exists
            $query = "SELECT id FROM users WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->execute([':email' => $row[1]]);
            if ($stmt->fetch()) {
                $errors[] = "Row $row_number: Email already exists";
                $error_count++;
                continue;
            }

            // Validate gender
            if (!in_array(strtolower($row[4]), ['male', 'female', 'other'])) {
                $errors[] = "Row $row_number: Invalid gender. Must be male, female, or other";
                $error_count++;
                continue;
            }

            try {
                // Generate random password
                $password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $query = "INSERT INTO users (name, email, password, department, phone, gender, role) 
                         VALUES (:name, :email, :password, :department, :phone, :gender, 'student')";
                
                $stmt = $db->prepare($query);
                $result = $stmt->execute([
                    ':name' => $row[0],
                    ':email' => $row[1],
                    ':password' => $hashed_password,
                    ':department' => $row[2],
                    ':phone' => $row[3],
                    ':gender' => strtolower($row[4])
                ]);

                if ($result) {
                    // Send email with credentials
                    $to = $row[1];
                    $subject = "Your " . SITE_NAME . " Account Details";
                    $message = "Hello " . $row[0] . ",\n\n"
                            . "Your account has been created at " . SITE_NAME . ".\n"
                            . "Please use the following credentials to login:\n\n"
                            . "Email: " . $row[1] . "\n"
                            . "Password: " . $password . "\n\n"
                            . "Please change your password after first login.\n\n"
                            . "Best regards,\n"
                            . SITE_NAME . " Team";
                    
                    $headers = "From: " . SMTP_USER;
                    mail($to, $subject, $message, $headers);
                    
                    $success_count++;
                }
            } catch (PDOException $e) {
                $errors[] = "Row $row_number: Database error";
                $error_count++;
            }
        }

        fclose($handle);

        if ($error_count === 0) {
            $db->commit();
            $_SESSION['message'] = "Successfully imported $success_count students.";
            header('Location: students.php');
            exit();
        } else {
            $db->rollBack();
            $message = '<div class="alert alert-warning">';
            $message .= "Imported: $success_count, Errors: $error_count<br>";
            $message .= "Errors:<br>" . implode("<br>", $errors);
            $message .= '</div>';
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
    <title>Bulk Upload Students - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Bulk Upload Students</h1>
                </div>

                <?php echo $message; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5>Instructions:</h5>
                            <ol>
                                <li>Download the <a href="templates/students_template.csv">CSV template</a></li>
                                <li>Fill in the student details following the template format</li>
                                <li>Upload the completed CSV file</li>
                            </ol>
                            <p>Note: Each student will receive their login credentials via email</p>
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="students_file" class="form-label">Students CSV File</label>
                                <input type="file" class="form-control" id="students_file" 
                                       name="students_file" accept=".csv" required>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="students.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Upload Students</button>
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