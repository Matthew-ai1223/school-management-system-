<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

session_start();

$auth = new Auth();

// if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
//     header('Location: login.php');
//     exit();
// }

$attempt_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$attempt_id) {
    header('Location: students.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get attempt details with exam and student info
$query = "SELECT ea.*, e.title as exam_title, e.passing_score, 
          u.name as student_name, u.id as student_id
          FROM exam_attempts ea
          JOIN exams e ON ea.exam_id = e.id
          JOIN users u ON ea.user_id = u.id
          WHERE ea.id = :attempt_id";
$stmt = $db->prepare($query);
$stmt->execute([':attempt_id' => $attempt_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    header('Location: students.php');
    exit();
}

// Get all questions and responses
$query = "SELECT q.*, ur.selected_answer, ur.is_bookmarked
          FROM questions q
          LEFT JOIN user_responses ur ON q.id = ur.question_id AND ur.attempt_id = :attempt_id
          WHERE q.exam_id = :exam_id
          ORDER BY q.id";
$stmt = $db->prepare($query);
$stmt->execute([
    ':attempt_id' => $attempt_id,
    ':exam_id' => $attempt['exam_id']
]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_questions = count($questions);
$correct_answers = 0;
$incorrect_answers = 0;
$unanswered = 0;
$bookmarked = 0;

foreach ($questions as $question) {
    if ($question['selected_answer'] === null) {
        $unanswered++;
    } elseif ($question['selected_answer'] === $question['correct_answer']) {
        $correct_answers++;
    } else {
        $incorrect_answers++;
    }
    if ($question['is_bookmarked']) {
        $bookmarked++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attempt - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Exam Attempt Details</h1>
                    <a href="student-performance.php?id=<?php echo $attempt['student_id']; ?>" class="btn btn-secondary">
                        <i class='bx bx-arrow-back'></i> Back to Performance
                    </a>
                </div>

                <!-- Attempt Overview -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Attempt Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Student:</strong> <?php echo htmlspecialchars($attempt['student_name']); ?></p>
                                <p><strong>Exam:</strong> <?php echo htmlspecialchars($attempt['exam_title']); ?></p>
                                <p><strong>Date:</strong> <?php echo date('Y-m-d H:i', strtotime($attempt['start_time'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Score:</strong> 
                                    <span class="badge bg-<?php echo $attempt['score'] >= $attempt['passing_score'] ? 'success' : 'danger'; ?>">
                                        <?php echo $attempt['score']; ?>%
                                    </span>
                                </p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $attempt['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($attempt['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Duration:</strong> 
                                    <?php
                                    if ($attempt['end_time']) {
                                        $duration = strtotime($attempt['end_time']) - strtotime($attempt['start_time']);
                                        echo floor($duration / 60) . 'm ' . ($duration % 60) . 's';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Correct Answers</h5>
                                <h2><?php echo $correct_answers; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Incorrect Answers</h5>
                                <h2><?php echo $incorrect_answers; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Unanswered</h5>
                                <h2><?php echo $unanswered; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Bookmarked</h5>
                                <h2><?php echo $bookmarked; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Questions and Answers -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Questions and Answers</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($questions as $index => $question): ?>
                        <div class="question-block mb-4 pb-3 border-bottom">
                            <h6>Question <?php echo $index + 1; ?></h6>
                            <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            
                            <?php if ($question['image_url']): ?>
                            <div class="mb-3">
                                <img src="<?php echo htmlspecialchars($question['image_url']); ?>" 
                                     class="img-fluid" style="max-height: 200px;" alt="Question Image">
                            </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="list-group">
                                        <?php
                                        $options = [
                                            'A' => $question['option_a'],
                                            'B' => $question['option_b'],
                                            'C' => $question['option_c'],
                                            'D' => $question['option_d']
                                        ];
                                        foreach ($options as $key => $value):
                                            $class = '';
                                            if ($key === $question['correct_answer']) {
                                                $class = 'list-group-item-success';
                                            } elseif ($key === $question['selected_answer'] && $key !== $question['correct_answer']) {
                                                $class = 'list-group-item-danger';
                                            }
                                        ?>
                                        <div class="list-group-item <?php echo $class; ?>">
                                            <?php echo $key . ') ' . htmlspecialchars($value); ?>
                                            <?php if ($key === $question['selected_answer']): ?>
                                                <i class='bx bx-check float-end'></i>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($question['explanation']): ?>
                                    <div class="alert alert-info">
                                        <strong>Explanation:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 