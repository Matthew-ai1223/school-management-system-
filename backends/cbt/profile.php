<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    $message = '';

    // Get student details
    $stmt = $db->prepare("SELECT * FROM students WHERE id = :student_id");
    if (!$stmt->execute([':student_id' => $_SESSION['student_id']])) {
        error_log("Error fetching student details: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Error loading profile");
    }
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        error_log("Student not found: " . $_SESSION['student_id']);
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // Get student's exam history
    $exam_history_query = "
        SELECT 
            ea.id as attempt_id,
            ea.exam_id,
            ea.score,
            ea.start_time,
            e.title as exam_title,
            e.passing_score,
            e.duration as total_questions
        FROM exam_attempts ea 
        JOIN exams e ON ea.exam_id = e.id 
        WHERE ea.student_id = :student_id
        ORDER BY ea.start_time DESC";

    $stmt = $db->prepare($exam_history_query);
    if (!$stmt->execute([':student_id' => $_SESSION['student_id']])) {
        error_log("Error fetching exam history: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Error loading exam history");
    }
    $exam_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get detailed answers for each attempt
    function getAttemptAnswers($db, $attempt_id) {
        // First get the exam_id for this attempt
        $attempt_query = "SELECT exam_id FROM exam_attempts WHERE id = :attempt_id";
        $stmt = $db->prepare($attempt_query);
        if (!$stmt->execute([':attempt_id' => $attempt_id])) {
            error_log("Error fetching attempt details: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Error loading attempt details");
        }
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attempt) {
            error_log("No attempt found with ID: " . $attempt_id);
            return [];
        }

        // Get all questions for this exam with student responses (if any)
        $answers_query = "
            SELECT 
                q.id,
                q.question_text,
                q.correct_answer,
                q.explanation,
                sr.selected_answer
            FROM questions q
            LEFT JOIN student_responses sr ON sr.question_id = q.id AND sr.attempt_id = :attempt_id
            WHERE q.exam_id = (SELECT exam_id FROM exam_attempts WHERE id = :attempt_id2)
            ORDER BY q.id";
        
        $stmt = $db->prepare($answers_query);
        if (!$stmt->execute([
            ':attempt_id' => $attempt_id,
            ':attempt_id2' => $attempt_id
        ])) {
            error_log("Error fetching questions and answers: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Error loading questions and answers");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $updates = [];
            $params = [':student_id' => $_SESSION['student_id']];

            // Only update fields that are provided and not empty
            if (!empty($_POST['first_name'])) {
                $updates[] = "first_name = :first_name";
                $params[':first_name'] = trim($_POST['first_name']);
            }

            if (!empty($_POST['last_name'])) {
                $updates[] = "last_name = :last_name";
                $params[':last_name'] = trim($_POST['last_name']);
            }

            if (!empty($_POST['phone'])) {
                $updates[] = "phone = :phone";
                $params[':phone'] = trim($_POST['phone']);
            }

            if (!empty($_POST['class'])) {
                $updates[] = "class = :class";
                $params[':class'] = trim($_POST['class']);
            }

            // Only process password if both fields are filled and match
            if (!empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
                if ($_POST['new_password'] === $_POST['confirm_password']) {
                    $updates[] = "password = :password";
                    $params[':password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                } else {
                    throw new Exception("New passwords do not match");
                }
            }

            if (!empty($updates)) {
                $sql = "UPDATE students SET " . implode(", ", $updates) . " WHERE id = :student_id";
                $stmt = $db->prepare($sql);
                if (!$stmt->execute($params)) {
                    error_log("Error updating profile: " . print_r($stmt->errorInfo(), true));
                    throw new Exception("Error updating profile");
                }
                
                $message = '<div class="alert alert-success">Profile updated successfully!</div>';
                
                // Refresh student data
                $stmt = $db->prepare("SELECT * FROM students WHERE id = :student_id");
                if (!$stmt->execute([':student_id' => $_SESSION['student_id']])) {
                    error_log("Error refreshing student data: " . print_r($stmt->errorInfo(), true));
                    throw new Exception("Error refreshing profile data");
                }
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Error updating profile: ' . $e->getMessage() . '</div>';
        }
    }
} catch (PDOException $e) {
    error_log("Database error in profile.php: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
    $message = '<div class="alert alert-danger">A database error occurred. Please try again later.</div>';
} catch (Exception $e) {
    error_log("General error in profile.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $message = '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1a73e8;
            --secondary-blue: #4285f4;
            --light-blue: #e8f0fe;
            --hover-blue: #1557b0;
            --accent-blue: #8ab4f8;
            --deep-blue: #174ea6;
            --pale-blue: #f8fbff;
        }

        body {
            background: linear-gradient(135deg, var(--light-blue) 0%, var(--pale-blue) 100%);
            min-height: 100vh;
            padding: 20px 0;
            color: #2c3e50;
        }

        .profile-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .exam-history-container {
            max-width: 1000px;
            margin: 40px auto;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .form-label {
            color: #2c3e50;
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--primary-blue);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--hover-blue);
            transform: scale(1.05);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-header i {
            font-size: 48px;
            color: var(--primary-blue);
        }

        .exam-card {
            background: var(--pale-blue);
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(26, 115, 232, 0.1);
            overflow: hidden;
        }

        .exam-card-header {
            background: var(--primary-blue);
            color: white;
            padding: 15px 20px;
            font-weight: 500;
        }

        .exam-card-body {
            padding: 20px;
        }

        .exam-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .stat-item {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            flex: 1;
            text-align: center;
            border: 1px solid rgba(26, 115, 232, 0.1);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--deep-blue);
        }

        .answer-list {
            list-style: none;
            padding: 0;
        }

        .answer-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid rgba(26, 115, 232, 0.1);
        }

        .answer-item.correct {
            border-left: 4px solid #28a745;
        }

        .answer-item.incorrect {
            border-left: 4px solid #dc3545;
        }

        .answer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .answer-status {
            font-size: 0.9rem;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .answer-status.correct {
            background: #d4edda;
            color: #155724;
        }

        .answer-status.incorrect {
            background: #f8d7da;
            color: #721c24;
        }

        .explanation {
            background: var(--light-blue);
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .view-details-btn {
            color: var(--primary-blue);
            background: none;
            border: none;
            padding: 0;
            font: inherit;
            cursor: pointer;
            text-decoration: underline;
        }

        .navbar {
            background: linear-gradient(to right, var(--primary-blue), var(--secondary-blue)) !important;
        }

        .navbar-brand, .nav-link {
            color: white !important;
        }

        .nav-link:hover {
            color: var(--accent-blue) !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">My Profile</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="profile-container">
            <div class="text-center mb-4">
                <i class='bx bxs-user-circle' style="font-size: 64px; color: var(--primary-blue);"></i>
                <h2 class="mt-3">My Results</h2>
            </div>

            <!-- <?php echo $message; ?>

            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" 
                               value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" 
                               value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Registration Number</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['registration_number'] ?? ''); ?>" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label">Class</label>
                    <input type="text" class="form-control" name="class" 
                           value="<?php echo htmlspecialchars($student['class'] ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" 
                           value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                </div>

                <hr class="my-4">

                <h4>Change Password</h4>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="new_password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password">
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form> -->
        </div>

        <!-- Exam History Section -->
        <div class="exam-history-container">
            <h3 class="mb-4"><i class='bx bx-history'></i> Exam History</h3>
            
            <?php if (empty($exam_history)): ?>
                <div class="alert alert-info">
                    You haven't taken any exams yet.
                </div>
            <?php else: ?>
                <?php foreach ($exam_history as $attempt): 
                    $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
                    $status = $percentage >= $attempt['passing_score'] ? 'Passed' : 'Failed';
                    $status_class = $status === 'Passed' ? 'success' : 'danger';
                ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?php echo htmlspecialchars($attempt['exam_title']); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Score:</strong> <?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Percentage:</strong> <?php echo number_format($percentage, 1); ?>%
                                </div>
                                <div class="col-md-3">
                                    <strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($attempt['start_time'])); ?>
                                </div>
                            </div>

                            <?php
                            $answers = getAttemptAnswers($db, $attempt['attempt_id']);
                            if (!empty($answers)):
                            ?>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-primary" type="button" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#answers-<?php echo $attempt['attempt_id']; ?>">
                                        View Details
                                    </button>
                                    <div class="collapse mt-3" id="answers-<?php echo $attempt['attempt_id']; ?>">
                                        <?php foreach ($answers as $answer): 
                                            $is_attempted = !is_null($answer['selected_answer']);
                                            $is_correct = $is_attempted && ($answer['selected_answer'] === $answer['correct_answer']);
                                        ?>
                                            <div class="card mb-2 <?php echo $is_attempted ? ($is_correct ? 'border-success' : 'border-danger') : 'border-warning'; ?>">
                                                <div class="card-body">
                                                    <p><strong>Question:</strong> <?php echo htmlspecialchars($answer['question_text']); ?></p>
                                                    <?php if ($is_attempted): ?>
                                                        <p>
                                                            <strong>Your Answer:</strong> 
                                                            <span class="<?php echo $is_correct ? 'text-success' : 'text-danger'; ?>">
                                                                <?php echo htmlspecialchars($answer['selected_answer']); ?>
                                                            </span>
                                                        </p>
                                                    <?php else: ?>
                                                        <p class="text-warning">
                                                            <strong>Your Answer:</strong> 
                                                            <span>Not attempted</span>
                                                        </p>
                                                    <?php endif; ?>
                                                    <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($answer['correct_answer']); ?></p>
                                                    <?php if (!$is_correct && !empty($answer['explanation'])): ?>
                                                        <div class="alert alert-info">
                                                            <strong>Explanation:</strong> <?php echo htmlspecialchars($answer['explanation']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 