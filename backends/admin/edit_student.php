<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../utils.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$mysqli = $db->getConnection();
$user = $auth->getCurrentUser();

// Get student ID from URL
$student_id = $_GET['id'] ?? 0;

// Fetch student details
$query = "SELECT * FROM students WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    header('Location: applications.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate input
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $parent_name = trim($_POST['parent_name'] ?? '');
        $parent_phone = trim($_POST['parent_phone'] ?? '');
        $parent_email = trim($_POST['parent_email'] ?? '');
        $status = trim($_POST['status'] ?? '');
        
        if (empty($first_name) || empty($last_name) || empty($parent_name) || empty($parent_phone)) {
            throw new Exception('Please fill in all required fields');
        }
        
        if (!filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        
        if (!in_array($status, ['pending', 'registered', 'rejected'])) {
            throw new Exception('Invalid status selected');
        }
        
        // Update student information
        $query = "UPDATE students SET 
            first_name = ?,
            last_name = ?,
            parent_name = ?,
            parent_phone = ?,
            parent_email = ?,
            status = ?
            WHERE id = ?";
            
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ssssssi', 
            $first_name,
            $last_name,
            $parent_name,
            $parent_phone,
            $parent_email,
            $status,
            $student_id
        );
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Student information updated successfully';
            
            // Refresh student data
            $query = "SELECT * FROM students WHERE id = ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('i', $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
        } else {
            throw new Exception('Failed to update student information');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    // Send JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Redirect for regular form submissions
    if ($response['success']) {
        header('Location: student_details.php?id=' . $student_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Edit Student</h2>
                    <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Details
                    </a>
                </div>

                <?php if (isset($response) && !$response['success']): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($response['message']); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form id="editStudentForm" method="POST" class="needs-validation" novalidate>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="registration_number" class="form-label">Registration Number</label>
                                    <input type="text" class="form-control" id="registration_number" value="<?php echo htmlspecialchars($student['registration_number']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="application_type" class="form-label">Application Type</label>
                                    <input type="text" class="form-control" id="application_type" value="<?php 
                                        // Determine application type based on registration number
                                        $regNumber = $student['registration_number'] ?? '';
                                        $appType = $student['application_type'] ?? '';
                                        
                                        if (!empty($regNumber)) {
                                            if (strpos($regNumber, 'COL') !== false) {
                                                echo 'Ace College';
                                            } elseif (strpos($regNumber, 'KID') !== false) {
                                                echo 'Ace Kiddies';
                                            } else {
                                                echo ucfirst(str_replace('_', ' ', $appType));
                                            }
                                        } else {
                                            echo ucfirst(str_replace('_', ' ', $appType));
                                        }
                                    ?>" readonly>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                                    <div class="invalid-feedback">Please enter the first name</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                                    <div class="invalid-feedback">Please enter the last name</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="parent_name" class="form-label">Parent Name *</label>
                                    <input type="text" class="form-control" id="parent_name" name="parent_name" value="<?php echo htmlspecialchars($student['parent_name']); ?>" required>
                                    <div class="invalid-feedback">Please enter the parent name</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="parent_phone" class="form-label">Parent Phone *</label>
                                    <input type="tel" class="form-control" id="parent_phone" name="parent_phone" value="<?php echo htmlspecialchars($student['parent_phone']); ?>" required>
                                    <div class="invalid-feedback">Please enter the parent phone number</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="parent_email" class="form-label">Parent Email</label>
                                    <input type="email" class="form-control" id="parent_email" name="parent_email" value="<?php echo htmlspecialchars($student['parent_email']); ?>">
                                    <div class="invalid-feedback">Please enter a valid email address</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="pending" <?php echo $student['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="registered" <?php echo $student['status'] === 'registered' ? 'selected' : ''; ?>>Registered</option>
                                        <option value="rejected" <?php echo $student['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a status</div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // AJAX form submission
        document.getElementById('editStudentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                return;
            }
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'student_details.php?id=<?php echo $student['id']; ?>';
                } else {
                    alert(data.message || 'An error occurred while updating student information.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating student information.');
            });
        });
    </script>
</body>
</html> 