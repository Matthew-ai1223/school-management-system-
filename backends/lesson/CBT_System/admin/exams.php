<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

session_start();

// Check admin authentication
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit();
// }

$db = Database::getInstance()->getConnection();

// Get all exams with creator info and statistics
$query = "SELECT e.*, 
          a.name as created_by_name,
          (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) as question_count,
          (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id) as attempt_count,
          (SELECT AVG(score) FROM exam_attempts ea WHERE ea.exam_id = e.id AND ea.status = 'completed') as avg_score
          FROM exams e
          LEFT JOIN admins a ON e.created_by = a.id
          ORDER BY e.created_at DESC";
$exams = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
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
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
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
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Questions</th>
                                        <th>Attempts</th>
                                        <th>Avg Score</th>
                                        <th>Duration</th>
                                        <th>Passing Score</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
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
                                        <td><?php echo htmlspecialchars($exam['created_by_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($exam['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Edit Exam">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                                <a href="manage-questions.php?exam_id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Manage Questions">
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
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteExam(examId) {
            if (confirm('Are you sure you want to delete this exam? This action cannot be undone.')) {
                fetch('delete-exam.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        exam_id: examId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error deleting exam: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the exam.');
                });
            }
        }
    </script>
</body>
</html> 