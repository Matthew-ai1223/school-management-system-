<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$user = $auth->getCurrentUser();

// Add after Database::getInstance();
$mysqli = $db->getConnection();

// Get exam ID
$exam_id = $_GET['id'] ?? '';

if (!$exam_id) {
    header('Location: exams.php');
    exit;
}

// Get exam details
$query = "SELECT e.*, s.first_name, s.last_name, s.registration_number 
          FROM exam_results e 
          JOIN students s ON e.student_id = s.id 
          WHERE e.id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: exams.php');
    exit;
}

$exam = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'include/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Edit Exam Result</h2>
                    <a href="exam_details.php?id=<?php echo $exam['id']; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Details
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form id="editExamForm">
                            <input type="hidden" name="id" value="<?php echo $exam['id']; ?>">
                            
                            <!-- Student Information -->
                            <div class="mb-4">
                                <h5>Student Information</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Registration Number</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($exam['registration_number']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Student Name</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($exam['first_name'] . ' ' . $exam['last_name']); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Exam Information -->
                            <div class="mb-4">
                                <h5>Exam Information</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="exam_type" class="form-label">Exam Type</label>
                                            <select class="form-select" id="exam_type" name="exam_type" required>
                                                <option value="entrance" <?php echo $exam['exam_type'] === 'entrance' ? 'selected' : ''; ?>>Entrance Exam</option>
                                                <option value="midterm" <?php echo $exam['exam_type'] === 'midterm' ? 'selected' : ''; ?>>Midterm Exam</option>
                                                <option value="final" <?php echo $exam['exam_type'] === 'final' ? 'selected' : ''; ?>>Final Exam</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="exam_date" class="form-label">Exam Date</label>
                                            <input type="datetime-local" class="form-control" id="exam_date" name="exam_date" value="<?php echo date('Y-m-d\TH:i', strtotime($exam['exam_date'])); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="score" class="form-label">Score</label>
                                            <input type="number" class="form-control" id="score" name="score" min="0" value="<?php echo $exam['score']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="total_score" class="form-label">Total Score</label>
                                            <input type="number" class="form-control" id="total_score" name="total_score" min="0" value="<?php echo $exam['total_score']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="pending" <?php echo $exam['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="passed" <?php echo $exam['status'] === 'passed' ? 'selected' : ''; ?>>Passed</option>
                                                <option value="failed" <?php echo $exam['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3"><?php echo htmlspecialchars($exam['remarks']); ?></textarea>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('editExamForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('update_exam.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Exam result updated successfully!');
                    window.location.href = 'exam_details.php?id=<?php echo $exam['id']; ?>';
                } else {
                    alert(data.message || 'An error occurred while updating exam result.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating exam result.');
            });
        });

        // Validate score
        document.getElementById('score').addEventListener('input', validateScore);
        document.getElementById('total_score').addEventListener('input', validateScore);

        function validateScore() {
            const score = document.getElementById('score');
            const totalScore = document.getElementById('total_score');
            
            if (parseInt(score.value) > parseInt(totalScore.value)) {
                score.setCustomValidity('Score cannot be greater than total score');
            } else {
                score.setCustomValidity('');
            }
        }
    </script>
</body>
</html> 