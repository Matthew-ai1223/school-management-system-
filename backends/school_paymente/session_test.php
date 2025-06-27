<?php
// Set session configuration for better compatibility with shared hosting
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Test session endpoint
if (isset($_GET['test_session'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'session_debug' => [
            'session_id' => session_id(),
            'session_status' => session_status(),
            'session_data' => $_SESSION,
            'cookies' => $_COOKIE,
            'session_name' => session_name(),
            'session_save_path' => session_save_path()
        ],
        'student_logged_in' => isset($_SESSION['student_id']) && isset($_SESSION['registration_number']),
        'session_vars' => [
            'student_id' => $_SESSION['student_id'] ?? 'not_set',
            'registration_number' => $_SESSION['registration_number'] ?? 'not_set',
            'student_name' => $_SESSION['student_name'] ?? 'not_set'
        ],
        'session_config' => [
            'session_name' => session_name(),
            'session_save_path' => session_save_path(),
            'session_cookie_params' => session_get_cookie_params(),
            'session_status' => session_status()
        ],
        'server_info' => [
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'not_set',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'not_set',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not_set',
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'not_set'
        ]
    ]);
    exit;
}

// Handle session variable setting for testing
if (isset($_POST['set_session'])) {
    $_SESSION['test_student_id'] = $_POST['student_id'] ?? 'test_123';
    $_SESSION['test_registration_number'] = $_POST['registration_number'] ?? 'TEST/2025/001';
    $_SESSION['test_student_name'] = $_POST['student_name'] ?? 'Test Student';
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Test session variables set',
        'session_data' => $_SESSION
    ]);
    exit;
}

// Handle session clearing for testing
if (isset($_POST['clear_session'])) {
    session_unset();
    session_destroy();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Session cleared'
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Test Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #2c3e50;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-cogs"></i> Session Test Tool</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Session Information</h5>
                        <div class="mb-3">
                            <strong>Session ID:</strong> <?php echo session_id(); ?><br>
                            <strong>Session Status:</strong> <?php echo session_status(); ?><br>
                            <strong>Session Name:</strong> <?php echo session_name(); ?><br>
                            <strong>Session Save Path:</strong> <?php echo session_save_path(); ?>
                        </div>
                        
                        <h5>Current Session Variables</h5>
                        <div class="mb-3">
                            <pre class="bg-light p-2 rounded" style="font-size: 12px;"><?php print_r($_SESSION); ?></pre>
                        </div>
                        
                        <h5>Cookies</h5>
                        <div class="mb-3">
                            <pre class="bg-light p-2 rounded" style="font-size: 12px;"><?php print_r($_COOKIE); ?></pre>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Test Actions</h5>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-info btn-sm" onclick="testSession()">
                                <i class="fas fa-bug me-1"></i> Test Session
                            </button>
                            <button type="button" class="btn btn-success btn-sm ms-2" onclick="setTestSession()">
                                <i class="fas fa-plus me-1"></i> Set Test Session
                            </button>
                            <button type="button" class="btn btn-danger btn-sm ms-2" onclick="clearSession()">
                                <i class="fas fa-trash me-1"></i> Clear Session
                            </button>
                        </div>
                        
                        <div id="test-output" class="mt-3" style="display: none;">
                            <h6>Test Results:</h6>
                            <pre id="test-content" class="bg-light p-2 rounded" style="font-size: 12px;"></pre>
                        </div>
                        
                        <div class="mt-4">
                            <h5>Quick Links</h5>
                            <a href="../student/registration/student_dashboard.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-external-link-alt me-1"></i> Student Dashboard
                            </a>
                            <a href="student_payment_history.php" class="btn btn-warning btn-sm ms-2">
                                <i class="fas fa-history me-1"></i> Payment History
                            </a>
                            <a href="../student/registration/login.php" class="btn btn-success btn-sm ms-2">
                                <i class="fas fa-sign-in-alt me-1"></i> Student Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testSession() {
            fetch('session_test.php?test_session=1')
                .then(response => response.json())
                .then(data => {
                    showTestOutput('Session Test Result:', data);
                })
                .catch(error => {
                    showTestOutput('Session Test Error:', { error: error.message });
                });
        }

        function setTestSession() {
            const formData = new FormData();
            formData.append('set_session', '1');
            formData.append('student_id', 'test_123');
            formData.append('registration_number', 'TEST/2025/001');
            formData.append('student_name', 'Test Student');
            
            fetch('session_test.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showTestOutput('Set Session Result:', data);
                setTimeout(() => location.reload(), 1000);
            })
            .catch(error => {
                showTestOutput('Set Session Error:', { error: error.message });
            });
        }

        function clearSession() {
            const formData = new FormData();
            formData.append('clear_session', '1');
            
            fetch('session_test.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showTestOutput('Clear Session Result:', data);
                setTimeout(() => location.reload(), 1000);
            })
            .catch(error => {
                showTestOutput('Clear Session Error:', { error: error.message });
            });
        }

        function showTestOutput(title, data) {
            const output = document.getElementById('test-output');
            const content = document.getElementById('test-content');
            
            content.innerHTML = title + '\n' + JSON.stringify(data, null, 2);
            output.style.display = 'block';
        }
    </script>
</body>
</html> 