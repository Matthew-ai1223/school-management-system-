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

// Add new teacher
if (isset($_POST['add_teacher'])) {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $employeeId = $_POST['employee_id'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $joiningDate = $_POST['joining_date'] ?? '';
    $address = $_POST['address'] ?? '';
    
    if (empty($firstName) || empty($lastName) || empty($employeeId) || empty($email)) {
        $errorMessage = "Required fields cannot be empty.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Check if email already exists in users table
            $checkEmail = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($checkEmail);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Email already exists in the system.");
            }
            
            // Check if employee ID already exists
            $checkEmployeeId = "SELECT id FROM teachers WHERE employee_id = ?";
            $stmt = $conn->prepare($checkEmployeeId);
            $stmt->bind_param("s", $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Employee ID already exists.");
            }
            
            // Create user account for teacher
            $username = strtolower($firstName . '.' . $lastName);
            $baseUsername = $username;
            $counter = 1;
            
            // Make sure username is unique
            while (true) {
                $checkUsername = "SELECT id FROM users WHERE username = ?";
                $stmt = $conn->prepare($checkUsername);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    break;
                }
                
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            // Generate a temporary password
            $tempPassword = substr(md5(uniqid(rand(), true)), 0, 8);
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Insert user
            $insertUser = "INSERT INTO users (username, password, email, role, created_at) VALUES (?, ?, ?, 'teacher', NOW())";
            $stmt = $conn->prepare($insertUser);
            $stmt->bind_param("sss", $username, $hashedPassword, $email);
            $stmt->execute();
            $userId = $conn->insert_id;
            
            // Insert teacher
            $insertTeacher = "INSERT INTO teachers (user_id, first_name, last_name, employee_id, joining_date, qualification, phone, address) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertTeacher);
            $stmt->bind_param("isssssss", $userId, $firstName, $lastName, $employeeId, $joiningDate, $qualification, $phone, $address);
            
            if ($stmt->execute()) {
                $conn->commit();
                $successMessage = "Teacher added successfully! Temporary password: $tempPassword";
            } else {
                throw new Exception("Error adding teacher: " . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    }
}

// Delete teacher
if (isset($_POST['delete_teacher'])) {
    $teacherId = $_POST['teacher_id'] ?? 0;
    
    if (empty($teacherId)) {
        $errorMessage = "Invalid teacher selection.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Check if teacher is a class teacher
            $checkClassTeacher = "SELECT id FROM class_teachers WHERE teacher_id = ? AND is_active = 1";
            $stmt = $conn->prepare($checkClassTeacher);
            $stmt->bind_param("i", $teacherId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Cannot delete this teacher as they are assigned as a class teacher. Please remove them from class teacher role first.");
            }
            
            // Get user ID
            $getUserId = "SELECT user_id FROM teachers WHERE id = ?";
            $stmt = $conn->prepare($getUserId);
            $stmt->bind_param("i", $teacherId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                throw new Exception("Teacher not found.");
            }
            
            $userData = $result->fetch_assoc();
            $userId = $userData['user_id'];
            
            // Delete teacher
            $deleteTeacher = "DELETE FROM teachers WHERE id = ?";
            $stmt = $conn->prepare($deleteTeacher);
            $stmt->bind_param("i", $teacherId);
            $stmt->execute();
            
            // Delete user
            $deleteUser = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($deleteUser);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $conn->commit();
            $successMessage = "Teacher deleted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    }
}

// Get all teachers
$teachersQuery = "SELECT t.*, u.email, u.username
                 FROM teachers t
                 JOIN users u ON t.user_id = u.id
                 ORDER BY t.first_name, t.last_name";

$result = $conn->query($teachersQuery);
$teachers = [];

while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}

// Include header template
include 'include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Manage Teachers</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Teachers</li>
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
                            <h3 class="card-title">Add New Teacher</h3>
                        </div>
                        <form method="post" action="">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="first_name">First Name*</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="last_name">Last Name*</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="employee_id">Employee ID*</label>
                                            <input type="text" class="form-control" id="employee_id" name="employee_id" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="email">Email*</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="phone">Phone</label>
                                            <input type="text" class="form-control" id="phone" name="phone">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="joining_date">Joining Date</label>
                                            <input type="date" class="form-control" id="joining_date" name="joining_date">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="qualification">Qualification</label>
                                            <input type="text" class="form-control" id="qualification" name="qualification">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="address">Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="add_teacher" class="btn btn-primary">Add Teacher</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Teachers List</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <?php if (count($teachers) > 0): ?>
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Qualification</th>
                                        <th>Joining Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><?php echo $teacher['employee_id']; ?></td>
                                        <td><?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?></td>
                                        <td><?php echo $teacher['email']; ?></td>
                                        <td><?php echo $teacher['phone']; ?></td>
                                        <td><?php echo $teacher['qualification']; ?></td>
                                        <td><?php echo !empty($teacher['joining_date']) ? date('M d, Y', strtotime($teacher['joining_date'])) : ''; ?></td>
                                        <td>
                                            <a href="edit_teacher.php?id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="post" action="" style="display: inline;">
                                                <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                <button type="submit" name="delete_teacher" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this teacher?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="callout callout-info m-3">
                                <h5>No Teachers Found</h5>
                                <p>There are currently no teachers in the system. Use the form above to add teachers.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">Next Steps</h3>
                        </div>
                        <div class="card-body">
                            <p>After adding teachers, you can:</p>
                            <ul>
                                <li>Assign them as class teachers from the <a href="class_teachers.php">Class Teachers</a> page</li>
                                <li>Assign them to teach specific subjects</li>
                                <li>Generate timetables</li>
                            </ul>
                            <div class="mt-3">
                                <a href="class_teachers.php" class="btn btn-primary">
                                    <i class="fas fa-user-graduate"></i> Manage Class Teachers
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?> 