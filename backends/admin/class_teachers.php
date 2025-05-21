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

// Process form submissions (add/delete/update class teacher)
$successMessage = '';
$errorMessage = '';

// Assign Class Teacher
if (isset($_POST['assign_teacher'])) {
    $teacherId = $_POST['teacher_id'] ?? '';
    $className = $_POST['class_name'] ?? '';
    $academicYear = $_POST['academic_year'] ?? '';
    
    if (empty($teacherId) || empty($className) || empty($academicYear)) {
        $errorMessage = "All fields are required.";
    } else {
        // Verify that the class exists in the students table
        $classCheckQuery = "SELECT DISTINCT class FROM students WHERE class = ? LIMIT 1";
        $stmt = $conn->prepare($classCheckQuery);
        $stmt->bind_param("s", $className);
        $stmt->execute();
        $classResult = $stmt->get_result();
        
        if ($classResult->num_rows === 0) {
            $errorMessage = "Selected class does not exist. Please select a valid class.";
        } else {
            // Check if this class already has a class teacher
            $checkQuery = "SELECT id FROM class_teachers WHERE class_name = ? AND is_active = 1";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("s", $className);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errorMessage = "This class already has an active class teacher. Please deactivate the current class teacher first.";
            } else {
                // Get teacher user_id
                $userQuery = "SELECT user_id FROM teachers WHERE id = ?";
                $stmt = $conn->prepare($userQuery);
                $stmt->bind_param("i", $teacherId);
                $stmt->execute();
                $userResult = $stmt->get_result();
                
                if ($userResult->num_rows === 0) {
                    $errorMessage = "Teacher not found.";
                } else {
                    $teacherData = $userResult->fetch_assoc();
                    $userId = $teacherData['user_id'];
                    
                    // Insert new class teacher
                    $insertQuery = "INSERT INTO class_teachers (user_id, teacher_id, class_name, academic_year, is_active) VALUES (?, ?, ?, ?, 1)";
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param("iiss", $userId, $teacherId, $className, $academicYear);
                    
                    if ($stmt->execute()) {
                        // Update user role to class_teacher
                        $updateRoleQuery = "UPDATE users SET role = 'class_teacher' WHERE id = ?";
                        $stmt = $conn->prepare($updateRoleQuery);
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        
                        $successMessage = "Class teacher assigned successfully!";
                    } else {
                        $errorMessage = "Error assigning class teacher: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Deactivate Class Teacher
if (isset($_POST['deactivate_teacher'])) {
    $classTeacherId = $_POST['class_teacher_id'] ?? '';
    
    if (empty($classTeacherId)) {
        $errorMessage = "Invalid class teacher selection.";
    } else {
        // Get the user info
        $infoQuery = "SELECT ct.class_name, ct.user_id, ct.teacher_id FROM class_teachers ct WHERE ct.id = ?";
        $stmt = $conn->prepare($infoQuery);
        $stmt->bind_param("i", $classTeacherId);
        $stmt->execute();
        $infoResult = $stmt->get_result();
        
        if ($infoResult->num_rows === 0) {
            $errorMessage = "Class teacher not found.";
        } else {
            $infoData = $infoResult->fetch_assoc();
            $className = $infoData['class_name'];
            $userId = $infoData['user_id'];
            $teacherId = $infoData['teacher_id'];
            
            // Deactivate the class teacher
            $deactivateQuery = "UPDATE class_teachers SET is_active = 0 WHERE id = ?";
            $stmt = $conn->prepare($deactivateQuery);
            $stmt->bind_param("i", $classTeacherId);
            
            if ($stmt->execute()) {
                // Check if user has other active class teacher roles
                $checkRolesQuery = "SELECT id FROM class_teachers WHERE user_id = ? AND is_active = 1";
                $stmt = $conn->prepare($checkRolesQuery);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $rolesResult = $stmt->get_result();
                
                // If no other active class teacher roles, revert user role to teacher
                if ($rolesResult->num_rows === 0) {
                    $updateRoleQuery = "UPDATE users SET role = 'teacher' WHERE id = ?";
                    $stmt = $conn->prepare($updateRoleQuery);
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                }
                
                $successMessage = "Class teacher deactivated successfully!";
            } else {
                $errorMessage = "Error deactivating class teacher: " . $conn->error;
            }
        }
    }
}

// Get all active class teachers
$classTeachersQuery = "SELECT ct.*, t.first_name, t.last_name, t.employee_id, 
                      u.email
                      FROM class_teachers ct
                      JOIN teachers t ON ct.teacher_id = t.id
                      JOIN users u ON ct.user_id = u.id
                      WHERE ct.is_active = 1
                      ORDER BY ct.class_name";

$result = $conn->query($classTeachersQuery);
$classTeachers = [];

while ($row = $result->fetch_assoc()) {
    $classTeachers[] = $row;
}

// Get all available teachers (for dropdown)
$teachersQuery = "SELECT t.id, t.first_name, t.last_name, t.employee_id 
                 FROM teachers t
                 LEFT JOIN class_teachers ct ON t.id = ct.teacher_id AND ct.is_active = 1
                 WHERE ct.id IS NULL
                 ORDER BY t.first_name, t.last_name";

$result = $conn->query($teachersQuery);
$availableTeachers = [];

while ($row = $result->fetch_assoc()) {
    $availableTeachers[] = $row;
}

// Get all classes without a class teacher (for dropdown)
$classesQuery = "SELECT DISTINCT class 
                FROM students s
                WHERE class COLLATE utf8mb4_unicode_ci NOT IN (
                    SELECT class_name COLLATE utf8mb4_unicode_ci FROM class_teachers ct WHERE is_active = 1
                ) OR class IS NULL
                ORDER BY class";

$result = $conn->query($classesQuery);
$availableClasses = [];

while ($row = $result->fetch_assoc()) {
    $availableClasses[] = $row;
}

// Include header/dashboard template
include 'include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Manage Class Teachers</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Class Teachers</li>
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
                            <h3 class="card-title">Assign Class Teacher</h3>
                        </div>
                        <form method="post" action="">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="teacher_id">Teacher</label>
                                            <select class="form-control" id="teacher_id" name="teacher_id" required>
                                                <option value="">Select Teacher</option>
                                                <?php foreach ($availableTeachers as $teacher): ?>
                                                <option value="<?php echo $teacher['id']; ?>">
                                                    <?php echo $teacher['first_name'] . ' ' . $teacher['last_name'] . ' (' . $teacher['employee_id'] . ')'; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="class_name">Class</label>
                                            <select class="form-control" id="class_name" name="class_name" required>
                                                <option value="">Select Class</option>
                                                <?php foreach ($availableClasses as $class): ?>
                                                <option value="<?php echo $class['class']; ?>">
                                                    <?php echo $class['class']; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="academic_year">Academic Year</label>
                                            <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                                   placeholder="e.g. 2024-2025" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="assign_teacher" class="btn btn-primary">Assign Class Teacher</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Current Class Teachers</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <?php if (count($classTeachers) > 0): ?>
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Teacher</th>
                                        <th>Employee ID</th>
                                        <th>Class</th>
                                        <th>Academic Year</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classTeachers as $teacher): ?>
                                    <tr>
                                        <td><?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?></td>
                                        <td><?php echo $teacher['employee_id']; ?></td>
                                        <td><?php echo $teacher['class_name']; ?></td>
                                        <td><?php echo $teacher['academic_year']; ?></td>
                                        <td><?php echo $teacher['email']; ?></td>
                                        <td>
                                            <form method="post" action="" style="display: inline;">
                                                <input type="hidden" name="class_teacher_id" value="<?php echo $teacher['id']; ?>">
                                                <button type="submit" name="deactivate_teacher" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to remove this class teacher assignment?')">
                                                    <i class="fas fa-user-minus"></i> Remove
                                                </button>
                                            </form>
                                            <a href="class_teacher_details.php?id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="callout callout-info m-3">
                                <h5>No Class Teachers Assigned</h5>
                                <p>There are currently no active class teachers. Use the form above to assign teachers to classes.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?> 