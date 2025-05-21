<?php
require_once '../config.php';
require_once '../database.php';
require_once '../auth.php';

// Check if user is logged in and has admin role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Process form submissions
$successMessage = '';
$errorMessage = '';

// Approve teacher registration
if (isset($_POST['approve_teacher'])) {
    $teacherId = $_POST['teacher_id'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    
    if (empty($teacherId) || empty($userId)) {
        $errorMessage = "Invalid teacher selection.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update user status to active
            $updateUser = "UPDATE users SET status = 'active' WHERE id = ?";
            $stmt = $conn->prepare($updateUser);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $conn->commit();
            $successMessage = "Teacher registration approved successfully!";
            
            // Here you could send an email notification to the teacher
            
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    }
}

// Reject teacher registration
if (isset($_POST['reject_teacher'])) {
    $teacherId = $_POST['teacher_id'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $rejectionReason = $_POST['rejection_reason'] ?? '';
    
    if (empty($teacherId) || empty($userId)) {
        $errorMessage = "Invalid teacher selection.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete teacher record
            $deleteTeacher = "DELETE FROM teachers WHERE id = ?";
            $stmt = $conn->prepare($deleteTeacher);
            $stmt->bind_param("i", $teacherId);
            $stmt->execute();
            
            // Delete user record
            $deleteUser = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($deleteUser);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $conn->commit();
            $successMessage = "Teacher registration rejected successfully.";
            
            // Here you could send an email notification to the teacher with the rejection reason
            
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    }
}

// Get all pending teacher registrations
$pendingTeachersQuery = "SELECT t.*, u.email, u.username, u.created_at
                       FROM teachers t
                       JOIN users u ON t.user_id = u.id
                       WHERE u.role = 'teacher' AND u.status = 'pending'
                       ORDER BY u.created_at DESC";

$result = $conn->query($pendingTeachersQuery);
$pendingTeachers = [];

while ($row = $result->fetch_assoc()) {
    $pendingTeachers[] = $row;
}

// Include header template
include 'include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Pending Teacher Registrations</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="manage_teachers.php">Teachers</a></li>
                        <li class="breadcrumb-item active">Pending Registrations</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo $successMessage; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <?php echo $errorMessage; ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Pending Registrations</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($pendingTeachers) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Employee ID</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Qualification</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingTeachers as $teacher): ?>
                                        <tr>
                                            <td><?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?></td>
                                            <td><?php echo $teacher['employee_id']; ?></td>
                                            <td><?php echo $teacher['email']; ?></td>
                                            <td><?php echo $teacher['phone']; ?></td>
                                            <td><?php echo $teacher['qualification']; ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($teacher['created_at'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $teacher['id']; ?>">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $teacher['id']; ?>">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $teacher['id']; ?>">
                                                    <i class="fas fa-eye"></i> Details
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Approval Modal -->
                                        <div class="modal fade" id="approveModal<?php echo $teacher['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Approve Registration</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to approve the registration for <strong><?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?></strong>?</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="post" action="">
                                                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $teacher['user_id']; ?>">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="approve_teacher" class="btn btn-success">Approve</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Rejection Modal -->
                                        <div class="modal fade" id="rejectModal<?php echo $teacher['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Registration</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="post" action="">
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to reject the registration for <strong><?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?></strong>?</p>
                                                            <div class="form-group">
                                                                <label for="rejection_reason">Reason for Rejection (Optional)</label>
                                                                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3"></textarea>
                                                            </div>
                                                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $teacher['user_id']; ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="reject_teacher" class="btn btn-danger">Reject</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Details Modal -->
                                        <div class="modal fade" id="detailsModal<?php echo $teacher['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Teacher Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Name:</strong> <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?></p>
                                                                <p><strong>Employee ID:</strong> <?php echo $teacher['employee_id']; ?></p>
                                                                <p><strong>Email:</strong> <?php echo $teacher['email']; ?></p>
                                                                <p><strong>Phone:</strong> <?php echo $teacher['phone']; ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Username:</strong> <?php echo $teacher['username']; ?></p>
                                                                <p><strong>Qualification:</strong> <?php echo $teacher['qualification']; ?></p>
                                                                <p><strong>Joining Date:</strong> <?php echo !empty($teacher['joining_date']) ? date('M d, Y', strtotime($teacher['joining_date'])) : 'Not specified'; ?></p>
                                                                <p><strong>Registered:</strong> <?php echo date('M d, Y H:i', strtotime($teacher['created_at'])); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="alert alert-info mt-3">
                                                            <p class="mb-0"><i class="fas fa-info-circle"></i> A temporary password will be generated when the teacher's account is approved.</p>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <p>There are no pending teacher registrations at this time.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Teacher Registration Information</h3>
                        </div>
                        <div class="card-body">
                            <p>Teachers can register themselves through the public registration page at:</p>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" value="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/backends/teacher_registration.php" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyRegistrationUrl()">Copy URL</button>
                            </div>
                            <p>You can share this link with teachers who want to join the system.</p>
                            
                            <div class="mt-3">
                                <a href="manage_teachers.php" class="btn btn-primary">
                                    <i class="fas fa-users"></i> Manage Active Teachers
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyRegistrationUrl() {
    var copyText = document.querySelector(".input-group input");
    copyText.select();
    document.execCommand("copy");
    alert("Registration URL copied to clipboard!");
}
</script>

<?php include 'include/footer.php'; ?> 