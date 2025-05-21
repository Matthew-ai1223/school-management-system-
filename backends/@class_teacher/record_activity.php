<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

// Check if user is logged in and has class teacher role
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'class_teacher') {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get class teacher information
$userId = $_SESSION['user_id'];
$teacherQuery = "SELECT ct.* 
                FROM class_teachers ct
                JOIN users u ON ct.user_id = u.id
                WHERE ct.user_id = ? AND ct.is_active = 1";

$stmt = $conn->prepare($teacherQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Error: You are not assigned as a class teacher. Please contact the administrator.";
    exit;
}

$classTeacher = $result->fetch_assoc();
$classTeacherId = $classTeacher['id'];

// Check if student ID is provided
if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    header('Location: students.php');
    exit;
}

$studentId = $_GET['student_id'];

// Get student information
$studentQuery = "SELECT s.* 
                FROM students s
                WHERE s.id = ?";

$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$studentResult = $stmt->get_result();

if ($studentResult->num_rows === 0) {
    echo "Error: Student not found.";
    exit;
}

$student = $studentResult->fetch_assoc();

// Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $activityType = $_POST['activity_type'] ?? '';
    $activityDescription = $_POST['description'] ?? '';
    $activityDate = $_POST['activity_date'] ?? '';
    
    if (empty($activityType) || empty($activityDescription) || empty($activityDate)) {
        $errorMessage = "All fields are required.";
    } else {
        // Insert activity record
        $insertQuery = "INSERT INTO class_teacher_activities 
                        (class_teacher_id, student_id, activity_type, description, activity_date) 
                        VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iisss", $classTeacherId, $studentId, $activityType, $activityDescription, $activityDate);
        
        if ($stmt->execute()) {
            $successMessage = "Activity recorded successfully!";
        } else {
            $errorMessage = "Error recording activity: " . $conn->error;
        }
    }
}

// Include header/dashboard template
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Record Student Activity</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item active">Record Activity</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <!-- Student Info Card -->
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Student Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <p><strong>Name:</strong> <?php echo ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''); ?></p>
                                    <p><strong>Admission #:</strong> <?php echo $student['registration_number'] ?? 'N/A'; ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Gender:</strong> <?php echo isset($student['gender']) ? ucfirst(strtolower($student['gender'])) : 'N/A'; ?></p>
                                    <p><strong>Date of Birth:</strong> <?php echo isset($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : 'N/A'; ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Class:</strong> <?php echo $student['class'] ?? 'N/A'; ?></p>
                                    <p><strong>Parent:</strong> <?php echo $student['parent_name'] ?? 'N/A'; ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Contact:</strong> <?php echo $student['parent_phone'] ?? 'N/A'; ?></p>
                                    <p><strong>Email:</strong> <?php echo $student['parent_email'] ?? 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                
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

                    <!-- Activity Form Card -->
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Record New Activity</h3>
                        </div>
                        <form method="post" action="">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="activity_type">Activity Type</label>
                                    <select class="form-control" id="activity_type" name="activity_type" required>
                                        <option value="">Select Activity Type</option>
                                        <option value="attendance">Attendance</option>
                                        <option value="behavioral">Behavioral</option>
                                        <option value="academic">Academic</option>
                                        <option value="health">Health</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="activity_date">Activity Date</label>
                                    <input type="date" class="form-control" id="activity_date" name="activity_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Record Activity</button>
                                <a href="students.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activities for this Student</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get recent activities for this student
                            $recentQuery = "SELECT * FROM class_teacher_activities
                                           WHERE student_id = ? AND class_teacher_id = ?
                                           ORDER BY activity_date DESC, created_at DESC
                                           LIMIT 5";
                            
                            $stmt = $conn->prepare($recentQuery);
                            $stmt->bind_param("ii", $studentId, $classTeacherId);
                            $stmt->execute();
                            $recentResult = $stmt->get_result();
                            $recentActivities = [];

                            while ($row = $recentResult->fetch_assoc()) {
                                $recentActivities[] = $row;
                            }
                            ?>

                            <?php if (count($recentActivities) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Recorded</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentActivities as $activity): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></td>
                                            <td><?php echo ucfirst($activity['activity_type']); ?></td>
                                            <td><?php echo $activity['description']; ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p>No recent activities found for this student.</p>
                            <?php endif; ?>

                            <div class="mt-3">
                                <a href="student_activities.php?student_id=<?php echo $studentId; ?>" class="btn btn-info">
                                    View All Activities for this Student
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../admin/include/footer.php'; ?> 