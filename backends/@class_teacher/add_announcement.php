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

$userId = $_SESSION['user_id'] ?? 0;
// Get the actual class_teacher_id from the class_teachers table using the user_id
$stmt = $conn->prepare("SELECT id FROM class_teachers WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User is not properly linked to a class teacher record
    die("Error: Your account is not properly linked to a class teacher record. Please contact the administrator.");
}

$classTeacherRow = $result->fetch_assoc();
$classTeacherId = $classTeacherRow['id'];

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$errors = [];
$success = '';

// Get student information if student ID is provided
$studentName = '';
if ($studentId > 0) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($student = $result->fetch_assoc()) {
        $studentName = $student['first_name'] . ' ' . $student['last_name'];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $target = isset($_POST['target']) ? $_POST['target'] : 'all';
    
    // Validate input
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    
    if (empty($content)) {
        $errors[] = "Content is required.";
    }
    
    if (empty($type)) {
        $errors[] = "Announcement type is required.";
    }
    
    // If no errors, insert announcement
    if (empty($errors)) {
        $targetStudentId = ($target === 'specific' && $studentId > 0) ? $studentId : NULL;
        
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, type, class_teacher_id, student_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssii", $title, $content, $type, $classTeacherId, $targetStudentId);
        
        if ($stmt->execute()) {
            $success = "Announcement added successfully!";
            
            // Redirect based on target
            if ($studentId > 0 && $target === 'specific') {
                header("Location: student_details.php?id=$studentId&success=announcement_added");
                exit;
            } else {
                header("Location: manage_announcements.php?success=announcement_added");
                exit;
            }
        } else {
            $errors[] = "Failed to add announcement: " . $conn->error;
        }
    }
}

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Add Announcement</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <?php if ($studentId > 0): ?>
                            <li class="breadcrumb-item"><a href="student_details.php?id=<?php echo $studentId; ?>">Student Details</a></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="manage_announcements.php">Announcements</a></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active">Add Announcement</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">New Announcement</h3>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="card-body">
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo $error; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($success)): ?>
                                    <div class="alert alert-success">
                                        <?php echo $success; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label for="title">Announcement Title</label>
                                    <input type="text" class="form-control" id="title" name="title" placeholder="Enter announcement title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="type">Announcement Type</label>
                                    <select class="form-control" id="type" name="type" required>
                                        <option value="">Select Type</option>
                                        <option value="general" <?php echo (isset($_POST['type']) && $_POST['type'] == 'general') ? 'selected' : ''; ?>>General</option>
                                        <option value="academic" <?php echo (isset($_POST['type']) && $_POST['type'] == 'academic') ? 'selected' : ''; ?>>Academic</option>
                                        <option value="exam" <?php echo (isset($_POST['type']) && $_POST['type'] == 'exam') ? 'selected' : ''; ?>>Examination</option>
                                        <option value="event" <?php echo (isset($_POST['type']) && $_POST['type'] == 'event') ? 'selected' : ''; ?>>Event</option>
                                        <option value="payment" <?php echo (isset($_POST['type']) && $_POST['type'] == 'payment') ? 'selected' : ''; ?>>Payment</option>
                                        <option value="important" <?php echo (isset($_POST['type']) && $_POST['type'] == 'important') ? 'selected' : ''; ?>>Important</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="content">Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="5" placeholder="Enter announcement content" required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Target</label>
                                    <div class="custom-control custom-radio">
                                        <input class="custom-control-input" type="radio" id="target_all" name="target" value="all" <?php echo (!isset($_POST['target']) || $_POST['target'] == 'all') ? 'checked' : ''; ?>>
                                        <label for="target_all" class="custom-control-label">All Students</label>
                                    </div>
                                    <?php if ($studentId > 0): ?>
                                        <div class="custom-control custom-radio">
                                            <input class="custom-control-input" type="radio" id="target_specific" name="target" value="specific" <?php echo (isset($_POST['target']) && $_POST['target'] == 'specific') ? 'checked' : ''; ?>>
                                            <label for="target_specific" class="custom-control-label">Only for <?php echo htmlspecialchars($studentName); ?></label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Add Announcement</button>
                                <?php if ($studentId > 0): ?>
                                    <a href="student_details.php?id=<?php echo $studentId; ?>" class="btn btn-default">Cancel</a>
                                <?php else: ?>
                                    <a href="manage_announcements.php" class="btn btn-default">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../admin/include/footer.php'; ?> 