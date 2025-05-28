<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// // Check if user is logged in and is an admin
// if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
//     header('Location: login.php');
//     exit();
// }

$db = Database::getInstance()->getConnection();
$message = '';
$exam = null;

// Get exam ID from URL
$exam_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$exam_id) {
    header('Location: manage-exams.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $db->beginTransaction();

        $updates = [];
        $params = [':exam_id' => $exam_id];

        // Basic exam details
        if (!empty($_POST['title'])) {
            $updates[] = "title = :title";
            $params[':title'] = $_POST['title'];
        }

        if (isset($_POST['duration'])) {
            $updates[] = "duration = :duration";
            $params[':duration'] = (int)$_POST['duration'];
        }

        if (isset($_POST['passing_score'])) {
            $updates[] = "passing_score = :passing_score";
            $params[':passing_score'] = (int)$_POST['passing_score'];
        }

        if (isset($_POST['max_attempts'])) {
            $updates[] = "max_attempts = :max_attempts";
            $params[':max_attempts'] = (int)$_POST['max_attempts'];
        }

        // Exam settings
        $updates[] = "is_active = :is_active";
        $params[':is_active'] = isset($_POST['is_active']) ? 1 : 0;

        // Update exam
        if (!empty($updates)) {
            $sql = "UPDATE exams SET " . implode(", ", $updates) . " WHERE id = :exam_id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        // Commit transaction
        $db->commit();
        $message = '<div class="alert alert-success">Exam updated successfully!</div>';

    } catch (Exception $e) {
        $db->rollBack();
        $message = '<div class="alert alert-danger">Error updating exam: ' . $e->getMessage() . '</div>';
    }
}

// Get exam details
$stmt = $db->prepare("
    SELECT e.id,
           e.title,
           e.duration,
           e.passing_score,
           e.is_active,
           e.max_attempts,
           COUNT(DISTINCT q.id) as total_questions,
           COUNT(DISTINCT ea.id) as total_attempts
    FROM exams e
    LEFT JOIN questions q ON e.id = q.exam_id
    LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
    WHERE e.id = :exam_id
    GROUP BY e.id
");
$stmt->execute([':exam_id' => $exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header('Location: manage-exams.php');
    exit();
}

// Initialize default values if not set
$exam = array_merge([
    'title' => '',
    'duration' => 60,
    'passing_score' => 50,
    'max_attempts' => 1,
    'is_active' => 0,
    'total_questions' => 0,
    'total_attempts' => 0
], $exam);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #f3f4f6;
            --accent-color: #3b82f6;
            --success-color: #059669;
            --danger-color: #dc2626;
        }

        body {
            background-color: #f8fafc;
            min-height: 100vh;
        }

        .exam-edit-container {
            max-width: 1000px;
            margin: 40px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-right: 10px;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .stats-card {
            background: var(--secondary-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stats-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background-color: #1e40af;
        }

        .form-label {
            font-weight: 500;
        }

        .settings-section {
            background: var(--secondary-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .settings-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="exam-edit-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Exam</h2>
                <a href="exams.php" class="btn btn-outline-primary">
                    <i class='bx bx-arrow-back'></i> Back to Exams
                </a>
            </div>

            <?php echo $message; ?>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-value"><?php echo $exam['total_questions']; ?></div>
                        <div class="stats-label">Total Questions</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-value"><?php echo $exam['total_attempts']; ?></div>
                        <div class="stats-label">Total Attempts</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-value"><?php echo $exam['passing_score']; ?>%</div>
                        <div class="stats-label">Passing Score</div>
                    </div>
                </div>
            </div>

            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="title" class="form-label">Exam Title</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="duration" class="form-label">Duration (minutes)</label>
                        <input type="number" class="form-control" id="duration" name="duration" 
                               value="<?php echo $exam['duration']; ?>" required min="1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="passing_score" class="form-label">Passing Score (%)</label>
                        <input type="number" class="form-control" id="passing_score" name="passing_score" 
                               value="<?php echo $exam['passing_score']; ?>" required min="0" max="100">
                    </div>
                </div>

                <div class="settings-section">
                    <div class="settings-title">Exam Settings</div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                       <?php echo $exam['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Activate Exam
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="max_attempts" class="form-label">Maximum Attempts</label>
                            <input type="number" class="form-control" id="max_attempts" name="max_attempts" 
                                   value="<?php echo $exam['max_attempts']; ?>" required min="1">
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confirm exam activation/deactivation
        document.getElementById('is_active').addEventListener('change', function() {
            if (!this.checked) {
                if (!confirm('Are you sure you want to deactivate this exam? Students will not be able to take it.')) {
                    this.checked = true;
                }
            }
        });
    </script>
</body>
</html> 