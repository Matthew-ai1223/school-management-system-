<?php
require_once '../../config.php';
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$registration_number = isset($_GET['reg']) ? htmlspecialchars($_GET['reg']) : '';
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Success - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success-checkmark {
            font-size: 5rem;
            color: #28a745;
        }
        .registration-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0d6efd;
            padding: 10px;
            border: 2px dashed #0d6efd;
            border-radius: 8px;
            display: inline-block;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="success-checkmark mb-4">
                            ✓
                        </div>
                        <h2 class="mb-4">Registration Successful!</h2>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($registration_number): ?>
                            <p>Your Registration Number is:</p>
                            <div class="registration-number">
                                <?php echo htmlspecialchars($registration_number); ?>
                            </div>
                            <p class="text-muted">Please save this number for future reference.</p>
                        <?php endif; ?>

                        <div class="mt-4">
                            <a href="reg_form.php" class="btn btn-primary">Register Another Student</a>
                            <a href="../../" class="btn btn-outline-primary">Go to Homepage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 