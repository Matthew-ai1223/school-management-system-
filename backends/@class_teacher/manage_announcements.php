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

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND class_teacher_id = ?");
    $stmt->bind_param("ii", $id, $classTeacherId);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = "announcement_deleted";
    } else {
        $error = "delete_failed";
    }
    // Redirect to remove the action from URL
    header("Location: manage_announcements.php" . ($success ? "?success=$success" : "") . ($error ? "?error=$error" : ""));
    exit;
}

// Get all announcements for this class teacher
$stmt = $conn->prepare("
    SELECT a.*, 
           IFNULL(s.first_name, '') AS student_first_name, 
           IFNULL(s.last_name, '') AS student_last_name
    FROM announcements a
    LEFT JOIN students s ON a.student_id = s.id
    WHERE a.class_teacher_id = ?
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $classTeacherId);
$stmt->execute();
$result = $stmt->get_result();
$announcements = [];

while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Manage Announcements</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Announcements</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($success === 'announcement_added'): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-check"></i> Success!</h5>
                    Announcement has been added successfully.
                </div>
            <?php endif; ?>
            
            <?php if ($success === 'announcement_deleted'): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-check"></i> Success!</h5>
                    Announcement has been deleted successfully.
                </div>
            <?php endif; ?>
            
            <?php if ($error === 'delete_failed'): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                    Failed to delete announcement.
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">All Announcements</h3>
                            <div class="card-tools">
                                <a href="add_announcement.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Add New Announcement
                                </a>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <?php if (empty($announcements)): ?>
                                <div class="alert alert-info">
                                    No announcements have been created yet.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th style="width: 15%">Title</th>
                                                <th>Content</th>
                                                <th style="width: 10%">Type</th>
                                                <th style="width: 15%">Target Student</th>
                                                <th style="width: 12%">Date</th>
                                                <th style="width: 15%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($announcements as $announcement): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $content = $announcement['content'];
                                                        echo htmlspecialchars(strlen($content) > 100 ? substr($content, 0, 100) . '...' : $content);
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $typeClass = 'badge-info';
                                                        switch ($announcement['type']) {
                                                            case 'academic': $typeClass = 'badge-primary'; break;
                                                            case 'exam': $typeClass = 'badge-warning'; break;
                                                            case 'event': $typeClass = 'badge-success'; break;
                                                            case 'payment': $typeClass = 'badge-danger'; break;
                                                            case 'important': $typeClass = 'badge-dark'; break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $typeClass; ?>">
                                                            <?php echo ucfirst($announcement['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($announcement['student_id'] === NULL): ?>
                                                            <span class="badge badge-info">All Students</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">
                                                                <?php echo htmlspecialchars($announcement['student_first_name'] . ' ' . $announcement['student_last_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
                                                    <td>
                                                        <a href="edit_announcement.php?id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="manage_announcements.php?delete=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?');">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../admin/include/footer.php'; ?> 