<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$message = '';

// Get user details from the appropriate table
$table = $_SESSION['user_table'];
$stmt = $db->prepare("SELECT * FROM $table WHERE id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Check if account is still active and not expired
$is_expired = strtotime($user['expiration_date']) < strtotime('today');
if (!$user['is_active'] || $is_expired) {
    session_destroy();
    header('Location: login.php?error=expired');
    exit();
}

// Get user's exam history
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
                  WHERE ea.user_id = :user_id
    ORDER BY ea.start_time DESC";

$stmt = $db->prepare($exam_history_query);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$exam_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get detailed answers for each attempt
function getAttemptAnswers($db, $attempt_id) {
    // First get the exam_id for this attempt
    $get_exam_id = "SELECT exam_id FROM exam_attempts WHERE id = :attempt_id";
    $stmt = $db->prepare($get_exam_id);
    $stmt->execute([':attempt_id' => $attempt_id]);
    $exam_id = $stmt->fetchColumn();

    // Now get all questions for this exam, left joining with user responses
    $answers_query = "
        SELECT 
            q.question_text,
            q.correct_answer,
            ur.selected_answer,
            q.explanation,
            CASE 
                WHEN ur.selected_answer IS NULL THEN 'Not Attempted'
                ELSE ur.selected_answer 
            END as selected_answer
        FROM questions q
        LEFT JOIN user_responses ur ON ur.question_id = q.id AND ur.attempt_id = :attempt_id
        WHERE q.exam_id = :exam_id
        ORDER BY q.id";
    
    $stmt = $db->prepare($answers_query);
    $stmt->execute([
        ':attempt_id' => $attempt_id,
        ':exam_id' => $exam_id
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updates = [];
        $params = [':user_id' => $_SESSION['user_id']];

        // Only update fields that are provided and not empty
        if (!empty($_POST['fullname'])) {
            $updates[] = "fullname = :fullname";
            $params[':fullname'] = $_POST['fullname'];
        }

        if (!empty($_POST['phone'])) {
            $updates[] = "phone = :phone";
            $params[':phone'] = $_POST['phone'];
        }

        if (!empty($_POST['department'])) {
            $updates[] = "department = :department";
            $params[':department'] = $_POST['department'];
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
            $sql = "UPDATE $table SET " . implode(", ", $updates) . " WHERE id = :user_id";
            $stmt = $db->prepare($sql);
            if ($stmt->execute($params)) {
                $message = '<div class="alert alert-success">Profile updated successfully!</div>';
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM $table WHERE id = :user_id");
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error updating profile: ' . $e->getMessage() . '</div>';
    }
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

        .answer-item.not-attempted {
            border-left: 4px solid #ffc107;
            background-color: #fff9e6;
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

        .answer-status.not-attempted {
            background: #fff3cd;
            color: #856404;
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
    <nav class="navbar navbar-expand-lg">
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

    <div class="exam-history-container">
        <h3 class="mb-4"><i class='bx bx-history'></i> Exam History</h3>
        
        <?php if (empty($exam_history)): ?>
            <div class="alert alert-info">
                You haven't taken any exams yet.
            </div>
        <?php else: ?>
            <?php foreach ($exam_history as $attempt): ?>
                <div class="exam-card">
                    <div class="exam-card-header">
                        <h5 class="mb-0"><?php echo htmlspecialchars($attempt['exam_title']); ?></h5>
                    </div>
                    <div class="exam-card-body">
                        <div class="exam-stats">
                            <div class="stat-item">
                                <div class="stat-label">Score</div>
                                <div class="stat-value">
                                    <?php 
                                    $raw_score = $attempt['score'];
                                    $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
                                    echo $raw_score . '/' . $attempt['total_questions'];
                                    echo ' (' . number_format($percentage, 1) . '%)';
                                    ?>
                        </div>
                    </div>
                            <div class="stat-item">
                                <div class="stat-label">Status</div>
                                <div class="stat-value">
                                    <?php 
                                    $average_score = 50; // 50% is typically considered average
                                    if ($percentage >= $average_score) {
                                        echo '<span class="text-success">Passed</span>';
                                    } else {
                                        echo '<span class="text-danger">Failed</span>';
                                    }
                                    ?>
                </div>
            </div>
                            <div class="stat-item">
                                <div class="stat-label">Required Score</div>
                                <div class="stat-value"><?php echo $average_score; ?>% and above</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Date</div>
                                <div class="stat-value">
                                    <?php echo date('M d, Y', strtotime($attempt['start_time'])); ?>
                                </div>
                    </div>
                </div>

                        <?php
                        // Get detailed answers for this attempt
                        $answers = getAttemptAnswers($db, $attempt['attempt_id']);
                        ?>

                        <div class="answer-details" id="answers-<?php echo $attempt['attempt_id']; ?>" style="display: none;">
                            <h6 class="mt-3 mb-3">Detailed Answers</h6>
                            <div class="answer-list">
                                <?php foreach ($answers as $index => $answer): ?>
                                    <div class="answer-item <?php echo ($answer['selected_answer'] === 'Not Attempted' ? 'not-attempted' : ($answer['selected_answer'] === $answer['correct_answer'] ? 'correct' : 'incorrect')); ?>">
                                        <div class="answer-header">
                                            <strong>Question <?php echo $index + 1; ?></strong>
                                            <span class="answer-status <?php echo ($answer['selected_answer'] === 'Not Attempted' ? 'not-attempted' : ($answer['selected_answer'] === $answer['correct_answer'] ? 'correct' : 'incorrect')); ?>">
                                                <?php echo ($answer['selected_answer'] === 'Not Attempted' ? 'Not Attempted' : ($answer['selected_answer'] === $answer['correct_answer'] ? 'Correct' : 'Incorrect')); ?>
                                            </span>
                                        </div>
                                        <p><?php echo htmlspecialchars($answer['question_text']); ?></p>
                                        <div class="d-flex gap-3">
                                            <div>Your Answer: <strong><?php echo htmlspecialchars($answer['selected_answer']); ?></strong></div>
                                            <div>Correct Answer: <strong><?php echo htmlspecialchars($answer['correct_answer']); ?></strong></div>
                                        </div>
                                        <?php if ($answer['selected_answer'] !== $answer['correct_answer'] && !empty($answer['explanation'])): ?>
                                            <div class="explanation">
                                                <strong>Explanation:</strong> <?php echo htmlspecialchars($answer['explanation']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button class="view-details-btn mt-3" 
                                onclick="toggleAnswers('answers-<?php echo $attempt['attempt_id']; ?>', this)">
                            View Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
                <?php endif; ?>
            </div>

    <div class="profile-container">
        <div class="profile-header">
            <i class='bx bxs-user-circle'></i>
            <h2 class="mt-3">My Profile</h2>
        </div>

        <?php echo $message; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                <small class="text-muted">Email cannot be changed</small>
            </div>
            <div class="mb-3">
                <label for="fullname" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="fullname" name="fullname" 
                       value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" 
                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" 
                       value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                <label for="session" class="form-label">Session</label>
                <input type="text" class="form-control" value="<?php echo ucfirst($table === 'morning_students' ? 'Morning' : 'Afternoon'); ?>" disabled>
                        </div>
                        <div class="mb-3">
                <label for="expiration_date" class="form-label">Account Expiration Date</label>
                <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($user['expiration_date'])); ?>" disabled>
                                </div>

            <hr class="my-4">

            <h4>Change Password</h4>
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAnswers(answersId, button) {
            const answersDiv = document.getElementById(answersId);
            if (answersDiv.style.display === 'none') {
                answersDiv.style.display = 'block';
                button.textContent = 'Hide Details';
            } else {
                answersDiv.style.display = 'none';
                button.textContent = 'View Details';
            }
        }
    </script>
</body>
</html> 