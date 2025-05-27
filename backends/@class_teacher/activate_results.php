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

// Check if show_result column exists and create it if it doesn't
$checkColumn = $conn->query("SHOW COLUMNS FROM cbt_exam_attempts LIKE 'show_result'");
if ($checkColumn->num_rows === 0) {
    $conn->query("ALTER TABLE cbt_exam_attempts ADD COLUMN show_result TINYINT(1) DEFAULT 0 AFTER status");
}

// Get class teacher information
$userId = $_SESSION['user_id'];
$className = $_SESSION['class_name'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_results'])) {
    $exam_ids = $_POST['exam_ids'] ?? [];
    
    if (!empty($exam_ids)) {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Update show_result status for selected exams
            $stmt = $conn->prepare("UPDATE cbt_exam_attempts SET show_result = 1 WHERE exam_id = ? AND student_id IN (SELECT id FROM students WHERE class = ?)");
            
            foreach ($exam_ids as $exam_id) {
                $stmt->bind_param("is", $exam_id, $className);
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Selected exam results have been activated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error activating exam results: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Please select at least one exam to activate.";
    }
    
    header("Location: activate_results.php");
    exit();
}

// Get pending exam results for the class
$query = "SELECT DISTINCT 
            e.id,
            e.title,
            e.subject,
            COUNT(DISTINCT ea.student_id) as total_attempts,
            COUNT(DISTINCT CASE WHEN COALESCE(ea.show_result, 0) = 0 THEN ea.student_id END) as pending_results
          FROM cbt_exams e
          JOIN cbt_exam_attempts ea ON e.id = ea.exam_id
          JOIN students s ON ea.student_id = s.id
          WHERE s.class = ? AND ea.status = 'completed'
          GROUP BY e.id, e.title, e.subject
          HAVING pending_results > 0
          ORDER BY e.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $className);
$stmt->execute();
$results = $stmt->get_result();

// Include header
include '../admin/include/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-check-circle mr-2"></i>Activate Exam Results
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Activate Results</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-gradient-primary text-white">
                    <h3 class="card-title">Pending Exam Results - <?php echo htmlspecialchars($className); ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($results->num_rows > 0): ?>
                        <form action="activate_results.php" method="POST">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="50px">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="selectAll">
                                                    <label class="custom-control-label" for="selectAll"></label>
                                                </div>
                                            </th>
                                            <th>Subject</th>
                                            <th>Exam Title</th>
                                            <th>Total Attempts</th>
                                            <th>Pending Results</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $results->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input exam-checkbox" 
                                                               id="exam_<?php echo $row['id']; ?>" 
                                                               name="exam_ids[]" 
                                                               value="<?php echo $row['id']; ?>">
                                                        <label class="custom-control-label" for="exam_<?php echo $row['id']; ?>"></label>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                <td><?php echo $row['total_attempts']; ?></td>
                                                <td>
                                                    <span class="badge badge-warning">
                                                        <?php echo $row['pending_results']; ?> pending
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <button type="submit" name="activate_results" class="btn btn-primary">
                                    <i class="fas fa-check-circle mr-2"></i>Activate Selected Results
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>No pending exam results found for <?php echo htmlspecialchars($className); ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle "Select All" checkbox
    $('#selectAll').change(function() {
        $('.exam-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // Update "Select All" when individual checkboxes change
    $('.exam-checkbox').change(function() {
        if ($('.exam-checkbox:checked').length === $('.exam-checkbox').length) {
            $('#selectAll').prop('checked', true);
        } else {
            $('#selectAll').prop('checked', false);
        }
    });
});
</script>

<?php include '../admin/include/footer.php'; ?> 