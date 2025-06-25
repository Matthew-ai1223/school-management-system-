<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Check teacher authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug information
echo "<!-- Debug Info: Loading exams for teacher ID: {$_SESSION['teacher_id']} -->\n";

// Get all exams with creator info and statistics
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) as question_count,
          (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id) as attempt_count,
          (SELECT COALESCE(AVG(score), 0) FROM exam_attempts ea WHERE ea.exam_id = e.id AND ea.status = 'completed') as avg_score
          FROM exams e
          WHERE e.created_by = :teacher_id
          ORDER BY e.created_at DESC";

try {
    // Debug query
    error_log("Debug - Exams Query: " . $query);
    error_log("Debug - Teacher ID: {$_SESSION['teacher_id']}");
    
    $stmt = $db->prepare($query);
    $stmt->execute([':teacher_id' => $_SESSION['teacher_id']]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Debug - Found " . count($exams) . " exams");
    error_log("Debug - Exams data: " . print_r($exams, true));

    // Verify each exam ID
    foreach ($exams as $key => $exam) {
        if (!is_numeric($exam['id']) || $exam['id'] <= 0) {
            error_log("Invalid exam ID found: " . print_r($exam, true));
            unset($exams[$key]);
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Error Details: " . print_r($e->errorInfo, true));
    $exams = [];
    $_SESSION['error_message'] = "Database error while loading exams: " . $e->getMessage() . 
                                "\nSQL State: " . $e->getCode();
}

// Debug information
echo "<!-- Debug Info: Total exams loaded: " . count($exams) . " -->\n";

// Store current exam ID in session if provided
if (isset($_GET['exam_id'])) {
    $_SESSION['current_exam_id'] = $_GET['exam_id'];
}

// Get any messages
$message = '';
if (isset($_SESSION['error_message'])) {
    $message = '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .main-content {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
        }
        .table > tbody > tr.active {
            background-color: rgba(52, 152, 219, 0.1);
        }
        .badge.bg-class {
            background-color: #34495e;
        }
        .table > tbody > tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <?php if ($message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Exams</h1>
                    <div class="btn-group">
                        <a href="create-exam.php" class="btn btn-primary">
                            <i class='bx bx-plus'></i> Create New Exam
                        </a>
                        <a href="bulk-upload-questions.php" class="btn btn-success">
                            <i class='bx bx-upload'></i> Bulk Upload Questions
                        </a>
                        <a href="download-questions.php" class="btn btn-info">
                            <i class='bx bx-download'></i> Download Questions
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($exams)): ?>
                            <div class="alert alert-info">
                                <i class='bx bx-info-circle me-2'></i>
                                No exams found. <a href="create-exam.php" class="alert-link">Create your first exam</a>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Class</th>
                                        <th>Questions</th>
                                        <th>Attempts</th>
                                        <th>Avg Score</th>
                                        <th>Duration</th>
                                        <th>Passing Score</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $exam): 
                                        $isActive = isset($_SESSION['current_exam_id']) && $_SESSION['current_exam_id'] == $exam['id'];
                                        // Debug output for exam IDs
                                        error_log("Debug - Processing exam: ID={$exam['id']}, Title=" . ($exam['title'] ?? 'N/A'));
                                    ?>
                                    <tr class="<?php echo $isActive ? 'active' : ''; ?>" 
                                        data-exam-id="<?php echo $exam['id']; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class='bx bx-file me-2'></i>
                                                <!-- Debug comment for exam ID -->
                                                <!-- Debug: Exam ID = <?php echo $exam['id']; ?> -->
                                                <a href="manage-questions.php?exam_id=<?php echo $exam['id']; ?>" 
                                                   class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($exam['title']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-class">
                                                <?php echo htmlspecialchars($exam['class']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $exam['question_count']; ?></td>
                                        <td><?php echo $exam['attempt_count']; ?></td>
                                        <td><?php echo $exam['avg_score'] ? round($exam['avg_score'], 1) . '%' : 'N/A'; ?></td>
                                        <td><?php echo $exam['duration']; ?> mins</td>
                                        <td><?php echo $exam['passing_score']; ?>%</td>
                                        <td>
                                            <span class="badge bg-<?php echo $exam['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $exam['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($exam['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Edit Exam">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                                <a href="manage-questions.php?exam_id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info" 
                                                   title="Manage Questions">
                                                    <i class='bx bx-list-ul'></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteExam(<?php echo $exam['id']; ?>)"
                                                        title="Delete Exam">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent row click when clicking on action buttons
        document.querySelectorAll('.btn-group').forEach(group => {
            group.addEventListener('click', (e) => e.stopPropagation());
        });

        function handleRowClick(event, examId) {
            console.log('Row clicked, exam ID:', examId);
            navigateToManageQuestions(examId);
        }

        function handleManageQuestions(event, examId) {
            event.preventDefault();
            event.stopPropagation();
            console.log('Manage questions clicked, exam ID:', examId);
            navigateToManageQuestions(examId);
        }

        function navigateToManageQuestions(examId) {
            if (!examId) {
                console.error('No exam ID provided');
                return;
            }
            const url = `manage-questions.php?exam_id=${examId}`;
            console.log('Navigating to:', url);
            window.location.href = url;
        }

        function deleteExam(examId) {
            if (confirm('Are you sure you want to delete this exam? This will also delete all questions, attempts, and associated data. This action cannot be undone.')) {
                fetch('delete-exam.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        exam_id: examId
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message before reload
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success';
                        alertDiv.textContent = data.message;
                        document.querySelector('main').insertBefore(alertDiv, document.querySelector('.card'));
                        
                        // Reload after a short delay to show the message
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Show error in a Bootstrap alert
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.textContent = data.message || 'Error deleting exam';
                        document.querySelector('main').insertBefore(alertDiv, document.querySelector('.card'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show error in a Bootstrap alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = 'An error occurred while deleting the exam. Please try again.';
                    document.querySelector('main').insertBefore(alertDiv, document.querySelector('.card'));
                });
            }
        }
    </script>
</body>
</html> 