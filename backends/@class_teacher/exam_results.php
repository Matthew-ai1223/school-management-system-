<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once 'class_teacher_auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get class teacher information
$userId = $_SESSION['user_id'];
$teacherName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$className = $_SESSION['class_name'] ?? '';

// Handle result visibility toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_result'])) {
    $examId = $_POST['exam_id'];
    $showResult = $_POST['show_result'];
    
    $updateQuery = "UPDATE cbt_exams SET show_result = ? WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ii", $showResult, $examId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Result visibility updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating result visibility.";
    }
    
    // Redirect to prevent form resubmission
    header('Location: exam_results.php');
    exit;
}

// Get all exams for this class
$examsQuery = "SELECT e.*, 
               (SELECT COUNT(*) FROM cbt_student_exams WHERE exam_id = e.id) as total_attempts,
               (SELECT COUNT(*) FROM cbt_student_exams WHERE exam_id = e.id AND status = 'Completed') as completed_attempts
               FROM cbt_exams e 
               WHERE e.class = ?
               ORDER BY e.created_at DESC";

$stmt = $conn->prepare($examsQuery);
$stmt->bind_param("s", $className);
$stmt->execute();
$examsResult = $stmt->get_result();
$exams = [];

while ($row = $examsResult->fetch_assoc()) {
    $exams[] = $row;
}

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-poll mr-2"></i>Exam Results Management
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Exam Results</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-gradient-primary">
                    <h3 class="card-title">
                        <i class="fas fa-list mr-2"></i>Exam List
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Title</th>
                                    <th>Date Created</th>
                                    <th>Total Attempts</th>
                                    <th>Completed</th>
                                    <th>Result Visibility</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($exam['created_at'])); ?></td>
                                    <td><?php echo $exam['total_attempts']; ?></td>
                                    <td><?php echo $exam['completed_attempts']; ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                            <input type="hidden" name="show_result" value="<?php echo $exam['show_result'] ? '0' : '1'; ?>">
                                            <button type="submit" name="toggle_result" class="btn btn-sm <?php echo $exam['show_result'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                <?php echo $exam['show_result'] ? 'Visible' : 'Hidden'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="../cbt/view_exam_results.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View Results
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../admin/include/footer.php'; ?> 